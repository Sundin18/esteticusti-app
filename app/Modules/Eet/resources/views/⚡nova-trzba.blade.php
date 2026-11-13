<?php

use App\Modules\Eet\Actions\EvidujTrzbu;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Services\NeuplneNastaveni;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – nová tržba')] class extends Component {
    public string $castka = '';

    public ?int $trzbaId = null;

    /**
     * The sale recorded by the last submission, shown as its result.
     */
    #[Computed]
    public function trzba(): ?Trzba
    {
        return $this->trzbaId === null ? null : Trzba::query()->with('zpravy')->find($this->trzbaId);
    }

    /**
     * Record the sale and send it to EET; the amount may use a decimal comma.
     */
    public function zaeviduj(EvidujTrzbu $evidujTrzbu): void
    {
        $this->authorize('eet.view');

        $this->castka = str_replace([' ', ','], ['', '.'], $this->castka);

        $this->validate([
            'castka' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
        ], [
            'castka.decimal' => 'Částka musí být číslo s nejvýše dvěma desetinnými místy.',
            'castka.gt' => 'Částka musí být větší než nula.',
        ], [
            'castka' => 'částka',
        ]);

        try {
            $trzba = $evidujTrzbu->handle(Auth::user(), $this->castka);
        } catch (NeuplneNastaveni $vyjimka) {
            $this->addError('castka', $vyjimka->getMessage());

            return;
        }

        $this->trzbaId = $trzba->id;
        $this->reset('castka');
        unset($this->trzba);
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">Nová tržba</flux:heading>
    <flux:subheading size="lg" class="mb-6">Zaevidování tržby do EET</flux:subheading>
    <flux:separator variant="subtle" />

    <div class="mt-6 max-w-lg space-y-6">
        @if ($this->trzba !== null)
            @php $trzba = $this->trzba; @endphp

            <flux:callout
                :variant="match ($trzba->stav) { StavTrzby::Potvrzena => 'success', StavTrzby::Zamitnuta => 'danger', default => 'warning' }"
                :icon="match ($trzba->stav) { StavTrzby::Potvrzena => 'check-circle', StavTrzby::Zamitnuta => 'x-circle', default => 'clock' }"
            >
                <flux:callout.heading>
                    Tržba č. {{ $trzba->porad_cis }} na {{ $trzba->formatovanaCastka() }}:
                    {{ match ($trzba->stav) {
                        StavTrzby::Potvrzena => 'potvrzena',
                        StavTrzby::Zamitnuta => 'zamítnuta systémem EET',
                        default => 'uložena, EET je nedostupná',
                    } }}
                </flux:callout.heading>

                <flux:callout.text>
                    @if ($trzba->jePotvrzena())
                        POK: {{ $trzba->pok }}
                    @elseif ($trzba->stav === StavTrzby::Zamitnuta)
                        {{ $trzba->zpravy->last()?->chyba }}
                    @else
                        Tržba se odešle automaticky, jakmile bude služba dostupná.
                    @endif
                </flux:callout.text>

                <x-slot name="actions">
                    @if ($trzba->stav !== StavTrzby::Zamitnuta)
                        <flux:button :href="route('eet.trzby.uctenka', $trzba)" target="_blank" icon="printer" data-test="uctenka">Účtenka</flux:button>
                    @endif

                    <flux:button :href="route('eet.trzby.detail', $trzba)" variant="ghost" wire:navigate>Detail</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <form wire:submit="zaeviduj" class="space-y-6">
            <flux:input wire:model="castka" label="Částka (Kč)" inputmode="decimal" placeholder="0,00" required autofocus autocomplete="off" />

            <flux:button type="submit" variant="primary" data-test="zaevidovat">Zaevidovat tržbu</flux:button>
        </form>
    </div>
</section>
