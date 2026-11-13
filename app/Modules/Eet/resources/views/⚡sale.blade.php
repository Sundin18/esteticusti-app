<?php

use App\Modules\Eet\Actions\CancelSale;
use App\Modules\Eet\Actions\SaleCannotBeCancelled;
use App\Modules\Eet\Actions\SendSale;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Services\IncompleteSetup;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – detail tržby')] class extends Component {
    public Sale $sale;

    public string $cancellationAmount = '';

    public function mount(Sale $sale): void
    {
        $this->sale = $sale->load(['user', 'customer', 'messages', 'cancelledSale', 'cancellations']);
        $this->cancellationAmount = $this->canBeCancelled() ? str_replace('.', ',', $sale->refundableAmount()) : '';
    }

    /**
     * Zda se u tržby nabízí storno: potvrzená tržba, která sama není stornem a ještě z ní něco zbývá.
     */
    public function canBeCancelled(): bool
    {
        return $this->sale->isConfirmed()
            && ! $this->sale->isCancellation()
            && bccomp($this->sale->refundableAmount(), '0', 2) > 0;
    }

    /**
     * Stornuje tržbu nebo její část a přejde na detail vzniklého storna.
     */
    public function cancel(CancelSale $cancelSale): void
    {
        $this->authorize('eet.view');

        $this->cancellationAmount = str_replace([' ', ','], ['', '.'], $this->cancellationAmount);

        $this->validate([
            'cancellationAmount' => ['required', 'decimal:0,2', 'gt:0'],
        ], [
            'cancellationAmount.decimal' => 'Částka musí být číslo s nejvýše dvěma desetinnými místy.',
            'cancellationAmount.gt' => 'Částka musí být větší než nula.',
        ], [
            'cancellationAmount' => 'částka storna',
        ]);

        try {
            $cancellation = $cancelSale->handle(Auth::user(), $this->sale, $this->cancellationAmount);
        } catch (SaleCannotBeCancelled|IncompleteSetup $exception) {
            $this->addError('cancellationAmount', $exception->getMessage());

            return;
        }

        $this->redirectRoute('eet.sales.show', $cancellation, navigate: true);
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
            <flux:heading size="xl" level="1">{{ $sale->isCancellation() ? 'Storno' : 'Tržba' }} č. {{ $sale->receipt_number }}</flux:heading>
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

            @if ($this->canBeCancelled())
                <flux:modal.trigger name="cancel-sale">
                    <flux:button icon="arrow-uturn-left" data-test="cancel-sale">Storno</flux:button>
                </flux:modal.trigger>
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
            <flux:text>Poplatník a provozovna</flux:text>
            <flux:heading>{{ $sale->taxpayer_eic }}, {{ $sale->premises_name }}</flux:heading>
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

    @if ($sale->isCancellation())
        <flux:callout icon="arrow-uturn-left" class="mt-6 max-w-2xl">
            <flux:callout.heading>
                Storno tržby
                <flux:link :href="route('eet.sales.show', $sale->cancelledSale)" wire:navigate>č. {{ $sale->cancelledSale->receipt_number }}</flux:link>
                na {{ $sale->cancelledSale->formattedAmount() }}
            </flux:callout.heading>
        </flux:callout>
    @endif

    @if ($sale->cancellations->isNotEmpty())
        <flux:callout icon="arrow-uturn-left" class="mt-6 max-w-2xl">
            <flux:callout.heading>Storna této tržby</flux:callout.heading>
            <flux:callout.text>
                @foreach ($sale->cancellations as $cancellation)
                    <span wire:key="cancellation-{{ $cancellation->id }}" class="block">
                        <flux:link :href="route('eet.sales.show', $cancellation)" wire:navigate>č. {{ $cancellation->receipt_number }}</flux:link>
                        ze dne {{ $cancellation->sold_at->format('j. n. Y') }}: {{ $cancellation->formattedAmount() }}
                        ({{ mb_strtolower($cancellation->status->label()) }})
                    </span>
                @endforeach
                <span class="mt-2 block">Zbývá ke stornování: {{ Sale::formatAmount($sale->refundableAmount()) }}</span>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->canBeCancelled())
        <flux:modal name="cancel-sale" class="md:w-96">
            <form wire:submit="cancel" class="space-y-6">
                <div>
                    <flux:heading size="lg">Storno tržby č. {{ $sale->receipt_number }}</flux:heading>
                    <flux:text class="mt-2">
                        Do EET se odešle nová tržba se zápornou částkou a dnešním datem. Vrátit lze nejvýše {{ Sale::formatAmount($sale->refundableAmount()) }}.
                    </flux:text>
                </div>

                <flux:input wire:model="cancellationAmount" label="Vracená částka (Kč)" inputmode="decimal" required autocomplete="off" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Zrušit</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="danger" data-test="confirm-cancel-sale">Stornovat</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

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
