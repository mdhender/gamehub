<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\GameGenerationController;
use App\Http\Controllers\GameMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('games')->name('games.')->group(function () {
    Route::get('/', [GameController::class, 'index'])->name('index');
    Route::post('/', [GameController::class, 'store'])->name('store');
    Route::get('{game}', [GameController::class, 'show'])->name('show');
    Route::put('{game}', [GameController::class, 'update'])->name('update');
    Route::delete('{game}', [GameController::class, 'destroy'])->name('destroy');
    Route::post('{game}/members', [GameMemberController::class, 'store'])->name('members.store');
    Route::delete('{game}/members/{user}', [GameMemberController::class, 'destroy'])->name('members.destroy');
    Route::post('{game}/members/{user}/restore', [GameMemberController::class, 'restore'])->name('members.restore');

    Route::prefix('{game}/generate')->name('generate.')->group(function () {
        Route::get('/', [GameGenerationController::class, 'show'])->name('show');
        Route::post('templates/home-system', [GameGenerationController::class, 'uploadHomeSystemTemplate'])->name('templates.home-system');
        Route::post('templates/colony', [GameGenerationController::class, 'uploadColonyTemplate'])->name('templates.colony');
        Route::post('stars', [GameGenerationController::class, 'generateStars'])->name('stars');
        Route::put('stars/{star}', [GameGenerationController::class, 'updateStar'])->name('update-star');
        Route::post('planets', [GameGenerationController::class, 'generatePlanets'])->name('planets');
        Route::put('planets/{planet}', [GameGenerationController::class, 'updatePlanet'])->name('update-planet');
        Route::post('deposits', [GameGenerationController::class, 'generateDeposits'])->name('deposits');
        Route::post('activate', [GameGenerationController::class, 'activate'])->name('activate');
        Route::post('home-systems/random', [GameGenerationController::class, 'createHomeSystemRandom'])->name('home-systems.random');
        Route::post('home-systems/manual', [GameGenerationController::class, 'createHomeSystemManual'])->name('home-systems.manual');
        Route::post('empires', [GameGenerationController::class, 'createEmpire'])->name('empires.create');
        Route::put('empires/{empire}', [GameGenerationController::class, 'reassignEmpire'])->name('empires.reassign');
        Route::delete('{step}', [GameGenerationController::class, 'deleteStep'])->name('delete-step');
    });
});
