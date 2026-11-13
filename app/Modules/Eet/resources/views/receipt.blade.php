<!DOCTYPE html>
<html lang="cs">
    <head>
        <meta charset="utf-8">
        <title>Účtenka {{ $sale->receipt_number }}</title>
        <style>
            @page { margin: 14pt; }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #000; }
            h1 { font-size: 11pt; margin: 0 0 2pt; text-align: center; }
            p { margin: 0; }
            .center { text-align: center; }
            .divider { border-top: 1px dashed #000; margin: 8pt 0; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 1pt 0; vertical-align: top; }
            td.value { text-align: right; }
            .total td { font-size: 12pt; font-weight: bold; padding-top: 4pt; }
            .pok { font-size: 7pt; word-wrap: break-word; }
        </style>
    </head>
    <body>
        <h1>{{ $sale->premises_name }}</h1>
        <p class="center">{{ $sale->premises_address }}</p>
        <p class="center">EIČ: {{ $sale->taxpayer_eic }}</p>

        <div class="divider"></div>

        <table>
            <tr>
                <td>Účtenka č.</td>
                <td class="value">{{ $sale->receipt_number }}</td>
            </tr>
            @if ($sale->isCancellation())
                <tr>
                    <td>Storno účtenky č.</td>
                    <td class="value">{{ $sale->cancelledSale->receipt_number }}</td>
                </tr>
            @endif
            <tr>
                <td>Datum a čas</td>
                <td class="value">{{ $sale->sold_at->format('j. n. Y H:i:s') }}</td>
            </tr>
            <tr>
                <td>Pokladna</td>
                <td class="value">{{ $sale->register_id }}</td>
            </tr>
            <tr>
                <td>Platba</td>
                <td class="value">{{ $sale->payment_method->label() }}</td>
            </tr>
            <tr class="total">
                <td>Celkem</td>
                <td class="value">{{ $sale->formattedAmount() }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        @if ($sale->isConfirmed())
            <p>POK:</p>
            <p class="pok">{{ $sale->pok }}</p>

            @if ($sale->is_test)
                <p class="center" style="margin-top: 6pt;">TESTOVACÍ PROSTŘEDÍ – neplatný doklad</p>
            @endif
        @else
            <p>Tržba dosud nebyla potvrzena systémem EET a bude odeslána dodatečně.</p>
        @endif
    </body>
</html>
