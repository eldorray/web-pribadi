<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChatUser;
use App\Models\GradingItem;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of the lecturer's grades. Only approved rows carry numbers, so an AI
 * proposal can never leave the app looking like a final grade.
 */
class GradingExportController extends Controller
{
    public function __invoke(int $batch): StreamedResponse
    {
        /** @var ChatUser $chatUser */
        $chatUser = app('chatUser');
        $batch = $chatUser->gradingBatches()->findOrFail($batch);
        $criteria = $batch->criteria();

        return response()->streamDownload(function () use ($batch, $criteria): void {
            $out = fopen('php://output', 'w');

            // Without the BOM Excel reads UTF-8 names as mojibake.
            fwrite($out, "\xEF\xBB\xBF");

            $header = ['Nama', ...array_column($criteria, 'name'), 'Total', 'Skala 0-100', 'Catatan', 'Status'];
            fputcsv($out, array_map($this->safeCell(...), $header), escape: '');

            foreach ($batch->items()->orderBy('student_name')->cursor() as $item) {
                $item->setRelation('batch', $batch);
                fputcsv($out, $this->row($item, count($criteria)), escape: '');
            }

            fclose($out);
        }, Str::slug($batch->name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string> */
    private function row(GradingItem $item, int $criteriaCount): array
    {
        $approved = $item->approved_at !== null;

        $scores = $approved
            ? array_map(fn ($score) => (string) $score, (array) $item->final_scores)
            : array_fill(0, $criteriaCount, '');

        return [
            $this->safeCell($item->student_name),
            ...$scores,
            $approved ? (string) $item->finalTotal() : '',
            $approved ? (string) $item->finalScaled() : '',
            $this->safeCell((string) $item->lecturer_note),
            $approved ? 'disetujui' : 'belum disetujui',
        ];
    }

    /** Excel runs cells starting with these as formulas, even in a CSV. */
    public function safeCell(string $value): string
    {
        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'{$value}" : $value;
    }
}
