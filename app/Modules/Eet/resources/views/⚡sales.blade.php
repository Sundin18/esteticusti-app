<?php

use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Exports\SalesExport;
use App\Modules\Eet\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Title('EET – tržby')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    public string $sortBy = 'sold_at';
    public string $sortDirection = 'desc';

    /**
     * Seřadí podle zadaného sloupce; pokud se podle něj už řadí, obrátí směr.
     */
    public function sort(string $column): void
    {
        if (! in_array($column, ['id', 'sold_at', 'total_amount'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    /**
     * Při změně filtru se vrátí na první stránku.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Stáhne tržby odpovídající aktuálnímu filtru jako soubor XLSX.
     */
    public function export(): BinaryFileResponse
    {
        $this->authorize('eet.view');

        return Excel::download(
            new SalesExport($this->status, $this->dateFrom, $this->dateTo),
            'trzby-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * @return LengthAwarePaginator<int, Sale>
     */
    #[Computed]
    public function sales(): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['user', 'customer'])
            ->matchingFilters($this->status, $this->dateFrom, $this->dateTo)
            ->orderBy($this->sortBy, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate(25);
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Tržby</flux:heading>
            <flux:subheading size="lg">Všechny zaevidované tržby</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button wire:click="export" icon="arrow-down-tray" data-test="export">Export do XLSX</flux:button>

            <flux:button :href="route('eet.sales.create')" variant="primary" icon="plus" wire:navigate>Nová tržba</flux:button>
        </div>
    </div>

    <flux:separator variant="subtle" class="my-6" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <flux:select wire:model.live="status" label="Stav">
            <flux:select.option value="">Všechny stavy</flux:select.option>
            <flux:select.option :value="Sale::FILTER_UNCONFIRMED">Všechny nepotvrzené</flux:select.option>
            @foreach (SaleStatus::cases() as $option)
                <flux:select.option wire:key="status-{{ $option->value }}" :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:date-picker wire:model.live="dateFrom" label="Od" clearable />

        <flux:date-picker wire:model.live="dateTo" label="Do" clearable />
    </div>

    <flux:table :paginate="$this->sales">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">Číslo</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'sold_at'" :direction="$sortDirection" wire:click="sort('sold_at')">Datum a čas</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'total_amount'" :direction="$sortDirection" wire:click="sort('total_amount')" align="end">Částka</flux:table.column>
            <flux:table.column>Platba</flux:table.column>
            <flux:table.column>Klient</flux:table.column>
            <flux:table.column>Stav</flux:table.column>
            <flux:table.column>Zadal</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->sales as $sale)
                <flux:table.row :key="$sale->id">
                    <flux:table.cell variant="strong">
                        {{ $sale->receipt_number }}
                        @if ($sale->isCancellation())
                            <flux:badge size="sm" color="zinc" inset="top bottom">Storno</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $sale->sold_at->format('j. n. Y H:i') }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $sale->formattedAmount() }}</flux:table.cell>
                    <flux:table.cell>{{ $sale->payment_method->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $sale->customer->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$sale->status->color()" inset="top bottom">{{ $sale->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $sale->user->name }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button :href="route('eet.sales.show', $sale)" size="sm" variant="ghost" inset="top bottom" wire:navigate>Detail</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">Žádné tržby neodpovídají filtru.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
