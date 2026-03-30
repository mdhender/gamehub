<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            $adminStats = [
                'totalActiveUsers' => User::count(),
                'loggedInUsersCount' => DB::table('sessions')
                    ->whereNotNull('user_id')
                    ->where('last_activity', '>=', now()->subMinutes(15)->timestamp)
                    ->count(DB::raw('DISTINCT user_id')),
                'pendingInvitesCount' => Invitation::valid()->count(),
            ];
        } else {
            $activeGamesCount = $user->games()
                ->where('games.is_active', true)
                ->count();
            $currentGame = $user->games()
                ->where('games.is_active', true)
                ->latest('games.updated_at')
                ->first(['games.id', 'games.name']);

            $adminStats = [];
        }

        return Inertia::render('dashboard', [
            'activeGamesCount' => $activeGamesCount,
            'currentGame' => $currentGame,
            ...$adminStats,
        ]);
    }
}
