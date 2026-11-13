<?php

use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('EET – tržby')] class extends Component {
    use WithPagination;

    public string $stav = '';
    public ?string $od = null;
    public ?string $do = null;

    public string $sortBy = 'dat_trzby';
    public string $sortDirection = 'desc';

    /**
     * Sort by the given column, flipping the direction when it is already the sort column.
     */
    public function sort(string $column): void
    {
        if (! in_array($column, ['id', 'dat_trzby', 'celk_trzba'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    /**
     * Return to the first page whenever a filter changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['stav', 'od', 'do'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Trzba>
     */
    #[Computed]
    public function trzby(): LengthAwarePaginator
    {
        return Trzba::query()
            ->with('user')
            ->when(StavTrzby::tryFrom($this->stav), fn (Builder $query, StavTrzby $stav) => $query->where('stav', $stav))
            ->when($this->od, fn (Builder $query, string $od) => $query->whereDate('dat_trzby', '>=', $od))
            ->when($this->do, fn (Builder $query, string $do) => $query->whereDate('dat_trzby', '<=', $do))
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

        <flux:button :href="route('eet.trzby.nova')" variant="primary" icon="plus" wire:navigate>Nová tržba</flux:button>
    </div>

    <flux:separator variant="subtle" class="my-6" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <flux:select wire:model.live="stav" label="Stav">
            <flux:select.option value="">Všechny stavy</flux:select.option>
            @foreach (StavTrzby::cases() as $moznost)
                <flux:select.option wire:key="stav-{{ $moznost->value }}" :value="$moznost->value">{{ $moznost->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:date-picker wire:model.live="od" label="Od" clearable />

        <flux:date-picker wire:model.live="do" label="Do" clearable />
    </div>

    <flux:table :paginate="$this->trzby">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">Číslo</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'dat_trzby'" :direction="$sortDirection" wire:click="sort('dat_trzby')">Datum a čas</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'celk_trzba'" :direction="$sortDirection" wire:click="sort('celk_trzba')" align="end">Částka</flux:table.column>
            <flux:table.column>Stav</flux:table.column>
            <flux:table.column>Zadal</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->trzby as $trzba)
                <flux:table.row :key="$trzba->id">
                    <flux:table.cell variant="strong">{{ $trzba->porad_cis }}</flux:table.cell>
                    <flux:table.cell>{{ $trzba->dat_trzby->format('j. n. Y H:i') }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $trzba->formatovanaCastka() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$trzba->stav->color()" inset="top bottom">{{ $trzba->stav->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $trzba->user->name }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button :href="route('eet.trzby.detail', $trzba)" size="sm" variant="ghost" inset="top bottom" wire:navigate>Detail</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">Žádné tržby neodpovídají filtru.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
