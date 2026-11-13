<?php

use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Message;
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

    /**
     * Počet tržeb, které nemají potvrzení déle než 24 hodin; zákonná lhůta na odeslání je 48 hodin.
     */
    #[Computed]
    public function overdueCount(): int
    {
        return Sale::query()->unconfirmedLongerThan(24)->count();
    }

    /**
     * Poslední pokus o odeslání, podle kterého se ukazuje stav spojení.
     */
    #[Computed]
    public function lastMessage(): ?Message
    {
        return Message::query()->latest('id')->first();
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

    <div class="mb-6 space-y-4 empty:hidden">
        @if ($this->overdueCount > 0)
            <flux:callout variant="danger" icon="exclamation-triangle" data-test="overdue-alert">
                <flux:callout.heading>Tržby bez potvrzení déle než 24 hodin: {{ $this->overdueCount }}</flux:callout.heading>
                <flux:callout.text>
                    Zákonná lhůta na odeslání tržby je 48 hodin.
                    <flux:link :href="route('eet.sales.index', ['status' => Sale::FILTER_UNCONFIRMED])" wire:navigate>Zobrazit nepotvrzené tržby</flux:link>
                </flux:callout.text>
            </flux:callout>
        @endif

        @if ($this->certificate === null)
            <flux:callout variant="danger" icon="exclamation-triangle" data-test="certificate-alert">
                <flux:callout.heading>Není nahrán pokladní certifikát</flux:callout.heading>
                <flux:callout.text>Bez certifikátu nelze tržby odesílat. Certifikát nahraje správce v nastavení.</flux:callout.text>
            </flux:callout>
        @elseif (! $this->certificate->isValid())
            <flux:callout variant="danger" icon="exclamation-triangle" data-test="certificate-alert">
                <flux:callout.heading>Pokladní certifikát není platný</flux:callout.heading>
                <flux:callout.text>Platnost skončila {{ $this->certificate->valid_until->format('j. n. Y') }}. Tržby nelze odesílat, dokud správce nenahraje nový certifikát.</flux:callout.text>
            </flux:callout>
        @elseif ($this->certificate->expiresWithinDays(30))
            <flux:callout variant="warning" icon="exclamation-triangle" data-test="certificate-alert">
                <flux:callout.heading>Pokladní certifikát brzy vyprší</flux:callout.heading>
                <flux:callout.text>Platí do {{ $this->certificate->valid_until->format('j. n. Y') }}. Nový certifikát nahraje správce v nastavení.</flux:callout.text>
            </flux:callout>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card>
            <flux:text>Dnešní tržby</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->today['total'] }}</flux:heading>
            <flux:text class="mt-1">Počet: {{ $this->today['count'] }}</flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Nepotvrzené tržby</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->unconfirmedCount }}</flux:heading>
            <flux:text class="mt-1">
                <flux:link :href="route('eet.sales.index', ['status' => Sale::FILTER_UNCONFIRMED])" wire:navigate>Zobrazit nepotvrzené</flux:link>
            </flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Spojení s EET</flux:text>
            @if ($this->lastMessage === null)
                <flux:heading size="xl" class="mt-2">—</flux:heading>
                <flux:text class="mt-1">Zatím nebylo nic odesláno.</flux:text>
            @else
                <flux:heading size="xl" class="mt-2">{{ $this->lastMessage->isSuccessful() ? 'V pořádku' : 'Chyba' }}</flux:heading>
                <flux:text class="mt-1">
                    Poslední odeslání {{ $this->lastMessage->sent_at->format('j. n. Y H:i') }}{{ $this->lastMessage->isSuccessful() ? '' : ' selhalo' }}
                </flux:text>
            @endif
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
