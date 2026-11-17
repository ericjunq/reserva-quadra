<?php

namespace App\Constants;

enum SituacaoClienteApiConstant: string
{
    case AGUARDANDO_ACEITE = 'AGUARDANDO_ACEITE';
    case ATIVO = 'ATIVO';
    case VENCIDO = 'VENCIDO';
    case DESATIVADO = 'DESATIVADO';
}
