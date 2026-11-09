<x-mail::message>
# Convite para o sistema de reservas

Olá, {{ $nome }}.

@if ($convidadoPor)
{{ $convidadoPor }} cadastrou você como administrador do sistema de reservas.
@else
Você foi cadastrado como administrador do sistema de reservas.
@endif

Para acessar, defina a sua senha pelo botão abaixo. Na página, informe este email e a senha nova.

<x-mail::button :url="$link">
Definir minha senha
</x-mail::button>

O link vale até **{{ $expiraEm->format('d/m/Y') }} às {{ $expiraEm->format('H:i') }}**. Depois disso, peça um novo convite a um administrador.

Se você não esperava este convite, ignore este email.

{{ config('app.name') }}
</x-mail::message>
