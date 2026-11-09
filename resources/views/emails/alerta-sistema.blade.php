<x-mail::message>
# {{ $assunto }}

{{ $descricao }}

**Quando:** {{ $quando->format('d/m/Y') }} às {{ $quando->format('H:i') }}

Confira o log do servidor (`storage/logs`) para ver os detalhes do erro. Se o problema continuar, um novo alerta sobre ele será enviado em até {{ config('reservas.alertas_intervalo_minutos') }} minutos.

{{ config('app.name') }}
</x-mail::message>
