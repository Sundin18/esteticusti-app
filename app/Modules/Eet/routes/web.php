<?php

use App\Modules\Eet\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:eet.view'])
    ->prefix('eet')
    ->name('eet.')
    ->group(function () {
        Route::livewire('/', 'eet::overview')->name('overview');

        Route::livewire('sales', 'eet::sales')->name('sales.index');
        Route::livewire('sales/new', 'eet::new-sale')->name('sales.create');
        Route::livewire('sales/{sale}', 'eet::sale')->whereNumber('sale')->name('sales.show');
        Route::get('sales/{sale}/receipt', ReceiptController::class)->whereNumber('sale')->name('sales.receipt');

        Route::livewire('settings', 'eet::settings')
            ->middleware('can:eet.manage')
            ->name('settings');
    });
