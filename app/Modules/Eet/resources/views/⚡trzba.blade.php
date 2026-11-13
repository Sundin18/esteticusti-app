<?php

use App\Modules\Eet\Actions\PosliTrzbu;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Services\NeuplneNastaveni;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – detail tržby')] class extends Component {
    public Trzba $trzba;

    public function mount(Trzba $trzba): void
    {
        $this->trzba = $trzba->load(['user', 'zpravy']);
    }

    /**
     * Make another attempt to send a sale that has not been confirmed.
     */
    public function odesliZnovu(PosliTrzbu $posliTrzbu): void
    {
        $this->authorize('eet.view');

        try {
            $posliTrzbu->handle($this->trzba);
        } catch (NeuplneNastaveni $vyjimka) {
            Flux::toast(variant: 'danger', text: $vyjimka->getMessage());

            return;
        }

        $this->trzba->load('zpravy');

        if ($this->trzba->jePotvrzena()) {
            Flux::toast(variant: 'success', text: 'Tržba byla potvrzena.');
        } else {
            Flux::toast(variant: 'danger', text: 'Tržbu se nepodařilo odeslat.');
        }
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Tržba č. {{ $trzba->porad_cis }}</flux:heading>
            <flux:subheading size="lg">
                <flux:badge size="sm" :color="$trzba->stav->color()">{{ $trzba->stav->label() }}</flux:badge>
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            @if (! $trzba->jePotvrzena())
                <flux:button wire:click="odesliZnovu" icon="arrow-path" data-test="odeslat-znovu">Odeslat znovu</flux:button>
            @endif

            @if ($trzba->stav !== StavTrzby::Zamitnuta)
                <flux:button :href="route('eet.trzby.uctenka', $trzba)" target="_blank" icon="printer" data-test="uctenka">Účtenka</flux:button>
            @endif

            <flux:button :href="route('eet.trzby.seznam')" variant="ghost" wire:navigate>Zpět na tržby</flux:button>
        </div>
    </div>

    <flux:separator variant="subtle" class="my-6" />

    <dl class="grid max-w-2xl gap-x-6 gap-y-4 sm:grid-cols-2">
        <div>
            <flux:text>Částka</flux:text>
            <flux:heading size="lg">{{ $trzba->formatovanaCastka() }}</flux:heading>
        </div>
        <div>
            <flux:text>Datum a čas tržby</flux:text>
            <flux:heading size="lg">{{ $trzba->dat_trzby->format('j. n. Y H:i:s') }}</flux:heading>
        </div>
        <div>
            <flux:text>Zadal</flux:text>
            <flux:heading>{{ $trzba->user->name }}</flux:heading>
        </div>
        <div>
            <flux:text>POK</flux:text>
            <flux:heading class="break-all">
                {{ $trzba->pok ?? '—' }}
                @if ($trzba->jePotvrzena() && $trzba->testovaci)
                    <flux:badge size="sm" color="zinc">testovací prostředí</flux:badge>
                @endif
            </flux:heading>
        </div>
    </dl>

    <flux:heading size="lg" level="2" class="mt-10 mb-4">Historie odeslání</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Odesláno</flux:table.column>
            <flux:table.column>Zaslání</flux:table.column>
            <flux:table.column>Výsledek</flux:table.column>
            <flux:table.column>UUID zprávy</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($trzba->zpravy as $zprava)
                <flux:table.row :key="$zprava->id">
                    <flux:table.cell>{{ $zprava->dat_odesl->format('j. n. Y H:i:s') }}</flux:table.cell>
                    <flux:table.cell>{{ $zprava->prvni_zaslani ? 'první' : 'opakované' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        @if ($zprava->jeUspesna())
                            <flux:badge size="sm" color="green">Přijato</flux:badge>

                            @foreach ($zprava->varovani ?? [] as $kod => $text)
                                <span wire:key="varovani-{{ $zprava->id }}-{{ $kod }}" class="block">Varování {{ $kod }}: {{ $text }}</span>
                            @endforeach
                        @else
                            <flux:badge size="sm" color="red">{{ $zprava->kod_chyby === null ? 'Chyba' : "Chyba {$zprava->kod_chyby}" }}</flux:badge>
                            <span class="block">{{ $zprava->chyba }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $zprava->uuid_zpravy ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">Tržba zatím nebyla odeslána.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
