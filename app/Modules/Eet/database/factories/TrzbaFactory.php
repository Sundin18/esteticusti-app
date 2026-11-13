<?php

namespace App\Modules\Eet\database\factories;

use App\Models\User;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trzba>
 */
class TrzbaFactory extends Factory
{
    protected $model = Trzba::class;

    /**
     * Define the model's default state: a sale that has not been sent yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'porad_cis' => (string) fake()->unique()->numberBetween(1, 999999),
            'dat_trzby' => now(),
            'celk_trzba' => fake()->randomFloat(2, 100, 5000),
            'stav' => StavTrzby::Ceka,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the test environment confirmed the sale.
     */
    public function potvrzena(): static
    {
        return $this->state(fn (array $attributes) => [
            'stav' => StavTrzby::Potvrzena,
            'pok' => Str::uuid().'-ff',
            'testovaci' => true,
        ]);
    }

    /**
     * Indicate that the sale could not be delivered and waits for another attempt.
     */
    public function kOpakovani(): static
    {
        return $this->state(fn (array $attributes) => [
            'stav' => StavTrzby::KOpakovani,
        ]);
    }

    /**
     * Indicate that the service rejected the sale.
     */
    public function zamitnuta(): static
    {
        return $this->state(fn (array $attributes) => [
            'stav' => StavTrzby::Zamitnuta,
        ]);
    }
}
