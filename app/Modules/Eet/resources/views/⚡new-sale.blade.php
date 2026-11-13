<?php

use App\Models\Customer;
use App\Modules\Eet\Actions\RecordSale;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Services\IncompleteSetup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – nová tržba')] class extends Component {
    public string $amount = '';
    public string $paymentMethod = '';
    public ?int $customerId = null;

    public ?int $saleId = null;

    /**
     * Tržba zaevidovaná posledním odesláním formuláře, zobrazená jako jeho výsledek.
     */
    #[Computed]
    public function sale(): ?Sale
    {
        return $this->saleId === null ? null : Sale::query()->with(['messages', 'customer'])->find($this->saleId);
    }

    /**
     * Klienti nabízení ve formuláři, řazení podle příjmení.
     *
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customers(): Collection
    {
        return Customer::query()->orderBy('lastname')->orderBy('firstname')->get();
    }

    /**
     * Zaeviduje tržbu a odešle ji do EET; částka může mít desetinnou čárku.
     */
    public function record(RecordSale $recordSale): void
    {
        $this->authorize('eet.view');

        $this->amount = str_replace([' ', ','], ['', '.'], $this->amount);

        $this->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'paymentMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'customerId' => ['nullable', 'integer', Rule::exists(Customer::class, 'id')->withoutTrashed()],
        ], [
            'amount.decimal' => 'Částka musí být číslo s nejvýše dvěma desetinnými místy.',
            'amount.gt' => 'Částka musí být větší než nula.',
            'paymentMethod.required' => 'Vyberte, jak zákazník platil.',
        ], [
            'amount' => 'částka',
            'paymentMethod' => 'způsob platby',
            'customerId' => 'klient',
        ]);

        try {
            $sale = $recordSale->handle(
                Auth::user(),
                $this->amount,
                PaymentMethod::from($this->paymentMethod),
                $this->customerId === null ? null : Customer::query()->find($this->customerId),
            );
        } catch (IncompleteSetup $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->saleId = $sale->id;
        $this->reset('amount', 'paymentMethod', 'customerId');
        unset($this->sale);
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">Nová tržba</flux:heading>
    <flux:subheading size="lg" class="mb-6">Zaevidování tržby do EET</flux:subheading>
    <flux:separator variant="subtle" />

    <div class="mt-6 max-w-lg space-y-6">
        @if ($this->sale !== null)
            @php $sale = $this->sale; @endphp

            <flux:callout
                :variant="match ($sale->status) { SaleStatus::Confirmed => 'success', SaleStatus::Rejected => 'danger', default => 'warning' }"
                :icon="match ($sale->status) { SaleStatus::Confirmed => 'check-circle', SaleStatus::Rejected => 'x-circle', default => 'clock' }"
            >
                <flux:callout.heading>
                    Tržba č. {{ $sale->receipt_number }} na {{ $sale->formattedAmount() }} ({{ mb_strtolower($sale->payment_method->label()) }}):
                    {{ match ($sale->status) {
                        SaleStatus::Confirmed => 'potvrzena',
                        SaleStatus::Rejected => 'zamítnuta systémem EET',
                        default => 'uložena, EET je nedostupná',
                    } }}
                </flux:callout.heading>

                <flux:callout.text>
                    @if ($sale->isConfirmed())
                        POK: {{ $sale->pok }}
                    @elseif ($sale->status === SaleStatus::Rejected)
                        {{ $sale->messages->last()?->error }}
                    @else
                        Tržba se odešle automaticky, jakmile bude služba dostupná.
                    @endif
                </flux:callout.text>

                <x-slot name="actions">
                    @if ($sale->status !== SaleStatus::Rejected)
                        <flux:button :href="route('eet.sales.receipt', $sale)" target="_blank" icon="printer" data-test="receipt">Účtenka</flux:button>
                    @endif

                    <flux:button :href="route('eet.sales.show', $sale)" variant="ghost" wire:navigate>Detail</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <form wire:submit="record" class="space-y-6">
            <flux:field>
                <flux:label>Částka (Kč)</flux:label>
                <flux:input wire:model="amount" inputmode="decimal" placeholder="0,00" autofocus autocomplete="off" />
                <flux:error name="amount" />
            </flux:field>

            <flux:radio.group wire:model="paymentMethod" label="Způsob platby" variant="segmented">
                @foreach (PaymentMethod::cases() as $option)
                    <flux:radio wire:key="payment-{{ $option->value }}" :value="$option->value" :label="$option->label()" />
                @endforeach
            </flux:radio.group>

            <flux:select wire:model="customerId" label="Klient" description="Nepovinné." variant="listbox" searchable clearable placeholder="Bez klienta">
                @foreach ($this->customers as $customer)
                    <flux:select.option wire:key="customer-{{ $customer->id }}" :value="$customer->id">{{ $customer->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button type="submit" variant="primary" data-test="record-sale">Zaevidovat tržbu</flux:button>
        </form>
    </div>
</section>
