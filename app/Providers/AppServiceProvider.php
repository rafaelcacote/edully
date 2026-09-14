<?php

namespace App\Providers;

use App\Listeners\UpdateLastLoginAt;
use App\Models\PersonalAccessToken;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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

        // Atrás do Nginx HTTPS → container HTTP: força scheme para assets do Vite/Inertia.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
