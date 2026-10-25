<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TenantsController extends Controller
{
    /**
     * Display a listing of the tenants.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'active']);

        $tenants = Tenant::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                // Remove formatação do CNPJ para busca
                $cnpjSearch = preg_replace('/[^0-9]/', '', $search);
                $query->where(function ($q) use ($search, $cnpjSearch) {
                    $q->where('nome', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('telefone', 'ilike', "%{$search}%")
                        ->orWhere('subdominio', 'ilike', "%{$search}%")
                        ->orWhere('cnpj', 'ilike', "%{$cnpjSearch}%");
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('ativo', $active);
                }
            })
            ->orderBy('nome')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/tenants/Index', [
            'tenants' => $tenants,
            'filters' => $filters,
        ]);
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): Response
    {
        return Inertia::render('admin/tenants/Show', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create(): Response
    {
        return Inertia::render('admin/tenants/Create');
    }

    /**
     * Store a newly created tenant.
     */
    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Remove formatação do CNPJ (pontos, traços, barras, espaços)
        if (! empty($validated['cnpj'])) {
            $validated['cnpj'] = preg_replace('/[^0-9]/', '', $validated['cnpj']);
        }

        // Remove formatação do CEP (pontos, traços, espaços)
        if (! empty($validated['endereco_cep'])) {
            $validated['endereco_cep'] = preg_replace('/[^0-9]/', '', $validated['endereco_cep']);
        }

        // Processar upload da logo
        $logoUrl = $validated['logo_url'] ?? null;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('tenants/logos', 'public');
            $logoUrl = Storage::disk('public')->url($logoPath);
        }

        $tenant = Tenant::create([
            'nome' => $validated['nome'],
            'subdominio' => $validated['subdominio'] ?? null,
            'cnpj' => $validated['cnpj'] ?? null,
            'email' => $validated['email'],
            'telefone' => $validated['telefone'] ?? null,
            'endereco' => $validated['endereco'] ?? null,
            'endereco_numero' => $validated['endereco_numero'] ?? null,
            'endereco_complemento' => $validated['endereco_complemento'] ?? null,
            'endereco_bairro' => $validated['endereco_bairro'] ?? null,
            'endereco_cep' => $validated['endereco_cep'] ?? null,
            'endereco_cidade' => $validated['endereco_cidade'] ?? null,
            'endereco_estado' => $validated['endereco_estado'] ?? null,
            'endereco_pais' => $validated['endereco_pais'] ?? 'Brasil',
            'logo_url' => $logoUrl,
            'plano_id' => $validated['plano_id'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
            'trial_ate' => $validated['trial_ate'] ?? null,
            'divisao_letiva' => $validated['divisao_letiva'],
        ]);

        return redirect()
            ->route('tenants.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Escola criada',
                'message' => 'A escola foi cadastrada com sucesso.',
            ]);
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('admin/tenants/Edit', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Update the specified tenant.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();

        // Remove formatação do CNPJ (pontos, traços, barras, espaços)
        if (! empty($validated['cnpj'])) {
            $validated['cnpj'] = preg_replace('/[^0-9]/', '', $validated['cnpj']);
        }

        // Remove formatação do CEP (pontos, traços, espaços)
        if (! empty($validated['endereco_cep'])) {
            $validated['endereco_cep'] = preg_replace('/[^0-9]/', '', $validated['endereco_cep']);
        }

        // Processar upload da logo
        $logoUrl = $validated['logo_url'] ?? $tenant->logo_url;
        if ($request->hasFile('logo')) {
            // Deletar logo antiga se existir e for do storage local
            $storedLogoUrl = $tenant->getRawOriginal('logo_url');
            if ($storedLogoUrl) {
                $storageBaseUrl = Storage::disk('public')->url('');
                $oldLogoPath = null;

                if (str_starts_with($storedLogoUrl, $storageBaseUrl)) {
                    $oldLogoPath = substr($storedLogoUrl, strlen($storageBaseUrl));
                } elseif (str_starts_with($storedLogoUrl, '/storage/')) {
                    $oldLogoPath = substr($storedLogoUrl, strlen('/storage/'));
                } elseif (str_starts_with($storedLogoUrl, 'storage/')) {
                    $oldLogoPath = substr($storedLogoUrl, strlen('storage/'));
                }

                if ($oldLogoPath) {
                    $oldLogoPath = ltrim($oldLogoPath, '/');
                    if (Storage::disk('public')->exists($oldLogoPath)) {
                        Storage::disk('public')->delete($oldLogoPath);
                    }
                }
            }

            $logo = $request->file('logo');
            $logoPath = $logo->store('tenants/logos', 'public');
            $logoUrl = Storage::disk('public')->url($logoPath);
        }

        $tenant->update([
            'nome' => $validated['nome'],
            'subdominio' => $validated['subdominio'] ?? null,
            'cnpj' => $validated['cnpj'] ?? null,
            'email' => $validated['email'],
            'telefone' => $validated['telefone'] ?? null,
            'endereco' => $validated['endereco'] ?? null,
            'endereco_numero' => $validated['endereco_numero'] ?? null,
            'endereco_complemento' => $validated['endereco_complemento'] ?? null,
            'endereco_bairro' => $validated['endereco_bairro'] ?? null,
            'endereco_cep' => $validated['endereco_cep'] ?? null,
            'endereco_cidade' => $validated['endereco_cidade'] ?? null,
            'endereco_estado' => $validated['endereco_estado'] ?? null,
            'endereco_pais' => $validated['endereco_pais'] ?? $tenant->endereco_pais ?? 'Brasil',
            'logo_url' => $logoUrl,
            'plano_id' => $validated['plano_id'] ?? null,
            'ativo' => $validated['ativo'] ?? $tenant->ativo,
            'trial_ate' => $validated['trial_ate'] ?? null,
            'divisao_letiva' => $validated['divisao_letiva'],
        ]);

        return redirect()
            ->route('tenants.edit', $tenant)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Escola atualizada',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()
            ->route('tenants.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Escola excluída',
                'message' => 'A escola foi removida com sucesso.',
            ]);
    }
}
