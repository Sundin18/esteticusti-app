<?php

namespace App\Modules\Eet\Jobs;

use App\Modules\Eet\Actions\SendSale;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Sale;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResendSale implements ShouldQueue
{
    use Queueable;

    /**
     * Počet sekund před dalším pokusem po neočekávané výjimce.
     */
    public int $backoff = 300;

    /**
     * Počet sekund čekání po každém neúspěšném doručení; poslední hodnota se opakuje.
     *
     * @var list<int>
     */
    private const array DELAYS = [60, 300, 900, 1800, 3600];

    public function __construct(public Sale $sale) {}

    /**
     * Zkouší to 48 hodin, které zákon dává na odeslání tržby po výpadku.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(48);
    }

    /**
     * Odešle tržbu znovu a vrátí se později, dokud je služba nedostupná.
     */
    public function handle(SendSale $sendSale): void
    {
        $sale = $sendSale->handle($this->sale);

        if ($sale->status === SaleStatus::AwaitingRetry) {
            $this->release(self::DELAYS[$this->attempts() - 1] ?? self::DELAYS[array_key_last(self::DELAYS)]);
        }
    }
}
