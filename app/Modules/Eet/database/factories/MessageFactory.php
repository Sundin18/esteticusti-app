<?php

namespace App\Modules\Eet\database\factories;

use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Výchozí stav modelu: první pokus, který služba přijala.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'message_uuid' => fake()->uuid(),
            'sent_at' => now(),
            'is_first_attempt' => true,
            'request_xml' => '<Trzba/>',
            'response_xml' => '<Odpoved/>',
        ];
    }

    /**
     * Pokus skončil zadanou chybou.
     */
    public function withError(int $code, string $error): static
    {
        return $this->state(fn (array $attributes) => [
            'error_code' => $code,
            'error' => $error,
        ]);
    }
}
