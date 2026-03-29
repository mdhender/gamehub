<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendInvitationRequest;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/invitations', [
            'invitations' => Invitation::orderByDesc('created_at')
                ->get(['id', 'email', 'expires_at', 'registered_at', 'created_at']),
        ]);
    }

    public function store(SendInvitationRequest $request): RedirectResponse
    {
        $invitation = Invitation::create([
            'email' => $request->validated('email'),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        return back()->with('success', 'Invitation sent to '.$invitation->email);
    }
}
