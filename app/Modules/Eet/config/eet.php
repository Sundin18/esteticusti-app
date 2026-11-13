<?php

use Eet2\Client;

return [

    /*
    |--------------------------------------------------------------------------
    | Adresy služby EET 2.0
    |--------------------------------------------------------------------------
    |
    | Adresa služby pro každé prostředí. Které prostředí se použije, určuje
    | nastavení modulu v databázi (tabulka eet_settings).
    |
    */

    'url' => [
        'test' => env('EET_URL_TEST', Client::PLAYGROUND),
        'production' => env('EET_URL_PRODUCTION', Client::PRODUCTION),
    ],

];
