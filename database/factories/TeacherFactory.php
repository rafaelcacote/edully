<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'usuario_id' => User::factory(),
            'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
            'especializacao' => fake()->randomElement(['Educação Especial', 'Educação Infantil', 'Educação Inclusiva', 'Psicopedagogia', null]),
            'ativo' => true,
        ];
    }

    /**
     * Indicate that the teacher is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'ativo' => false,
        ]);
    }
}
