<?php

namespace App\Modules\Eet\Exports;

use App\Modules\Eet\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Export tržeb do XLSX: jeden řádek na tržbu, od nejstarší.
 *
 * @implements WithMapping<Sale>
 */
class SalesExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping
{
    /**
     * @param  string  $status  Hodnota filtru stavu ze seznamu tržeb; prázdný řetězec znamená všechny.
     * @param  string|null  $dateFrom  První den období ve tvaru Y-m-d.
     * @param  string|null  $dateTo  Poslední den období ve tvaru Y-m-d.
     */
    public function __construct(
        public string $status = '',
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    /**
     * @return Builder<Sale>
     */
    public function query(): Builder
    {
        return Sale::query()
            ->with(['user', 'customer', 'cancelledSale'])
            ->matchingFilters($this->status, $this->dateFrom, $this->dateTo)
            ->orderBy('sold_at')
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Číslo účtenky',
            'Datum a čas',
            'Částka (Kč)',
            'Způsob platby',
            'Stav',
            'POK',
            'Storno k účtence č.',
            'Klient',
            'Zadal',
            'EIČ',
            'Provozovna',
        ];
    }

    /**
     * @param  Sale  $sale
     * @return list<string|float|null>
     */
    public function map($sale): array
    {
        return [
            $sale->receipt_number,
            Date::dateTimeToExcel($sale->sold_at->toDateTime()),
            (float) $sale->total_amount,
            $sale->payment_method->label(),
            $sale->status->label(),
            $sale->pok,
            $sale->cancelledSale?->receipt_number,
            $sale->customer?->name,
            $sale->user->name,
            $sale->taxpayer_eic,
            $sale->premises_name,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => 'd.m.yyyy hh:mm:ss',
            'C' => '#,##0.00',
            'G' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
