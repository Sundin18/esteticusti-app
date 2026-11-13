<!DOCTYPE html>
<html lang="cs">
    <head>
        <meta charset="utf-8">
        <title>Účtenka {{ $trzba->porad_cis }}</title>
        <style>
            @page { margin: 14pt; }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #000; }
            h1 { font-size: 11pt; margin: 0 0 2pt; text-align: center; }
            p { margin: 0; }
            .stred { text-align: center; }
            .oddelovac { border-top: 1px dashed #000; margin: 8pt 0; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 1pt 0; vertical-align: top; }
            td.hodnota { text-align: right; }
            .castka td { font-size: 12pt; font-weight: bold; padding-top: 4pt; }
            .pok { font-size: 7pt; word-wrap: break-word; }
        </style>
    </head>
    <body>
        <h1>{{ $nastaveni->nazev_provozovny }}</h1>
        <p class="stred">{{ $nastaveni->adresa_provozovny }}</p>
        <p class="stred">EIČ: {{ $nastaveni->eic_popl }}</p>

        <div class="oddelovac"></div>

        <table>
            <tr>
                <td>Účtenka č.</td>
                <td class="hodnota">{{ $trzba->porad_cis }}</td>
            </tr>
            <tr>
                <td>Datum a čas</td>
                <td class="hodnota">{{ $trzba->dat_trzby->format('j. n. Y H:i:s') }}</td>
            </tr>
            <tr>
                <td>Pokladna</td>
                <td class="hodnota">{{ $nastaveni->id_pokl }}</td>
            </tr>
            <tr class="castka">
                <td>Celkem</td>
                <td class="hodnota">{{ $trzba->formatovanaCastka() }}</td>
            </tr>
        </table>

        <div class="oddelovac"></div>

        @if ($trzba->jePotvrzena())
            <p>POK:</p>
            <p class="pok">{{ $trzba->pok }}</p>

            @if ($trzba->testovaci)
                <p class="stred" style="margin-top: 6pt;">TESTOVACÍ PROSTŘEDÍ – neplatný doklad</p>
            @endif
        @else
            <p>Tržba dosud nebyla potvrzena systémem EET a bude odeslána dodatečně.</p>
        @endif
    </body>
</html>
