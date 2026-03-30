<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\AuthController;

// Public Frontend
Route::get('/', [FrontendController::class, 'home'])->name('home');
Route::get('/projects', [FrontendController::class, 'projects'])->name('projects');
Route::get('/about', [FrontendController::class, 'about'])->name('about');
Route::get('/contact', [FrontendController::class, 'contact'])->name('contact');

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Admin (auth-protected)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('admin.dashboard');
    Route::get('/projects', \App\Livewire\Admin\Projects\Index::class)->name('admin.projects');
    Route::get('/skills', \App\Livewire\Admin\Skills\Index::class)->name('admin.skills');
    Route::get('/experiences', \App\Livewire\Admin\Experiences\Index::class)->name('admin.experiences');
    Route::get('/messages', \App\Livewire\Admin\Messages\Index::class)->name('admin.messages');
    Route::get('/settings', \App\Livewire\Admin\Settings\General::class)->name('admin.settings');
    Route::get('/social-links', \App\Livewire\Admin\Settings\SocialLinks::class)->name('admin.social-links');
});
