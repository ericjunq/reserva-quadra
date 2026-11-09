<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservas:pedir-confirmacao')
    ->dailyAt(config('reservas.horario_pedido_confirmacao'))
    ->withoutOverlapping();

Schedule::command('reservas:revogar-nao-confirmadas')
    ->dailyAt(config('reservas.horario_revogacao_nao_confirmadas'))
    ->withoutOverlapping();

Schedule::command('feriados:importar', array_filter([
    now()->addYear()->year,
    config('reservas.feriados_facultativos') ? '--facultativos' : null,
]))
    ->yearlyOn(12, 1, '03:00')
    ->withoutOverlapping();

Schedule::command('reservas:limpar-tokens')->dailyAt('03:10')->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:20')->withoutOverlapping();

Schedule::command('queue:prune-failed', ['--hours' => config('reservas.jobs_falhos_retencao_horas')])
    ->dailyAt('03:30')
    ->withoutOverlapping();
