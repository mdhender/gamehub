<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('games')->name('games.')->group(function () {
    Route::get('/', [GameController::class, 'index'])->name('index');
    Route::post('/', [GameController::class, 'store'])->name('store');
    Route::delete('{game}', [GameController::class, 'destroy'])->name('destroy');
});
