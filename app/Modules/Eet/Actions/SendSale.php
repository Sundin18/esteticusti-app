<?php

namespace App\Modules\Eet\Actions;

use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\IncompleteSetup;
use App\Modules\Eet\Services\SaleData;
use Illuminate\Support\Facades\Cache;

class SendSale
{
    public function __construct(private EetClient $client) {}

    /**
     * Provede jeden pokus o odeslání tržby, zaznamená ho a převede tržbu do výsledného stavu.
     *
     * Potvrzená tržba, nebo tržba, kterou právě odesílá jiný proces, se vrátí beze změny.
     *
     * @throws IncompleteSetup Když chybí nastavení nebo certifikát; nic se nezaznamená.
     */
    public function handle(Sale $sale): Sale
    {
        $lock = Cache::lock("eet:sale:{$sale->id}", 120);

        if (! $lock->get()) {
            return $sale;
        }

        try {
            $sale->refresh();

            if ($sale->isConfirmed()) {
                return $sale;
            }

            $this->send($sale);
        } finally {
            $lock->release();
        }

        return $sale;
    }

    protected function send(Sale $sale): void
    {
        $firstAttempt = $sale->messages()->doesntExist();
        $sentAt = now();

        try {
            $response = $this->client->send(
                new SaleData($sale->receipt_number, $sale->sold_at, $sale->total_amount),
                firstAttempt: $firstAttempt,
            );
        } catch (EetException $error) {
            $sale->messages()->create([
                'message_uuid' => $error->messageUuid,
                'sent_at' => $sentAt,
                'is_first_attempt' => $firstAttempt,
                'request_xml' => $error->requestXml,
                'response_xml' => $error->responseXml,
                'error_code' => $error->errorCode,
                'error' => $error->getMessage(),
            ]);

            $sale->update(['status' => $error->isTemporary() ? SaleStatus::AwaitingRetry : SaleStatus::Rejected]);

            return;
        }

        $sale->messages()->create([
            'message_uuid' => $response->messageUuid,
            'sent_at' => $sentAt,
            'is_first_attempt' => $firstAttempt,
            'request_xml' => $response->requestXml,
            'response_xml' => $response->responseXml,
            'warnings' => $response->warnings ?: null,
        ]);

        $sale->update([
            'status' => SaleStatus::Confirmed,
            'pok' => $response->pok,
            'is_test' => $response->isTest,
        ]);
    }
}
