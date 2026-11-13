<?php

namespace App\Constants;

enum SituacaoConfirmacaoConstant: string
{
    case AGUARDANDO_CONFIRMACAO = 'AGUARDANDO_CONFIRMACAO';
    case CONFIRMADA = 'CONFIRMADA';
    case AGUARDANDO_RECONFIRMACAO = 'AGUARDANDO_RECONFIRMACAO';
    case RECONFIRMADA = 'RECONFIRMADA';
}
