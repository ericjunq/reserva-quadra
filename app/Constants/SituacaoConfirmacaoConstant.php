<?php

namespace App\Constants;

enum SituacaoConfirmacaoConstant: string
{
    case NAO_SOLICITADA = 'NAO_SOLICITADA';
    case AGUARDANDO = 'AGUARDANDO';
    case CONFIRMADA = 'CONFIRMADA';
}
