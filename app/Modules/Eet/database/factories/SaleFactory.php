<?php

namespace App\Modules\Eet\database\factories;

use App\Models\User;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Výchozí stav modelu: tržba, která ještě nebyla odeslána.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => (string) fake()->unique()->numberBetween(1, 999999),
            'taxpayer_eic' => 'CZ683555118',
            'premises_name' => fake()->company(),
            'premises_address' => fake()->address(),
            'unit_id' => 101,
            'register_id' => 'P1',
            'sold_at' => now(),
            'total_amount' => fake()->randomFloat(2, 100, 5000),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'status' => SaleStatus::Pending,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Tržbu potvrdilo testovací prostředí.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleStatus::Confirmed,
            'pok' => Str::uuid().'-ff',
            'is_test' => true,
        ]);
    }

    /**
     * Tržba je potvrzeným stornem zadané tržby na celou její částku.
     */
    public function cancelling(Sale $sale): static
    {
        return $this->confirmed()->state(fn (array $attributes) => [
            'cancelled_sale_id' => $sale->id,
            'total_amount' => bcsub('0', $sale->total_amount, 2),
            'payment_method' => $sale->payment_method,
        ]);
    }

    /**
     * Tržbu se nepodařilo doručit a čeká na další pokus.
     */
    public function awaitingRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleStatus::AwaitingRetry,
        ]);
    }

    /**
     * Tržbu služba zamítla.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleStatus::Rejected,
        ]);
    }
}
