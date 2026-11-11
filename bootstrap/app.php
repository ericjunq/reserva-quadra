<?php

use App\Exceptions\RegraNegocioException;
use App\Http\Middleware\CabecalhosDeSeguranca;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CabecalhosDeSeguranca::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(RegraNegocioException::class);

        $exceptions->render(fn (RegraNegocioException $e) => response()->json(
            array_filter(['message' => $e->getMessage(), 'dados' => $e->dados]),
            $e->status,
        ));
    })->create();
