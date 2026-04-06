<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');

        Route::middleware('throttle:admin-mutations')->group(function () {
            Route::patch('users/{user}/handle', [UserController::class, 'updateHandle'])->name('users.update-handle');
            Route::post('users/{user}/send-password-reset', [UserController::class, 'sendPasswordResetLink'])->name('users.send-password-reset');
            Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
            Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        });
    });
});
