<x-mail::message>
# Pedido de equipamento

Olá, {{ $reserva->aluno->nome_completo }}.

Resposta aos equipamentos pedidos para a reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**:

@foreach ($itens as $item)
- **{{ $item['nome'] }} × {{ $item['quantidade'] }}**: @switch($item['situacao'])
@case(\App\Constants\SituacaoSolicitacaoConstant::APROVADA)
aprovado
@break
@case(\App\Constants\SituacaoSolicitacaoConstant::RECUSADA)
recusado (motivo: {{ $item['justificativa'] }})
@break
@default
sem resposta da administração a tempo
@endswitch
@endforeach

@if (! $naoLiberados)
Está tudo certo: os equipamentos estarão disponíveis na sua reserva.
@elseif ($manterAte)
**A sua reserva será cancelada em {{ $manterAte->format('d/m/Y') }} às {{ $manterAte->format('H:i') }}**, a menos que você confirme que quer mantê-la sem os equipamentos não liberados.

<x-mail::button :url="$linkManter">
Manter a reserva mesmo assim
</x-mail::button>
@else
A sua reserva do espaço continua valendo.
@endif

@if ($linkCancelar)
Se não precisa mais do espaço, cancele agora e libere o horário: [Cancelar a reserva]({{ $linkCancelar }})
@endif

{{ config('app.name') }}
</x-mail::message>
