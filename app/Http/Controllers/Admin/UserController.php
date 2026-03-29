<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'is_admin', 'created_at']),
        ]);
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('admin/users/show', [
            'user' => $user->only('id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at', 'updated_at'),
        ]);
    }
}
