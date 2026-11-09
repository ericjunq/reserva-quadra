<x-mail::message>
# Sua senha foi alterada

Olá, {{ $nome }}.

A senha da sua conta no sistema de reservas foi alterada em **{{ $quando->format('d/m/Y') }} às {{ $quando->format('H:i') }}**. Por segurança, as sessões abertas em outros dispositivos foram encerradas.

**Não foi você?** Use "esqueci minha senha" para definir uma nova senha e avise outro administrador imediatamente.

{{ config('app.name') }}
</x-mail::message>
