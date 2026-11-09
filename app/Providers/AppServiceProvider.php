<?php

namespace App\Providers;

use App\Constants\TipoAuditavelConstant;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Relation::enforceMorphMap(TipoAuditavelConstant::mapaMorph());
    }
}
