<?php

namespace App\Providers;

use App\Constants\TipoAuditavelConstant;
use App\Services\AlertaService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Relation::enforceMorphMap(TipoAuditavelConstant::mapaMorph());

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
}
