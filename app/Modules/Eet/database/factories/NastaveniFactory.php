<?php

namespace App\Modules\Eet\database\factories;

use App\Modules\Eet\Enums\Prostredi;
use App\Modules\Eet\Models\Nastaveni;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nastaveni>
 */
class NastaveniFactory extends Factory
{
    protected $model = Nastaveni::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eic_popl' => 'CZ'.fake()->numerify('#########'),
            'nazev_provozovny' => fake()->company(),
            'adresa_provozovny' => fake()->address(),
            'id_jednotky' => fake()->numberBetween(1, 999),
            'id_pokl' => 'P1',
            'prostredi' => Prostredi::Test,
            'mezni_doba_odezvy' => 2,
        ];
    }

    /**
     * Indicate that the module sends to the production environment.
     */
    public function produkce(): static
    {
        return $this->state(fn (array $attributes) => [
            'prostredi' => Prostredi::Produkce,
        ]);
    }
}
