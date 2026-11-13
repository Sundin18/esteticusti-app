<?php

namespace App\Modules\Eet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    /**
     * Šířka 80mm účtenkové role a výška účtenky v bodech.
     */
    private const array RECEIPT_SIZE = [0, 0, 226.77, 340];

    /**
     * Vykreslí účtenku jako PDF pro tisk; nic se neukládá. Zamítnutá tržba účtenku nemá.
     * Hlavička účtenky vychází z údajů uložených u tržby, ne z aktuálního nastavení.
     */
    public function __invoke(Sale $sale): Response
    {
        abort_if($sale->status === SaleStatus::Rejected, 404);

        return Pdf::setOption('isFontSubsettingEnabled', true)
            ->loadView('eet::receipt', ['sale' => $sale])
            ->setPaper(self::RECEIPT_SIZE)
            ->stream("uctenka-{$sale->id}.pdf");
    }
}
