<x-mail::message>
# Seu código de acesso

Olá, {{ $nome }}.

Use este código para entrar na administração do sistema de reservas:

<x-mail::panel>
<span style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $codigo }}</span>
</x-mail::panel>

Ele vale por **{{ $minutos }} minutos** e só pode ser usado uma vez. Não compartilhe este código com ninguém.

**Não foi você?** Alguém sabe a sua senha. Use "esqueci minha senha" para definir uma nova e avise outro administrador.

{{ config('app.name') }}
</x-mail::message>
