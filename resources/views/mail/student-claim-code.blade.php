<x-mail::message>
# Hello {{ $name }},

Use the code below to finish claiming your student account.

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

The code expires in {{ $expiresInMinutes }} minutes. If you did not try to
create an account on the {{ config('app.name') }}, you can ignore this email —
nothing has changed, and nobody can use your registration number without this
code.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
