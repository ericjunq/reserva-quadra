<?php

use App\Exceptions\RegraNegocioException;
use App\Http\Middleware\AdminAtivo;
use App\Http\Middleware\CabecalhosDeSeguranca;
use App\Http\Middleware\ClienteApiAtivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CabecalhosDeSeguranca::class);
        $middleware->alias([
            'admin.ativo' => AdminAtivo::class,
            'parceiro'    => ClienteApiAtivo::class,
            'permissao'   => CheckForAnyAbility::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, AdminAtivo::class);
        $middleware->prependToPriorityList(ThrottleRequests::class, ClienteApiAtivo::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, CheckForAnyAbility::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(RegraNegocioException::class);

        $exceptions->render(fn (AccessDeniedHttpException $e) => $e->getPrevious() instanceof MissingAbilityException
            ? response()->json([
                'message' => 'O token deste sistema não tem a permissão necessária para esta ação.',
                'dados'   => ['permissoes_necessarias' => array_values($e->getPrevious()->abilities())],
            ], 403)
            : null);

        $exceptions->render(fn (RegraNegocioException $e) => response()->json(
            array_filter(['message' => $e->getMessage(), 'dados' => $e->dados]),
            $e->status,
        ));
    })->create();
