<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            'subdominio' => fake()->unique()->slug(2),
            'ativo' => true,
            'divisao_letiva' => 'bimestral',
        ];
    }
}
