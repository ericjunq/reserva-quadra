<?php

namespace Tests\Feature;

use App\Models\Espaco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LimitesPublicosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetInstance(ThrottleRequests::class);
    }

    private function consultarMinhasReservas(string $ra, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/minhas-reservas', ['registro_academico' => $ra, 'email' => 'aluno@email.com']);
    }

    public function test_o_mesmo_ra_e_limitado_sem_afetar_outros_alunos_no_mesmo_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertNotSame(429, $this->consultarMinhasReservas('2024000001')->status());
        }

        $this->consultarMinhasReservas('2024000001')->assertStatus(429);
        $this->assertNotSame(429, $this->consultarMinhasReservas('2024000002')->status());
    }

    public function test_o_limite_por_ip_e_mais_alto_e_vale_para_todos_os_alunos_do_ip(): void
    {
        config(['reservas.limites.ip_acoes' => 3]);

        foreach (['2024000001', '2024000002', '2024000003'] as $ra) {
            $this->assertNotSame(429, $this->consultarMinhasReservas($ra)->status());
        }

        $this->consultarMinhasReservas('2024000004')->assertStatus(429);
        $this->assertNotSame(429, $this->consultarMinhasReservas('2024000004', '10.0.0.2')->status());
    }

    public function test_os_links_do_email_sao_limitados_por_token(): void
    {
        $token = str_repeat('a', 64);

        for ($i = 0; $i < 5; $i++) {
            $this->getJson("/api/reservas/confirmacao/{$token}");
        }

        $this->getJson("/api/reservas/confirmacao/{$token}")->assertStatus(429);
        $this->assertNotSame(429, $this->getJson('/api/reservas/confirmacao/'.str_repeat('b', 64))->status());
    }

    public function test_as_consultas_do_calendario_tem_o_limite_por_ip_das_consultas(): void
    {
        config(['reservas.limites.ip_consultas' => 2]);
        $espaco = Espaco::factory()->create();

        $url = "/api/espacos/{$espaco->id}/disponibilidade?de=2026-11-09&ate=2026-11-13";

        $this->getJson($url)->assertSuccessful();
        $this->getJson($url)->assertSuccessful();
        $this->getJson($url)->assertStatus(429);
    }

    public function test_o_ra_nao_vai_em_claro_para_a_chave_do_limite(): void
    {
        $request = Request::create('/api/minhas-reservas', 'POST', ['registro_academico' => '2024000001']);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $chaves = collect(RateLimiter::limiter('acoes-publicas')($request))->pluck('key');

        $this->assertCount(2, $chaves);
        $chaves->each(fn (string $chave) => $this->assertStringNotContainsString('2024000001', $chave));
    }
}
