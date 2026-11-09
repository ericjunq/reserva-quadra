<?php

namespace App\Console\Commands;

use App\Constants\OrigemBloqueioConstant;
use App\Models\DataBloqueada;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class VerificarProducao extends Command
{
    protected $signature = 'sistema:verificar-producao';

    protected $description = 'Confere se a configuração está pronta para produção';

    public function handle(): int
    {
        $bancoOk = $this->bancoResponde();

        $verificacoes = [
            ['APP_ENV é production', app()->isProduction(), 'defina APP_ENV=production'],
            ['APP_DEBUG desligado', ! config('app.debug'), 'defina APP_DEBUG=false'],
            ['APP_KEY definida', filled(config('app.key')), 'rode php artisan key:generate'],
            ['APP_URL com HTTPS', $this->https(config('app.url')), 'use https:// no APP_URL'],
            ['FRONTEND_URL com HTTPS', $this->https(config('app.frontend_url')), 'use https:// no FRONTEND_URL'],
            ['CORS liberado só para endereços HTTPS', $this->corsOk(), 'defina CORS_ALLOWED_ORIGINS com o endereço do front (https://...)'],
            ['Banco de dados responde', $bancoOk, 'confira DB_HOST, DB_DATABASE, DB_USERNAME e DB_PASSWORD'],
            ['Banco é MySQL ou Postgres', in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true), 'use DB_CONNECTION=mysql ou pgsql'],
            ['Fila fora do modo sync', config('queue.default') !== 'sync', 'use QUEUE_CONNECTION=database e o supervisor'],
            ['Email configurado', ! in_array(config('mail.default'), ['log', 'array'], true), 'configure MAIL_MAILER e o SMTP'],
            ['Remetente do email definido', config('mail.from.address') !== 'hello@example.com', 'defina MAIL_FROM_ADDRESS'],
            ['Cache fora da memória', config('cache.default') !== 'array', 'use CACHE_STORE=database'],
            ['Logs diários com nível warning ou acima', $this->logsOk(), 'use LOG_STACK=daily e LOG_LEVEL=warning'],
            ['ALERTAS_EMAIL definido', filled(config('reservas.alertas_email')), 'defina ALERTAS_EMAIL com o email que recebe os alertas'],
            ['ADMIN_INICIAL_SENHA vazio', blank(config('reservas.admin_inicial.senha')), 'apague ADMIN_INICIAL_SENHA do .env depois de criar o admin'],
            ['Admin principal ativo', $bancoOk && (bool) User::buscarPrincipal()?->ativo, 'rode php artisan admin:criar-principal'],
            ['Feriados do ano importados', $bancoOk && $this->feriadosDoAno(), 'rode php artisan feriados:importar'],
            ['Configuração em cache', app()->configurationIsCached(), 'rode php artisan config:cache'],
            ['Rotas em cache', app()->routesAreCached(), 'rode php artisan route:cache'],
            ['Eventos em cache', app()->eventsAreCached(), 'rode php artisan event:cache'],
        ];

        $falhas = 0;

        foreach ($verificacoes as [$descricao, $ok, $dica]) {
            if ($ok) {
                $this->line("✅ {$descricao}");

                continue;
            }

            $falhas++;
            $this->line("❌ {$descricao}: {$dica}");
        }

        if ($falhas > 0) {
            $this->error("{$falhas} item(ns) para corrigir.");

            return self::FAILURE;
        }

        $this->info('Tudo pronto para produção.');

        return self::SUCCESS;
    }

    private function https(?string $url): bool
    {
        return str_starts_with((string) $url, 'https://');
    }

    private function corsOk(): bool
    {
        $origens = config('cors.allowed_origins', []);

        return $origens !== [] && collect($origens)->every(fn (string $origem) => $this->https($origem));
    }

    private function logsOk(): bool
    {
        $canais = config('logging.default') === 'stack'
            ? config('logging.channels.stack.channels', [])
            : [config('logging.default')];

        return in_array('daily', $canais, true)
            && in_array(config('logging.channels.daily.level'), ['warning', 'error', 'critical', 'alert', 'emergency'], true);
    }

    private function bancoResponde(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function feriadosDoAno(): bool
    {
        return DataBloqueada::query()
            ->where('origem', OrigemBloqueioConstant::FERIADO)
            ->where('data_inicio', '>=', now()->startOfYear()->toDateString())
            ->where('data_inicio', '<=', now()->endOfYear()->toDateString())
            ->exists();
    }
}
