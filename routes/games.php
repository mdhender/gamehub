<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\GameGeneration\EmpireController;
use App\Http\Controllers\GameGeneration\GenerationStepController;
use App\Http\Controllers\GameGeneration\HomeSystemController;
use App\Http\Controllers\GameGeneration\PlanetController;
use App\Http\Controllers\GameGeneration\StarController;
use App\Http\Controllers\GameGeneration\TemplateController;
use App\Http\Controllers\GameGenerationController;
use App\Http\Controllers\GameMemberController;
use App\Http\Controllers\TurnReportController;
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
    Route::post('{game}/members/{user}/promote', [GameMemberController::class, 'promote'])->name('members.promote');
    Route::delete('{game}/members/{user}/remove', [GameMemberController::class, 'remove'])->name('members.remove');

    Route::prefix('{game}/turns/{turn}/reports')->name('turns.reports.')
        ->scopeBindings()
        ->group(function () {
            Route::middleware('throttle:game-mutations')->group(function () {
                Route::post('generate', [TurnReportController::class, 'generate'])->name('generate');
                Route::post('lock', [TurnReportController::class, 'lock'])->name('lock');
            });
            Route::get('empires/{empire}', [TurnReportController::class, 'show'])->name('show');
            Route::get('empires/{empire}/download', [TurnReportController::class, 'download'])->name('download');
        });

    Route::prefix('{game}/generate')->name('generate.')->scopeBindings()->group(function () {
        Route::get('/', [GameGenerationController::class, 'show'])->name('show');
        Route::get('download', [GameGenerationController::class, 'download'])->name('download');

        Route::middleware('throttle:game-mutations')->group(function () {
            Route::post('activate', [GameGenerationController::class, 'activate'])->name('activate');
            Route::post('templates/home-system', [TemplateController::class, 'uploadHomeSystem'])->name('templates.home-system');
            Route::post('templates/colony', [TemplateController::class, 'uploadColony'])->name('templates.colony');
            Route::post('stars', [GenerationStepController::class, 'generateStars'])->name('stars');
            Route::put('stars/{star}', [StarController::class, 'update'])->name('stars.update');
            Route::post('planets', [GenerationStepController::class, 'generatePlanets'])->name('planets');
            Route::put('planets/{planet}', [PlanetController::class, 'update'])->name('planets.update');
            Route::post('deposits', [GenerationStepController::class, 'generateDeposits'])->name('deposits');
            Route::post('home-systems/random', [HomeSystemController::class, 'createRandom'])->name('home-systems.random');
            Route::post('home-systems/manual', [HomeSystemController::class, 'createManual'])->name('home-systems.manual');
            Route::post('empires', [EmpireController::class, 'store'])->name('empires.store');
            Route::delete('empires/{empire}', [EmpireController::class, 'destroy'])->name('empires.destroy');
            Route::delete('{step}', [GenerationStepController::class, 'deleteStep'])->name('steps.destroy');
        });
    });
});
