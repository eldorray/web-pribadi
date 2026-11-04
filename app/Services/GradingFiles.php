<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use Illuminate\Support\Facades\Storage;

/**
 * Student work lives on the private `local` disk. Deleting an item, a batch or
 * a user has to take the bytes with it.
 */
final class GradingFiles
{
    public static function deleteForItem(GradingItem $item): void
    {
        $disk = Storage::disk('local');

        foreach ((array) $item->files as $file) {
            if (is_array($file) && isset($file['path']) && is_string($file['path'])) {
                $disk->delete($file['path']);
            }
        }
    }

    public static function deleteForBatch(GradingBatch $batch): void
    {
        foreach ($batch->items()->get() as $item) {
            self::deleteForItem($item);
        }
    }

    public static function deleteForUser(ChatUser $user): void
    {
        foreach ($user->gradingBatches()->get() as $batch) {
            self::deleteForBatch($batch);
        }
    }
}
