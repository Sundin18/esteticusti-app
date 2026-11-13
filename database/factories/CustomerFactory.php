<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Výchozí stav modelu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'phone' => fake()->numerify('+420 ### ### ###'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    /**
     * Klient neuvedl žádný kontakt.
     */
    public function withoutContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => null,
            'email' => null,
        ]);
    }
}
