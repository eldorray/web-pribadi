<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatAuthController;
use App\Http\Controllers\ChatFileController;
use App\Http\Controllers\ChatStopController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\GradingExportController;
use App\Http\Controllers\GradingProcessController;
use App\Http\Controllers\GradingUploadController;
use App\Http\Controllers\GradingVideoController;
use App\Http\Middleware\ChatPin;
use App\Http\Middleware\TrackVisitor;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Projects\Index;
use App\Livewire\Admin\Settings\General;
use App\Livewire\Admin\Settings\SocialLinks;
use App\Livewire\Chat\Grading\BatchPage;
use App\Livewire\Chat\Grading\Index as GradingIndex;
use App\Livewire\Chat\Page;
use Illuminate\Support\Facades\Route;

// Public Frontend
Route::middleware(TrackVisitor::class)->group(function () {
    Route::get('/', [FrontendController::class, 'home'])->name('home');
    Route::get('/projects', [FrontendController::class, 'projects'])->name('projects');
    Route::get('/about', [FrontendController::class, 'about'])->name('about');
    Route::get('/contact', [FrontendController::class, 'contact'])->name('contact');
});

// Chat AI — PIN-gated, deliberately outside TrackVisitor and never linked
// from the portfolio navigation (see <meta name="robots" content="noindex">).
Route::prefix('chat')->group(function () {
    Route::get('/pin', [ChatAuthController::class, 'showPin'])->name('chat.pin');
    Route::post('/pin', [ChatAuthController::class, 'submitPin'])->name('chat.pin.submit');
    Route::post('/logout', [ChatAuthController::class, 'logout'])->name('chat.logout');

    Route::middleware(ChatPin::class)->group(function () {
        Route::post('/stop', ChatStopController::class)->name('chat.stop');
        Route::get('/penilaian', GradingIndex::class)->name('chat.grading');
        Route::get('/penilaian/{batch}', BatchPage::class)->name('chat.grading.batch')->whereNumber('batch');
        Route::post('/penilaian/{batch}/items', [GradingUploadController::class, 'store'])->name('chat.grading.items.store')->whereNumber('batch');
        Route::get('/penilaian/{batch}/export', GradingExportController::class)->name('chat.grading.export')->whereNumber('batch');
        Route::post('/penilaian/{batch}/videos', [GradingVideoController::class, 'store'])->name('chat.grading.videos.store')->whereNumber('batch');
        Route::post('/penilaian/items/{item}/frames', [GradingVideoController::class, 'frames'])->name('chat.grading.videos.frames')->whereNumber('item');
        Route::post('/penilaian/items/{item}/audio', [GradingVideoController::class, 'audio'])->name('chat.grading.videos.audio')->whereNumber('item');
        Route::post('/penilaian/items/{item}/ready', [GradingVideoController::class, 'ready'])->name('chat.grading.videos.ready')->whereNumber('item');
        Route::post('/penilaian/items/{item}/grade', [GradingProcessController::class, 'grade'])->name('chat.grading.items.grade')->whereNumber('item');
        Route::get('/penilaian/items/{item}/file/{index}', [GradingUploadController::class, 'file'])->name('chat.grading.file')->whereNumber(['item', 'index']);
        Route::get('/file/{message}/{index}', ChatFileController::class)->name('chat.file');
        Route::get('/{conversation?}', Page::class)->name('chat')->whereNumber('conversation');
    });
});

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Admin (auth-protected)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/', Dashboard::class)->name('admin.dashboard');
    Route::get('/projects', Index::class)->name('admin.projects');
    Route::get('/skills', App\Livewire\Admin\Skills\Index::class)->name('admin.skills');
    Route::get('/tools', App\Livewire\Admin\Tools\Index::class)->name('admin.tools');
    Route::get('/experiences', App\Livewire\Admin\Experiences\Index::class)->name('admin.experiences');
    Route::get('/messages', App\Livewire\Admin\Messages\Index::class)->name('admin.messages');
    Route::get('/chat-ai', App\Livewire\Admin\ChatAi\Index::class)->name('admin.chat-ai');
    Route::get('/settings', General::class)->name('admin.settings');
    Route::get('/social-links', SocialLinks::class)->name('admin.social-links');
});
