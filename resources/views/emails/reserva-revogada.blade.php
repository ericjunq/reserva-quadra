<x-mail::message>
# Reserva revogada

Olá, {{ $reserva->aluno->nome_completo }}.

Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, foi revogada.

**Motivo:** {{ $reserva->justificativa_revogacao }}

Se ainda precisar do espaço, você pode fazer uma nova reserva em outro horário. Em caso de dúvida, procure a administração.

{{ config('app.name') }}
</x-mail::message>
