<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        $userData = null;
        $currentTenant = null;
        $tenantId = $request->session()->get('tenant_id');

        if ($user) {
            // Carregar roles e tenants explicitamente para evitar queries N+1
            $user->load('roles', 'tenants');
            $isAdminGeral = $user->hasRole('Administrador Geral');

            $userData = [
                ...$user->toArray(),
                'roles' => $user->roles->pluck('name')->toArray(),
                'is_admin_geral' => $isAdminGeral,
                'tenants' => $user->tenants->map(fn ($tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->nome,
                ])->toArray(),
            ];

            // Buscar tenant atual se não for admin geral
            if (! $isAdminGeral) {
                // Se não tem tenant_id na sessão, mas tem apenas um tenant, usar esse
                if (! $tenantId && $user->tenants->count() === 1) {
                    $tenantId = $user->tenants->first()->id;
                    $request->session()->put('tenant_id', $tenantId);
                }

                // Buscar tenant atual
                if ($tenantId) {
                    $tenant = Tenant::find($tenantId);
                    if ($tenant) {
                        $currentTenant = [
                            'id' => $tenant->id,
                            'nome' => $tenant->nome,
                            'logo_url' => $tenant->logo_url,
                        ];
                    }
                }
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $userData,
                'tenant_id' => $tenantId,
                'current_tenant' => $currentTenant,
            ],
            'toast' => fn () => $request->session()->get('toast'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrfToken' => $request->session()->token(),
        ];
    }
}
