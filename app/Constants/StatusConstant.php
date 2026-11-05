<?php

namespace App\Constants;

enum StatusConstant: string
{
    case RESERVADO = 'RESERVADO';
    case CANCELADO = 'CANCELADO';
    case REVOGADO = 'REVOGADO';
}
