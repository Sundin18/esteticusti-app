<?php

namespace App\Modules\Eet\Services;

use Illuminate\Support\Str;

/**
 * In-memory client for tests: answers from a queue and records what was sent.
 */
class FakeEetClient implements EetClient
{
    /**
     * @var list<array{trzba: UdajeTrzby, prvniZaslani: bool, overeni: bool}>
     */
    public array $odeslane = [];

    /**
     * @var list<Odpoved|EetChyba|NeuplneNastaveni>
     */
    private array $fronta = [];

    /**
     * Queue the next answer; an exception is thrown instead of returned.
     */
    public function odpovi(Odpoved|EetChyba|NeuplneNastaveni $odpoved): static
    {
        $this->fronta[] = $odpoved;

        return $this;
    }

    /**
     * Send a sale; with an empty queue it succeeds with a test POK, or without one in verification mode.
     */
    public function odesli(UdajeTrzby $trzba, bool $prvniZaslani = true, bool $overeni = false): Odpoved
    {
        $this->odeslane[] = ['trzba' => $trzba, 'prvniZaslani' => $prvniZaslani, 'overeni' => $overeni];

        $odpoved = array_shift($this->fronta) ?? new Odpoved(
            uuidZpravy: (string) Str::uuid(),
            pok: $overeni ? null : Str::uuid().'-ff',
            test: true,
            varovani: [],
            xmlPozadavku: '<Trzba/>',
            xmlOdpovedi: '<Odpoved/>',
        );

        if (! $odpoved instanceof Odpoved) {
            throw $odpoved;
        }

        return $odpoved;
    }
}
