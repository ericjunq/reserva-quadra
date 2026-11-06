<?php

namespace App\Constants;

enum AcaoAuditoriaConstant: string
{
    case CRIACAO = 'CRIACAO';
    case ATUALIZACAO = 'ATUALIZACAO';
    case EXCLUSAO = 'EXCLUSAO';
}
