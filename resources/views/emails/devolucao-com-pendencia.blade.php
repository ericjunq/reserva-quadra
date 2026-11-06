<x-mail::message>
# Pendência na devolução de equipamentos

Olá, {{ $reserva->aluno->nome_completo }}.

Na sua reserva do espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**, a devolução destes equipamentos foi registrada com pendência:

<x-mail::table>
| Equipamento | Reservado | Devolvido | Situação | Observação |
|:--|:--:|:--:|:--|:--|
@foreach ($itens as $item)
| {{ $item['nome'] }} | {{ $item['quantidade'] }} | {{ $item['quantidade_devolvida'] }} | {{ $item['situacao'] }} | {{ $item['observacao'] }} |
@endforeach
</x-mail::table>

Se houver algum engano, procure a administração.

{{ config('app.name') }}
</x-mail::message>
