<?php

namespace App\Services;

use App\Mail\AlertaSistemaMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AlertaService
{
    public function tarefaFalhou(string $tarefa): void
    {
        $this->enviar(
            'tarefa:'.$tarefa,
            'Tarefa agendada falhou',
            "A tarefa agendada \"{$tarefa}\" terminou com erro.",
        );
    }

    public function jobFalhou(string $job, string $tipoDoErro): void
    {
        $this->enviar(
            'job:'.$job,
            'Tarefa em segundo plano falhou',
            "O job \"{$job}\" falhou depois de todas as tentativas (erro do tipo {$tipoDoErro}).",
        );
    }

    private function enviar(string $chave, string $assunto, string $descricao): void
    {
        if (! Cache::add('alerta:'.$chave, true, now()->addMinutes(config('reservas.alertas_intervalo_minutos')))) {
            return;
        }

        $quando = now();

        foreach ($this->destinatarios() as $email) {
            try {
                Mail::to($email)->send(new AlertaSistemaMail($assunto, $descricao, $quando));
            } catch (Throwable $erro) {
                Log::error('Falha ao enviar alerta do sistema.', ['alerta' => $chave, 'erro' => $erro::class]);

                return;
            }
        }
    }

    private function destinatarios(): array
    {
        $admins = User::query()
            ->where('ativo', true)
            ->whereNotNull('convite_aceito_em')
            ->pluck('email')
            ->all();

        $extras = explode(',', (string) config('reservas.alertas_email'));

        return array_values(array_unique(array_filter(array_map(
            fn (string $email) => User::normalizarEmail($email),
            [...$admins, ...$extras],
        ))));
    }
}
