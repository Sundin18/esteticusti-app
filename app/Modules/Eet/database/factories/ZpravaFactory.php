<?php

namespace App\Modules\Eet\database\factories;

use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Models\Zprava;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zprava>
 */
class ZpravaFactory extends Factory
{
    protected $model = Zprava::class;

    /**
     * Define the model's default state: a first attempt the service accepted.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trzba_id' => Trzba::factory(),
            'uuid_zpravy' => fake()->uuid(),
            'dat_odesl' => now(),
            'prvni_zaslani' => true,
            'xml_pozadavku' => '<Trzba/>',
            'xml_odpovedi' => '<Odpoved/>',
        ];
    }

    /**
     * Indicate that the attempt ended with the given error.
     */
    public function sChybou(int $kod, string $chyba): static
    {
        return $this->state(fn (array $attributes) => [
            'kod_chyby' => $kod,
            'chyba' => $chyba,
        ]);
    }
}
