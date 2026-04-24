<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\MessageReceived;
use App\Listeners\AutoAssignConversation;

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
        // Registrar listener para auto-asignación de conversaciones
        Event::listen(
            MessageReceived::class,
            AutoAssignConversation::class,
        );
    }
}
