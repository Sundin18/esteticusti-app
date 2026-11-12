<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('register', 'pages::auth.register')
        ->middleware('can:users.create')
        ->name('register');
});

require __DIR__.'/settings.php';
