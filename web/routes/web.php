<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? to_route('game-session.import')
    : to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
