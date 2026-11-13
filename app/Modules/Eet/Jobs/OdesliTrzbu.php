<?php

namespace App\Modules\Eet\Jobs;

use App\Modules\Eet\Actions\PosliTrzbu;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OdesliTrzbu implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before retrying after an unexpected exception.
     */
    public int $backoff = 300;

    /**
     * Seconds to wait after each failed delivery; the last value repeats.
     *
     * @var list<int>
     */
    private const array ODSTUPY = [60, 300, 900, 1800, 3600];

    public function __construct(public Trzba $trzba) {}

    /**
     * Keep trying for the 48 hours the law allows for sending a sale after an outage.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(48);
    }

    /**
     * Send the sale again and come back later while the service stays unavailable.
     */
    public function handle(PosliTrzbu $posliTrzbu): void
    {
        $trzba = $posliTrzbu->handle($this->trzba);

        if ($trzba->stav === StavTrzby::KOpakovani) {
            $this->release(self::ODSTUPY[$this->attempts() - 1] ?? self::ODSTUPY[array_key_last(self::ODSTUPY)]);
        }
    }
}
