<?php

namespace App\Modules\Eet\database\factories;

use App\Modules\Eet\Enums\Environment;
use App\Modules\Eet\Models\Settings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settings>
 */
class SettingsFactory extends Factory
{
    protected $model = Settings::class;

    /**
     * Výchozí stav modelu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'taxpayer_eic' => 'CZ'.fake()->numerify('#########'),
            'premises_name' => fake()->company(),
            'premises_address' => fake()->address(),
            'unit_id' => fake()->numberBetween(1, 999),
            'register_id' => 'P1',
            'environment' => Environment::Test,
            'response_timeout' => 2,
        ];
    }

    /**
     * Modul odesílá do produkčního prostředí.
     */
    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => Environment::Production,
        ]);
    }
}
