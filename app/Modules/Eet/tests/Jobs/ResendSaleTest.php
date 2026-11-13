<?php

namespace App\Modules\Eet\tests\Jobs;

use App\Modules\Eet\Actions\SendSale;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Jobs\ResendSale;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\FakeEetClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResendSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_sale_finishes_the_job(): void
    {
        $sale = Sale::factory()->awaitingRetry()->create();
        $job = (new ResendSale($sale))->withFakeQueueInteractions();

        $job->handle(new SendSale(new FakeEetClient));

        $job->assertNotReleased();
        $this->assertSame(SaleStatus::Confirmed, $sale->refresh()->status);
    }

    public function test_job_comes_back_in_a_minute_while_the_service_is_unavailable(): void
    {
        $sale = Sale::factory()->awaitingRetry()->create();
        $client = (new FakeEetClient)->respondWith(new EetException('Spojení s EET selhalo', EetException::CODE_TEMPORARY));
        $job = (new ResendSale($sale))->withFakeQueueInteractions();

        $job->handle(new SendSale($client));

        $job->assertReleased(delay: 60);
        $this->assertSame(SaleStatus::AwaitingRetry, $sale->refresh()->status);
    }

    public function test_rejected_sale_is_not_retried(): void
    {
        $sale = Sale::factory()->awaitingRetry()->create();
        $client = (new FakeEetClient)->respondWith(new EetException('Neplatny EIC', 6));
        $job = (new ResendSale($sale))->withFakeQueueInteractions();

        $job->handle(new SendSale($client));

        $job->assertNotReleased();
        $this->assertSame(SaleStatus::Rejected, $sale->refresh()->status);
    }

    public function test_job_keeps_trying_for_48_hours(): void
    {
        $this->freezeSecond();

        $job = new ResendSale(Sale::factory()->awaitingRetry()->create());

        $this->assertTrue(now()->addHours(48)->equalTo($job->retryUntil()));
    }
}
