<?php

namespace App\Modules\Eet\tests\Actions;

use App\Modules\Eet\Actions\SendSale;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Models\Message;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\IncompleteSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SendSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_attempt_is_marked_as_repeated_and_confirms_the_sale(): void
    {
        $client = new FakeEetClient;
        $sale = Sale::factory()->awaitingRetry()->create(['receipt_number' => '7', 'total_amount' => '250.00']);
        Message::factory()->for($sale)->withError(EetException::CODE_TEMPORARY, 'Spojení s EET selhalo')->create();

        $sale = (new SendSale($client))->handle($sale);

        $this->assertSame(SaleStatus::Confirmed, $sale->status);
        $this->assertNotNull($sale->pok);
        $this->assertFalse($client->sent[0]['firstAttempt']);
        $this->assertSame('7', $client->sent[0]['sale']->receiptNumber);
        $this->assertSame('250.00', $client->sent[0]['sale']->totalAmount);
        $this->assertSame($sale->sold_at->getTimestamp(), $client->sent[0]['sale']->soldAt->getTimestamp());
        $this->assertCount(2, $sale->messages);
        $this->assertFalse($sale->messages->last()->is_first_attempt);
    }

    public function test_confirmed_sale_is_not_sent_again(): void
    {
        $client = new FakeEetClient;
        $sale = Sale::factory()->confirmed()->create();
        $pok = $sale->pok;

        $sale = (new SendSale($client))->handle($sale);

        $this->assertSame([], $client->sent);
        $this->assertSame($pok, $sale->pok);
    }

    public function test_sale_confirmed_by_another_process_in_the_meantime_is_not_sent_again(): void
    {
        $client = new FakeEetClient;
        $sale = Sale::factory()->awaitingRetry()->create();
        Sale::query()->whereKey($sale->id)->update(['status' => SaleStatus::Confirmed, 'pok' => 'pok-z-jineho-procesu']);

        $sale = (new SendSale($client))->handle($sale);

        $this->assertSame([], $client->sent);
        $this->assertSame('pok-z-jineho-procesu', $sale->pok);
    }

    public function test_sale_being_sent_by_another_process_is_skipped(): void
    {
        $client = new FakeEetClient;
        $sale = Sale::factory()->awaitingRetry()->create();
        Cache::lock("eet:sale:{$sale->id}", 120)->get();

        (new SendSale($client))->handle($sale);

        $this->assertSame([], $client->sent);
        $this->assertDatabaseCount('eet_messages', 0);
    }

    public function test_rejection_after_a_repeated_attempt_marks_the_sale_as_rejected(): void
    {
        $client = (new FakeEetClient)->respondWith(new EetException('Neplatny podpis', 4, '<pozadavek/>', '<odpoved/>', 'b3a09b52-7c87-4014-a496-4c7a53cf9125'));
        $sale = Sale::factory()->awaitingRetry()->create();

        $sale = (new SendSale($client))->handle($sale);

        $message = $sale->messages()->sole();

        $this->assertSame(SaleStatus::Rejected, $sale->status);
        $this->assertSame(4, $message->error_code);
        $this->assertSame('b3a09b52-7c87-4014-a496-4c7a53cf9125', $message->message_uuid);
        $this->assertFalse($message->isSuccessful());
    }

    public function test_missing_settings_leave_the_sale_unchanged_and_free_for_the_next_attempt(): void
    {
        $client = (new FakeEetClient)->respondWith(IncompleteSetup::missingCertificate());
        $sale = Sale::factory()->awaitingRetry()->create();

        try {
            (new SendSale($client))->handle($sale);
            $this->fail('A missing certificate should have thrown.');
        } catch (IncompleteSetup) {
            $this->assertSame(SaleStatus::AwaitingRetry, $sale->refresh()->status);
            $this->assertDatabaseCount('eet_messages', 0);
        }

        $this->assertSame(SaleStatus::Confirmed, (new SendSale($client))->handle($sale)->status);
    }
}
