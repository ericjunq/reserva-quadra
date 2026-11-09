<x-mail::message>
# Reserva confirmada

Olá, {{ $nome }}.

Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, foi registrada.

@if ($reserva->equipamentos->isNotEmpty())
**Equipamentos:**

@foreach ($reserva->equipamentos as $equipamento)
- {{ $equipamento->nome }} x{{ $equipamento->pivot->quantidade }}
@endforeach
@endif

Se não puder comparecer, cancele para liberar o horário. O link vale até o início da reserva.

<x-mail::button :url="$linkCancelamento">
Cancelar reserva
</x-mail::button>

**Não foi você quem fez esta reserva?** Cancele pelo botão acima e avise a administração.

{{ config('app.name') }}
</x-mail::message>
