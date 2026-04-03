<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HandleUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::select(['id', 'name', 'handle', 'email', 'is_admin', 'created_at'])
                ->orderBy('name')
                ->withExists(['games as is_gm' => fn ($q) => $q
                    ->wherePivot('role', GameRole::Gm->value)
                    ->wherePivot('is_active', true),
                ])
                ->get(),
        ]);
    }

    public function show(User $user): Response
    {
        Gate::authorize('view', $user);

        return Inertia::render('admin/users/show', [
            'user' => $user->only('id', 'name', 'handle', 'email', 'is_admin', 'email_verified_at', 'created_at', 'updated_at'),
        ]);
    }

    public function updateHandle(HandleUpdateRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return back();
    }

    public function sendPasswordResetLink(User $user): RedirectResponse
    {
        Gate::authorize('view', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        return $status === Password::ResetLinkSent
            ? back()->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
