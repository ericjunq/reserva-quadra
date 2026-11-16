<x-mail::message>
# Pedido de equipamento

Olá, {{ $reserva->aluno->nome_completo }}.

@if ($situacao === \App\Constants\SituacaoSolicitacaoConstant::APROVADA)
O seu pedido de **{{ $equipamento }} × {{ $quantidade }}** para a reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, foi **aprovado**.
@else
@if ($situacao === \App\Constants\SituacaoSolicitacaoConstant::RECUSADA)
O seu pedido de **{{ $equipamento }} × {{ $quantidade }}** para a reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, foi **recusado** pela administração.

**Motivo:** {{ $justificativa }}
@else
O seu pedido de **{{ $equipamento }} × {{ $quantidade }}** para a reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, **não foi respondido a tempo** e deixou de valer.
@endif

@if ($manterAte)
**A sua reserva será cancelada em {{ $manterAte->format('d/m/Y') }} às {{ $manterAte->format('H:i') }}**, a menos que você confirme que quer mantê-la sem o equipamento.

<x-mail::button :url="$linkManter">
Manter a reserva sem o equipamento
</x-mail::button>
@else
A sua reserva do espaço continua valendo.
@endif

@if ($linkCancelar)
Se não precisa mais do espaço, cancele agora e libere o horário: [Cancelar a reserva]({{ $linkCancelar }})
@endif
@endif

{{ config('app.name') }}
</x-mail::message>
