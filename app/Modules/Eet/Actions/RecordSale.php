<?php

namespace App\Modules\Eet\Actions;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Jobs\ResendSale;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\IncompleteSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RecordSale
{
    public function __construct(private SendSale $sendSale) {}

    /**
     * Zaeviduje tržbu pod dalším pořadovým číslem a hned se ji pokusí odeslat.
     *
     * Když je služba nedostupná, tržba se zařadí do fronty k dalším pokusům. Údaje poplatníka
     * a provozovny se k tržbě zkopírují, aby se pozdější změnou nastavení nezměnily.
     *
     * @param  string  $totalAmount  Částka v Kč jako desetinný řetězec, např. "1500.00"; u storna záporná.
     * @param  Customer|null  $customer  Klient, kterému byla tržba účtována, pokud je znám.
     * @param  Model|null  $source  Záznam v jiném modulu, ke kterému tržba patří.
     * @param  Sale|null  $cancelledSale  Původní tržba, pokud je tato tržba jejím stornem.
     *
     * @throws IncompleteSetup Když chybí nastavení nebo certifikát; žádné pořadové číslo se nespotřebuje.
     */
    public function handle(
        User $user,
        string $totalAmount,
        PaymentMethod $paymentMethod,
        ?Customer $customer = null,
        ?Model $source = null,
        ?Sale $cancelledSale = null,
    ): Sale {
        $sale = DB::transaction(function () use ($user, $totalAmount, $paymentMethod, $customer, $source, $cancelledSale): Sale {
            $settings = Settings::query()->lockForUpdate()->first() ?? throw IncompleteSetup::missingSettings();

            if (Certificate::active() === null) {
                throw IncompleteSetup::missingCertificate();
            }

            $settings->last_receipt_number++;
            $settings->save();

            $sale = new Sale([
                'receipt_number' => (string) $settings->last_receipt_number,
                'taxpayer_eic' => $settings->taxpayer_eic,
                'premises_name' => $settings->premises_name,
                'premises_address' => $settings->premises_address,
                'unit_id' => $settings->unit_id,
                'register_id' => $settings->register_id,
                'sold_at' => now(),
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'cancelled_sale_id' => $cancelledSale?->id,
            ]);
            $sale->source()->associate($source);
            $sale->save();

            return $sale;
        });

        $sale = $this->sendSale->handle($sale);

        if ($sale->status === SaleStatus::AwaitingRetry) {
            ResendSale::dispatch($sale)->delay(now()->addMinute());
        }

        return $sale;
    }
}
