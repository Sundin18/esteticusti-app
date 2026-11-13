<?php

namespace App\Modules\Eet\Actions;

use App\Models\User;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Services\IncompleteSetup;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class CancelSale
{
    public function __construct(private RecordSale $recordSale) {}

    /**
     * Stornuje tržbu nebo její část: zaeviduje novou tržbu se zápornou částkou a vlastním pořadovým číslem.
     *
     * Podle metodiky EET se storno eviduje jako záporná tržba s datem vrácení peněz; služba ho
     * s původní tržbou nijak nepáruje, vazbu si drží jen aplikace.
     *
     * @param  string|null  $amount  Vracená částka v Kč jako kladný desetinný řetězec; null stornuje vše, co ještě zbývá.
     *
     * @throws SaleCannotBeCancelled Když tržbu stornovat nelze nebo částka přesahuje to, co z ní zbývá.
     * @throws IncompleteSetup Když chybí nastavení nebo certifikát.
     */
    public function handle(User $user, Sale $sale, ?string $amount = null): Sale
    {
        try {
            return Cache::lock("eet:cancel:{$sale->id}", 30)->block(5, function () use ($user, $sale, $amount): Sale {
                $sale->refresh();
                $amount = $this->validatedAmount($sale, $amount);

                return $this->recordSale->handle(
                    $user,
                    bcsub('0', $amount, 2),
                    $sale->payment_method,
                    $sale->customer,
                    cancelledSale: $sale,
                );
            });
        } catch (LockTimeoutException) {
            throw new SaleCannotBeCancelled('Tržbu právě stornuje někdo jiný. Zkuste to za chvíli znovu.');
        }
    }

    /**
     * Ověří, že tržbu lze stornovat, a vrátí částku, která se má vrátit.
     *
     * @return numeric-string
     */
    protected function validatedAmount(Sale $sale, ?string $amount): string
    {
        if ($sale->isCancellation()) {
            throw new SaleCannotBeCancelled('Storno nelze stornovat.');
        }

        if (! $sale->isConfirmed()) {
            throw new SaleCannotBeCancelled('Stornovat lze jen tržbu potvrzenou systémem EET.');
        }

        $refundable = $sale->refundableAmount();

        if (bccomp($refundable, '0', 2) <= 0) {
            throw new SaleCannotBeCancelled('Tržba už byla celá stornována.');
        }

        $amount ??= $refundable;

        if (! is_numeric($amount) || bccomp($amount, '0', 2) <= 0 || bccomp($amount, $refundable, 2) > 0) {
            throw new SaleCannotBeCancelled('Částka storna musí být větší než nula a nejvýše '.Sale::formatAmount($refundable).'.');
        }

        return $amount;
    }
}
