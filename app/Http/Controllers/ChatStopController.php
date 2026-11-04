<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ChatStopController extends Controller
{
    /**
     * Ask the running answer to end. The Livewire request stays open and
     * finishes normally, so the page gets a fresh snapshot instead of the stale
     * state an aborted request leaves behind.
     */
    public function __invoke(): Response
    {
        Cache::put(app('chatUser')->stopCacheKey(), true, 300);

        return response()->noContent();
    }
}
