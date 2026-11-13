<x-mail::message>
@if ($etapa === \App\Constants\EtapaConfirmacaoConstant::CONFIRMACAO)
# Confirme sua reserva
@else
# Sua reserva está chegando
@endif

Olá, {{ $nome }}.

@if ($etapa === \App\Constants\EtapaConfirmacaoConstant::CONFIRMACAO)
Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, foi registrada e já está guardando o horário. Para valer, ela precisa ser **confirmada por você**. Na confirmação, você lê e aceita os termos de uso e o aviso de privacidade.
@else
Sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, está chegando. Confirme que ainda vai usar o horário.
@endif

@if ($reserva->equipamentos->isNotEmpty())
**Equipamentos:**

@foreach ($reserva->equipamentos as $equipamento)
- {{ $equipamento->nome }} x{{ $equipamento->pivot->quantidade }}
@endforeach
@endif

**Confirme até {{ $prazo->format('d/m/Y') }} às {{ $prazo->format('H:i') }}.** Sem a confirmação, a reserva é cancelada automaticamente e o horário fica livre para outra pessoa.

<x-mail::button :url="$linkConfirmacao" color="success">
Confirmar reserva
</x-mail::button>

@if ($avisarReconfirmacao)
Perto da data, você vai receber outro email pedindo para confirmar de novo.
@endif

Não vai mais usar? Cancele e libere o horário:

<x-mail::button :url="$linkCancelamento" color="error">
Cancelar reserva
</x-mail::button>

**Não foi você quem fez esta reserva?** Não confirme: cancele pelo botão acima e avise a administração.

{{ config('app.name') }}
</x-mail::message>
