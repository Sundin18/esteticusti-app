<?php

use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Trzba;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – přehled')] class extends Component {
    /**
     * @return array{pocet: int, soucet: string}
     */
    #[Computed]
    public function dnes(): array
    {
        $dnesni = Trzba::query()->whereDate('dat_trzby', today())->where('stav', '!=', StavTrzby::Zamitnuta);

        return [
            'pocet' => $dnesni->count(),
            'soucet' => Trzba::formatujCastku((string) $dnesni->sum('celk_trzba')),
        ];
    }

    #[Computed]
    public function pocetNepotvrzenych(): int
    {
        return Trzba::query()->where('stav', '!=', StavTrzby::Potvrzena)->count();
    }

    #[Computed]
    public function certifikat(): ?Certifikat
    {
        return Certifikat::aktivni();
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">EET</flux:heading>
            <flux:subheading size="lg">Evidence tržeb</flux:subheading>
        </div>

        <flux:button :href="route('eet.trzby.nova')" variant="primary" icon="plus" wire:navigate>Nová tržba</flux:button>
    </div>

    <flux:separator variant="subtle" class="my-6" />

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text>Dnešní tržby</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->dnes['soucet'] }}</flux:heading>
            <flux:text class="mt-1">Počet: {{ $this->dnes['pocet'] }}</flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Nepotvrzené tržby</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->pocetNepotvrzenych }}</flux:heading>
            <flux:text class="mt-1">
                <flux:link :href="route('eet.trzby.seznam')" wire:navigate>Zobrazit tržby</flux:link>
            </flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Pokladní certifikát</flux:text>
            @if ($this->certifikat === null)
                <flux:heading size="xl" class="mt-2">Chybí</flux:heading>
                <flux:text class="mt-1">Certifikát nahraje správce v nastavení.</flux:text>
            @else
                <flux:heading size="xl" class="mt-2">{{ $this->certifikat->jePlatny() ? 'Platný' : 'Neplatný' }}</flux:heading>
                <flux:text class="mt-1">do {{ $this->certifikat->platnost_do->format('j. n. Y') }}</flux:text>
            @endif
        </flux:card>
    </div>
</section>
