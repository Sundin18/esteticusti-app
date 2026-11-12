<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:eet.view'])
    ->prefix('eet')
    ->name('eet.')
    ->group(function () {
        Route::livewire('/', 'eet::prehled')->name('prehled');
    });
