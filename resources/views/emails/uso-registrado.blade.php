<x-mail::message>
# Uso de espaço registrado

Olá, {{ $reserva->aluno->nome_completo }}.

A administração registrou que você usou o espaço **{{ $reserva->espaco->nome }}** em **{{ $reserva->data_reserva->format('d/m/Y') }}**, das **{{ substr($reserva->hora_inicio, 0, 5) }}** às **{{ substr($reserva->hora_fim, 0, 5) }}**. O registro serve para manter o histórico de uso dos espaços e não precisa de confirmação.

**Não foi você?** Procure a coordenação para corrigir o registro.

{{ config('app.name') }}
</x-mail::message>
