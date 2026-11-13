<?php

use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Sale;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – přehled')] class extends Component {
    /**
     * @return array{count: int, total: string}
     */
    #[Computed]
    public function today(): array
    {
        $todaysSales = Sale::query()->whereDate('sold_at', today())->where('status', '!=', SaleStatus::Rejected);

        return [
            'count' => $todaysSales->count(),
            'total' => Sale::formatAmount((string) $todaysSales->sum('total_amount')),
        ];
    }

    #[Computed]
    public function unconfirmedCount(): int
    {
        return Sale::query()->where('status', '!=', SaleStatus::Confirmed)->count();
    }

    #[Computed]
    public function certificate(): ?Certificate
    {
        return Certificate::active();
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">EET</flux:heading>
            <flux:subheading size="lg">Evidence tržeb</flux:subheading>
        </div>

        <flux:button :href="route('eet.sales.create')" variant="primary" icon="plus" wire:navigate>Nová tržba</flux:button>
    </div>

    <flux:separator variant="subtle" class="my-6" />

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text>Dnešní tržby</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->today['total'] }}</flux:heading>
            <flux:text class="mt-1">Počet: {{ $this->today['count'] }}</flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Nepotvrzené tržby</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->unconfirmedCount }}</flux:heading>
            <flux:text class="mt-1">
                <flux:link :href="route('eet.sales.index')" wire:navigate>Zobrazit tržby</flux:link>
            </flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Pokladní certifikát</flux:text>
            @if ($this->certificate === null)
                <flux:heading size="xl" class="mt-2">Chybí</flux:heading>
                <flux:text class="mt-1">Certifikát nahraje správce v nastavení.</flux:text>
            @else
                <flux:heading size="xl" class="mt-2">{{ $this->certificate->isValid() ? 'Platný' : 'Neplatný' }}</flux:heading>
                <flux:text class="mt-1">do {{ $this->certificate->valid_until->format('j. n. Y') }}</flux:text>
            @endif
        </flux:card>
    </div>
</section>
