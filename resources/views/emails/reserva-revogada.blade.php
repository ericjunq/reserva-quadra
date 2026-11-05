<x-mail::message>
# Reserva revogada

Olá, {{ $reserva->aluno->nome_completo }}.

Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data->format('d/m/Y') }}**, no horário **{{ $reserva->horario->label() }}**, foi revogada pela administração.

**Motivo:** {{ $reserva->justificativa_revogacao }}

Pedimos desculpas pelo transtorno. Você pode fazer uma nova reserva em outro horário.

{{ config('app.name') }}
</x-mail::message>
