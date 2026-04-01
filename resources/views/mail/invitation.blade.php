<x-mail::message>
# You're Invited!

You've been invited to join **{{ config('app.name') }}**. Click the button below to create your account.

Please note: this invitation is tied to this email address. You must register using **{{ $invitation->email }}** to accept it.

<x-mail::button :url="$url">
Create Account
</x-mail::button>

This invitation will expire in 7 days. If you did not expect this invitation, no action is required.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
