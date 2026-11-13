<?php

namespace App\Modules\Eet\Actions;

use App\Models\User;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Jobs\OdesliTrzbu;
use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Services\NeuplneNastaveni;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EvidujTrzbu
{
    public function __construct(private PosliTrzbu $posliTrzbu) {}

    /**
     * Record a sale under the next receipt number and try to send it right away.
     *
     * When the service cannot be reached the sale is queued for further attempts.
     *
     * @param  string  $celkTrzba  Amount in CZK as a decimal string, e.g. "1500.00".
     * @param  Model|null  $zdroj  Record in another module the sale belongs to.
     *
     * @throws NeuplneNastaveni When the settings or the certificate are missing; no receipt number is used.
     */
    public function handle(User $user, string $celkTrzba, ?Model $zdroj = null): Trzba
    {
        $trzba = DB::transaction(function () use ($user, $celkTrzba, $zdroj): Trzba {
            $nastaveni = Nastaveni::query()->lockForUpdate()->first() ?? throw NeuplneNastaveni::chybiNastaveni();

            if (Certifikat::aktivni() === null) {
                throw NeuplneNastaveni::chybiCertifikat();
            }

            $nastaveni->posledni_porad_cis++;
            $nastaveni->save();

            $trzba = new Trzba([
                'porad_cis' => (string) $nastaveni->posledni_porad_cis,
                'dat_trzby' => now(),
                'celk_trzba' => $celkTrzba,
                'user_id' => $user->id,
            ]);
            $trzba->zdroj()->associate($zdroj);
            $trzba->save();

            return $trzba;
        });

        $trzba = $this->posliTrzbu->handle($trzba);

        if ($trzba->stav === StavTrzby::KOpakovani) {
            OdesliTrzbu::dispatch($trzba)->delay(now()->addMinute());
        }

        return $trzba;
    }
}
