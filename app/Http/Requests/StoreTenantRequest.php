<?php

namespace App\Http\Requests;

use App\Enums\DivisaoLetiva;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'subdominio' => ['nullable', 'string', 'max:255', Rule::unique(Tenant::class, 'subdominio')],
            'cnpj' => ['nullable', 'string', 'regex:/^[0-9]{14}$|^[0-9]{2}\.[0-9]{3}\.[0-9]{3}\/[0-9]{4}-[0-9]{2}$/', Rule::unique(Tenant::class, 'cnpj')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Tenant::class, 'email')],
            'telefone' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string'],
            'endereco_numero' => ['nullable', 'string', 'max:20'],
            'endereco_complemento' => ['nullable', 'string', 'max:100'],
            'endereco_bairro' => ['nullable', 'string', 'max:100'],
            'endereco_cep' => ['nullable', 'string', 'regex:/^[0-9]{8}$|^[0-9]{5}-[0-9]{3}$/', 'max:10'],
            'endereco_cidade' => ['nullable', 'string', 'max:100'],
            'endereco_estado' => ['nullable', 'string', 'max:2'],
            'endereco_pais' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:5120'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'plano_id' => ['nullable', 'uuid'],
            'ativo' => ['nullable', 'boolean'],
            'trial_ate' => ['nullable', 'date'],
            'divisao_letiva' => ['required', Rule::enum(DivisaoLetiva::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'divisao_letiva.required' => 'Selecione como a escola divide o ano letivo.',
            'divisao_letiva.enum' => 'A divisão do ano letivo deve ser bimestre ou trimestre escolar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('divisao_letiva')) {
            $this->merge([
                'divisao_letiva' => DivisaoLetiva::Bimestral->value,
            ]);
        }
    }
}
