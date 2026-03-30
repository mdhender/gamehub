<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $activeGamesCount = Game::where('is_active', true)->count();
            $currentGame = Game::where('is_active', true)
                ->latest('updated_at')
                ->first(['id', 'name']);
        } else {
            $activeGamesCount = $user->games()
                ->where('games.is_active', true)
                ->count();
            $currentGame = $user->games()
                ->where('games.is_active', true)
                ->latest('games.updated_at')
                ->first(['games.id', 'games.name']);
        }

        return Inertia::render('dashboard', [
            'activeGamesCount' => $activeGamesCount,
            'currentGame' => $currentGame,
        ]);
    }
}
