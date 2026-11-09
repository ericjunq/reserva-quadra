<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::command('reservas:pedir-confirmacao')
    ->dailyAt(config('reservas.horario_pedido_confirmacao'))
    ->withoutOverlapping();

Schedule::command('reservas:revogar-nao-confirmadas')
    ->dailyAt(config('reservas.horario_revogacao_nao_confirmadas'))
    ->withoutOverlapping();
