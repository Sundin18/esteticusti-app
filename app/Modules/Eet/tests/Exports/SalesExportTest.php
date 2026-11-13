<?php

namespace App\Modules\Eet\tests\Exports;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Exports\SalesExport;
use App\Modules\Eet\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SalesExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_the_sales_matching_the_filter_from_the_oldest(): void
    {
        Sale::factory()->confirmed()->create(['receipt_number' => 'B', 'sold_at' => '2027-01-11 10:00:00']);
        Sale::factory()->confirmed()->create(['receipt_number' => 'A', 'sold_at' => '2027-01-10 10:00:00']);
        Sale::factory()->awaitingRetry()->create(['receipt_number' => 'UNCONFIRMED', 'sold_at' => '2027-01-10 12:00:00']);
        Sale::factory()->confirmed()->create(['receipt_number' => 'TOO-LATE', 'sold_at' => '2027-01-12 00:00:00']);

        $export = new SalesExport('confirmed', '2027-01-10', '2027-01-11');

        $this->assertSame(['A', 'B'], $export->query()->pluck('receipt_number')->all());
    }

    public function test_written_file_has_a_row_per_sale_with_readable_values(): void
    {
        $user = User::factory()->create(['firstname' => 'Jana', 'lastname' => 'Nováková']);
        $original = Sale::factory()->confirmed()->for($user)
            ->for(Customer::factory()->state(['firstname' => 'Petra', 'lastname' => 'Malá']))
            ->create([
                'receipt_number' => '42',
                'sold_at' => '2027-01-09 04:25:28',
                'total_amount' => '1500.50',
                'payment_method' => PaymentMethod::Card,
                'taxpayer_eic' => 'CZ683555118',
                'premises_name' => 'Estetic Ústí',
            ]);
        Sale::factory()->cancelling($original)->for($user)->create(['receipt_number' => '43', 'sold_at' => '2027-01-10 09:00:00']);

        $rows = $this->writtenRows(new SalesExport);

        $this->assertSame((new SalesExport)->headings(), $rows[0]);
        $this->assertSame(
            ['42', '9.1.2027 04:25:28', '1,500.50', 'Kartou', 'Potvrzena', $original->pok, null, 'Petra Malá', 'Jana Nováková', 'CZ683555118', 'Estetic Ústí'],
            $rows[1],
        );
        $this->assertSame('43', $rows[2][0]);
        $this->assertSame('-1,500.50', $rows[2][2]);
        $this->assertSame('42', $rows[2][6]);
        $this->assertCount(3, $rows);
    }

    /**
     * @return list<array<mixed>>
     */
    private function writtenRows(SalesExport $export): array
    {
        $path = tempnam(sys_get_temp_dir(), 'eet-export-');
        file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));

        try {
            return array_values(IOFactory::load($path)->getActiveSheet()->toArray());
        } finally {
            @unlink($path);
        }
    }
}
