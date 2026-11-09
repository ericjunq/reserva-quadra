<x-mail::message>
# Reserva cancelada

Olá, {{ $nome }}.

Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, foi cancelada e o horário ficou livre.

**Não foi você quem cancelou?** Procure a administração.

{{ config('app.name') }}
</x-mail::message>
