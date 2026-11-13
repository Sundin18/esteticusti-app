<?php

namespace App\Modules\Eet\Services;

use Illuminate\Support\Str;

/**
 * Klient v paměti pro testy: odpovídá z fronty a zaznamenává, co bylo odesláno.
 */
class FakeEetClient implements EetClient
{
    /**
     * @var list<array{sale: SaleData, firstAttempt: bool, verifyOnly: bool}>
     */
    public array $sent = [];

    /**
     * @var list<EetResponse|EetException|IncompleteSetup>
     */
    private array $queue = [];

    /**
     * Zařadí do fronty další odpověď; výjimka se místo vrácení vyhodí.
     */
    public function respondWith(EetResponse|EetException|IncompleteSetup $response): static
    {
        $this->queue[] = $response;

        return $this;
    }

    /**
     * Odešle tržbu; s prázdnou frontou uspěje s testovacím POK, v ověřovacím režimu bez něj.
     */
    public function send(SaleData $sale, bool $firstAttempt = true, bool $verifyOnly = false): EetResponse
    {
        $this->sent[] = ['sale' => $sale, 'firstAttempt' => $firstAttempt, 'verifyOnly' => $verifyOnly];

        $response = array_shift($this->queue) ?? new EetResponse(
            messageUuid: (string) Str::uuid(),
            pok: $verifyOnly ? null : Str::uuid().'-ff',
            isTest: true,
            warnings: [],
            requestXml: '<Trzba/>',
            responseXml: '<Odpoved/>',
        );

        if (! $response instanceof EetResponse) {
            throw $response;
        }

        return $response;
    }
}
