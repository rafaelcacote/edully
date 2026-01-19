<?php

namespace App\Providers;

use App\Listeners\UpdateLastLoginAt;
use App\Models\PersonalAccessToken;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            UpdateLastLoginAt::class,
        ],
    ];

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
        parent::boot();

        // Configurar Sanctum para usar o modelo customizado com schema laravel
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
