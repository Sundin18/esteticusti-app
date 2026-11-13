<?php

namespace App\Modules\Eet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Models\Trzba;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class UctenkaController extends Controller
{
    /**
     * Width of an 80 mm receipt roll and the receipt's height, in points.
     */
    private const array ROZMER_UCTENKY = [0, 0, 226.77, 340];

    /**
     * Render the receipt as a PDF for printing; nothing is stored. A rejected sale has no receipt.
     */
    public function __invoke(Trzba $trzba): Response
    {
        abort_if($trzba->stav === StavTrzby::Zamitnuta, 404);

        $nastaveni = Nastaveni::aktualni() ?? abort(404);

        return Pdf::setOption('isFontSubsettingEnabled', true)
            ->loadView('eet::uctenka', ['trzba' => $trzba, 'nastaveni' => $nastaveni])
            ->setPaper(self::ROZMER_UCTENKY)
            ->stream("uctenka-{$trzba->id}.pdf");
    }
}
