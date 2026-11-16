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

**A sua reserva do espaço continua valendo.** Se sem o equipamento ela não serve mais, cancele e libere o horário para outro aluno.

@if ($linkCancelar)
<x-mail::button :url="$linkCancelar">
Cancelar reserva
</x-mail::button>
@endif
@endif

{{ config('app.name') }}
</x-mail::message>
