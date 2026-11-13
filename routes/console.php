<?php

use App\Services\AlertaService;
use Illuminate\Support\Facades\Schedule;

$alertarSeFalhar = fn (string $tarefa) => fn (AlertaService $alertas) => $alertas->tarefaFalhou($tarefa);

Schedule::command('reservas:pedir-reconfirmacao')
    ->dailyAt(config('reservas.horario_pedido_reconfirmacao'))
    ->withoutOverlapping()
    ->onFailure($alertarSeFalhar('Pedido de reconfirmação das reservas'));

Schedule::command('reservas:revogar-nao-confirmadas')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onFailure($alertarSeFalhar('Revogação das reservas não confirmadas'));

Schedule::command('feriados:importar', array_filter([
    now()->addYear()->year,
    config('reservas.feriados_facultativos') ? '--facultativos' : null,
]))
    ->yearlyOn(12, 1, '03:00')
    ->withoutOverlapping()
    ->onFailure($alertarSeFalhar('Importação dos feriados do próximo ano'));

Schedule::command('reservas:limpar-tokens')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->onFailure($alertarSeFalhar('Limpeza dos links de email vencidos'));

Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->onFailure($alertarSeFalhar('Limpeza das sessões de admin vencidas'));

Schedule::command('queue:prune-failed', ['--hours' => config('reservas.jobs_falhos_retencao_horas')])
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onFailure($alertarSeFalhar('Limpeza dos jobs falhos antigos'));
