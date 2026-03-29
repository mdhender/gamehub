<x-mail::message>
# You're Invited!

You've been invited to join **{{ config('app.name') }}**. Click the button below to create your account.

<x-mail::button :url="$url">
Create Account
</x-mail::button>

This invitation will expire in 7 days. If you did not expect this invitation, no action is required.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
