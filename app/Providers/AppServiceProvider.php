<?php

namespace App\Providers;

use App\Constants\TipoAuditavelConstant;
use App\Models\ClienteApi;
use App\Services\AlertaService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\ServerVariable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(Scramble::class)) {
            $this->configurarDocumentacaoDaApi();
        }
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(TipoAuditavelConstant::mapaMorph());

        $this->definirLimites();

        Queue::failing(fn (JobFailed $evento) => app(AlertaService::class)->jobFalhou(
            class_basename($evento->job->resolveName()),
            class_basename($evento->exception),
        ));

        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }
    }

    private function configurarDocumentacaoDaApi(): void
    {
        Scramble::configure()
            ->withServerVariables(['dominio' => ServerVariable::make('reservas.exemplo.edu', description: 'Domínio do sistema de reservas, informado pela instituição')])
            ->withDocumentTransformers(fn (OpenApi $openApi) => $openApi->secure(
                SecurityScheme::http('bearer')->setDescription('Token gerado na página de aceite dos termos, enviado como Authorization: Bearer <token>.'),
            ));
    }

    private function definirLimites(): void
    {
        RateLimiter::for('consultas-publicas', fn (Request $request) => Limit::perMinute(config('reservas.limites.ip_consultas'))->by('ip:'.$request->ip()));

        RateLimiter::for('acoes-publicas', fn (Request $request) => $this->limitesComPessoa($request, 'ip_acoes', 'por_pessoa'));

        RateLimiter::for('parceiros', fn (Request $request) => $this->limitesDoParceiro($request));

        RateLimiter::for('consulta-com-ra', fn (Request $request) => $this->limitesComPessoa($request, 'ip_consultas', 'consulta_ra'));
    }

    private function limitesComPessoa(Request $request, string $limiteIp, string $limitePessoa): array
    {
        $limites = [Limit::perMinute(config("reservas.limites.{$limiteIp}"))->by('ip:'.$request->ip())];

        if ($pessoa = $this->identificadorDaPessoa($request)) {
            $limites[] = Limit::perMinute(config("reservas.limites.{$limitePessoa}"))
                ->by('pessoa:'.$request->route()?->uri().':'.$pessoa);
        }

        return $limites;
    }

    private function limitesDoParceiro(Request $request): array
    {
        $cliente = $request->user();

        if (! $cliente instanceof ClienteApi) {
            return [Limit::none()];
        }

        $limites = [Limit::perMinute($cliente->limite_por_minuto)->by('cliente:'.$cliente->id)];

        if ($request->isMethod('POST') && $pessoa = $this->identificadorDaPessoa($request)) {
            $limites[] = Limit::perMinute(config('reservas.limites.por_pessoa'))
                ->by('pessoa:'.$request->route()?->uri().':'.$pessoa);
        }

        return $limites;
    }

    private function identificadorDaPessoa(Request $request): ?string
    {
        $valor = $request->route('token') ?? $request->input('registro_academico');

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return hash_hmac('sha256', mb_strtolower(trim($valor)), (string) config('app.key'));
    }
}
