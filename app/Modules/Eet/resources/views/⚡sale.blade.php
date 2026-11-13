<?php

use App\Modules\Eet\Actions\SendSale;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Services\IncompleteSetup;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – detail tržby')] class extends Component {
    public Sale $sale;

    public function mount(Sale $sale): void
    {
        $this->sale = $sale->load(['user', 'customer', 'messages']);
    }

    /**
     * Provede další pokus o odeslání tržby, která ještě nebyla potvrzena.
     */
    public function resend(SendSale $sendSale): void
    {
        $this->authorize('eet.view');

        try {
            $sendSale->handle($this->sale);
        } catch (IncompleteSetup $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->sale->load('messages');

        if ($this->sale->isConfirmed()) {
            Flux::toast(variant: 'success', text: 'Tržba byla potvrzena.');
        } else {
            Flux::toast(variant: 'danger', text: 'Tržbu se nepodařilo odeslat.');
        }
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Tržba č. {{ $sale->receipt_number }}</flux:heading>
            <flux:subheading size="lg">
                <flux:badge size="sm" :color="$sale->status->color()">{{ $sale->status->label() }}</flux:badge>
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            @if (! $sale->isConfirmed())
                <flux:button wire:click="resend" icon="arrow-path" data-test="resend">Odeslat znovu</flux:button>
            @endif

            @if ($sale->status !== SaleStatus::Rejected)
                <flux:button :href="route('eet.sales.receipt', $sale)" target="_blank" icon="printer" data-test="receipt">Účtenka</flux:button>
            @endif

            <flux:button :href="route('eet.sales.index')" variant="ghost" wire:navigate>Zpět na tržby</flux:button>
        </div>
    </div>

    <flux:separator variant="subtle" class="my-6" />

    <dl class="grid max-w-2xl gap-x-6 gap-y-4 sm:grid-cols-2">
        <div>
            <flux:text>Částka</flux:text>
            <flux:heading size="lg">{{ $sale->formattedAmount() }}</flux:heading>
        </div>
        <div>
            <flux:text>Datum a čas tržby</flux:text>
            <flux:heading size="lg">{{ $sale->sold_at->format('j. n. Y H:i:s') }}</flux:heading>
        </div>
        <div>
            <flux:text>Způsob platby</flux:text>
            <flux:heading>{{ $sale->payment_method->label() }}</flux:heading>
        </div>
        <div>
            <flux:text>Klient</flux:text>
            <flux:heading>{{ $sale->customer->name ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text>Zadal</flux:text>
            <flux:heading>{{ $sale->user->name }}</flux:heading>
        </div>
        <div>
            <flux:text>POK</flux:text>
            <flux:heading class="break-all">
                {{ $sale->pok ?? '—' }}
                @if ($sale->isConfirmed() && $sale->is_test)
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
            @forelse ($sale->messages as $message)
                <flux:table.row :key="$message->id">
                    <flux:table.cell>{{ $message->sent_at->format('j. n. Y H:i:s') }}</flux:table.cell>
                    <flux:table.cell>{{ $message->is_first_attempt ? 'první' : 'opakované' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        @if ($message->isSuccessful())
                            <flux:badge size="sm" color="green">Přijato</flux:badge>

                            @foreach ($message->warnings ?? [] as $code => $text)
                                <span wire:key="warning-{{ $message->id }}-{{ $code }}" class="block">Varování {{ $code }}: {{ $text }}</span>
                            @endforeach
                        @else
                            <flux:badge size="sm" color="red">{{ $message->error_code === null ? 'Chyba' : "Chyba {$message->error_code}" }}</flux:badge>
                            <span class="block">{{ $message->error }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $message->message_uuid ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">Tržba zatím nebyla odeslána.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
