<?php

namespace Tests\Feature;

use App\Exceptions\RegraNegocioException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

class LogTest extends TestCase
{
    public function test_erro_de_regra_de_negocio_nao_vai_para_o_log(): void
    {
        $this->assertFalse(app(ExceptionHandler::class)->shouldReport(new RegraNegocioException('Link inválido ou expirado.')));
    }

    public function test_erro_inesperado_continua_indo_para_o_log(): void
    {
        $this->assertTrue(app(ExceptionHandler::class)->shouldReport(new \RuntimeException('falha')));
    }
}
