<x-mail::message>
# Redefinição de senha

Olá, {{ $nome }}.

Recebemos um pedido para redefinir a sua senha do sistema de reservas. Na página, informe este email e a senha nova.

<x-mail::button :url="$link">
Redefinir senha
</x-mail::button>

O link vale até **{{ $expiraEm->format('d/m/Y') }} às {{ $expiraEm->format('H:i') }}**.

Se você não pediu a redefinição, ignore este email. A sua senha continua a mesma.

{{ config('app.name') }}
</x-mail::message>
