<?php

namespace App\Providers;

use App\Http\Middleware\ChatPin;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire component updates POST to /livewire/update, which sits
        // outside the /chat route group. Without this, ChatPin never runs on
        // those requests: app('chatUser') is unbound (every chat action 500s)
        // and the PIN check would be skipped entirely.
        Livewire::addPersistentMiddleware([ChatPin::class]);
    }
}
