<x-mail::message>
# Acesso à API do sistema de reservas

Olá, {{ $nome }}.

@if ($acessoVenceEm)
O acesso do sistema **{{ $sistema }}** à API de reservas vence em **{{ $acessoVenceEm->format('d/m/Y') }}**. Para renovar por mais um período, leia e aceite de novo os termos de uso da API pelo botão abaixo. Você recebe um token novo, e o atual continua funcionando até o vencimento, para dar tempo de trocar.
@else
Você foi indicado como responsável pelo sistema **{{ $sistema }}**, que vai acessar a API do sistema de reservas. Para liberar o acesso, leia e aceite os termos de uso da API pelo botão abaixo.
@endif

<x-mail::button :url="$link">
Ler e aceitar os termos
</x-mail::button>

Depois do aceite, o token de acesso aparece **uma única vez** na própria página. Guarde-o num lugar seguro e não o envie por email nem o coloque em código público.

O link vale até **{{ $expiraEm->format('d/m/Y') }} às {{ $expiraEm->format('H:i') }}**. Depois disso, peça um novo link a um administrador do sistema de reservas.

Se você não reconhece este pedido, ignore este email e avise a instituição.

{{ config('app.name') }}
</x-mail::message>
