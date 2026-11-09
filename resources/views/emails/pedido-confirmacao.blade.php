<x-mail::message>
# Confirme sua reserva

Olá, {{ $nome }}.

@if ($recemCriada)
Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, foi registrada. Como ela está próxima, já precisamos da sua confirmação.
@else
Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, está chegando.
@endif

@if ($reserva->equipamentos->isNotEmpty())
**Equipamentos:**

@foreach ($reserva->equipamentos as $equipamento)
- {{ $equipamento->nome }} x{{ $equipamento->pivot->quantidade }}
@endforeach
@endif

**Confirme até {{ $prazo->format('d/m/Y') }} às {{ $prazo->format('H:i') }}.** Sem a confirmação, a reserva será revogada e o horário ficará livre para outra pessoa.

<x-mail::button :url="$linkConfirmacao" color="success">
Confirmar reserva
</x-mail::button>

Não vai mais usar? Cancele e libere o horário:

<x-mail::button :url="$linkCancelamento" color="error">
Cancelar reserva
</x-mail::button>

**Não foi você quem fez esta reserva?** Cancele pelo botão acima e avise a administração.

{{ config('app.name') }}
</x-mail::message>
