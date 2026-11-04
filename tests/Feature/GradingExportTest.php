<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ChatPin;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingExportTest extends TestCase
{
    use RefreshDatabase;

    private ChatUser $user;

    private GradingBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $this->batch = $this->user->gradingBatches()->create([
            'name' => 'Tugas Kelas A',
            'model' => 'chat-basic',
            'rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [
                ['name' => 'Isi', 'description' => '', 'max_score' => 10],
                ['name' => 'Bahasa', 'description' => '', 'max_score' => 20],
            ]],
        ]);
    }

    private function item(array $attributes): GradingItem
    {
        return $this->batch->items()->create($attributes + [
            'student_name' => 'Budi',
            'type' => 'document',
            'original_name' => 'budi.docx',
            'status' => GradingItem::READY,
            'final_scores' => [5, 15],
        ]);
    }

    private function export(?ChatUser $as = null)
    {
        return $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($as ?? $this->user))
            ->get(route('chat.grading.export', $this->batch));
    }

    /** @return list<list<string>> */
    private function rows(): array
    {
        $content = $this->export()->assertOk()->streamedContent();
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($content, 3));
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    #[Test]
    public function export_starts_with_a_utf8_bom(): void
    {
        $response = $this->export()->assertOk();

        $this->assertSame("\xEF\xBB\xBF", substr($response->streamedContent(), 0, 3));
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('tugas-kelas-a', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function export_has_one_column_per_criterion(): void
    {
        $this->assertSame(['Nama', 'Isi', 'Bahasa', 'Total', 'Skala 0-100', 'Catatan', 'Status'], $this->rows()[0]);
    }

    #[Test]
    public function export_writes_totals_and_scale_for_approved(): void
    {
        $this->item(['status' => GradingItem::APPROVED, 'approved_at' => now(), 'lecturer_note' => 'Baik']);

        $this->assertSame(['Budi', '5', '15', '20', '66.67', 'Baik', 'disetujui'], $this->rows()[1]);
    }

    #[Test]
    public function export_leaves_unapproved_scores_empty(): void
    {
        $this->item(['student_name' => 'Citra', 'lecturer_note' => 'Belum']);

        $this->assertSame(['Citra', '', '', '', '', 'Belum', 'belum disetujui'], $this->rows()[1]);
    }

    #[Test]
    public function regrade_in_progress_still_exports_the_approved_grade(): void
    {
        $this->item(['status' => GradingItem::QUEUED, 'approved_at' => now()]);

        $this->assertSame('disetujui', $this->rows()[1][6]);
        $this->assertSame('20', $this->rows()[1][3]);
    }

    #[Test]
    public function export_neutralises_formula_cells(): void
    {
        $this->item(['student_name' => '=SUM(A1)', 'lecturer_note' => '@cmd']);
        $this->batch->update(['rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [
            ['name' => '+Isi', 'description' => '', 'max_score' => 10],
            ['name' => '-Bahasa', 'description' => '', 'max_score' => 20],
        ]]]);

        $rows = $this->rows();

        $this->assertSame("'+Isi", $rows[0][1]);
        $this->assertSame("'-Bahasa", $rows[0][2]);
        $this->assertSame("'=SUM(A1)", $rows[1][0]);
        $this->assertSame("'@cmd", $rows[1][5]);
    }

    #[Test]
    public function foreign_batch_export_is_404(): void
    {
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);

        $this->export($other)->assertNotFound();
    }

    #[Test]
    public function export_requires_the_pin(): void
    {
        $this->get(route('chat.grading.export', $this->batch))->assertRedirect(route('chat.pin'));
    }

    #[Test]
    public function export_follows_the_name_order_so_nim_prefixes_sort(): void
    {
        $this->item(['student_name' => '1000000012 - Citra']);
        $this->item(['student_name' => '1000000001 - Ayu']);

        $rows = $this->rows();

        $this->assertSame('1000000001 - Ayu', $rows[1][0]);
        $this->assertSame('1000000012 - Citra', $rows[2][0]);
    }
}
