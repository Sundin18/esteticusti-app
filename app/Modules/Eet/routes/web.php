<?php

use App\Modules\Eet\Http\Controllers\UctenkaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:eet.view'])
    ->prefix('eet')
    ->name('eet.')
    ->group(function () {
        Route::livewire('/', 'eet::prehled')->name('prehled');

        Route::livewire('trzby', 'eet::trzby')->name('trzby.seznam');
        Route::livewire('trzby/nova', 'eet::nova-trzba')->name('trzby.nova');
        Route::livewire('trzby/{trzba}', 'eet::trzba')->whereNumber('trzba')->name('trzby.detail');
        Route::get('trzby/{trzba}/uctenka', UctenkaController::class)->whereNumber('trzba')->name('trzby.uctenka');

        Route::livewire('nastaveni', 'eet::nastaveni')
            ->middleware('can:eet.manage')
            ->name('nastaveni');
    });
