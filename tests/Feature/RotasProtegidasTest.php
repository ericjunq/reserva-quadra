<?php

namespace Tests\Feature;

use App\Models\ClienteApi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Rota;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RotasProtegidasTest extends TestCase
{
    use RefreshDatabase;

    private const ROTAS_DO_ADMIN_FORA_DE_ADMIN = ['api/me', 'api/me/senha', 'api/logout', 'api/logout-geral'];

    private function rotasDoAdmin(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Rota $rota) => str_starts_with($rota->uri(), 'api/admin/')
                || in_array($rota->uri(), self::ROTAS_DO_ADMIN_FORA_DE_ADMIN, true))
            ->flatMap(fn (Rota $rota) => collect($rota->methods())
                ->reject(fn (string $metodo) => $metodo === 'HEAD')
                ->map(fn (string $metodo) => [$metodo, '/'.preg_replace('/\{[^}]+\}/', '1', $rota->uri())]))
            ->values()
            ->all();
    }

    public function test_toda_rota_do_admin_exige_login(): void
    {
        $rotas = $this->rotasDoAdmin();

        $this->assertGreaterThan(50, count($rotas));

        foreach ($rotas as [$metodo, $uri]) {
            $this->json($metodo, $uri)->assertUnauthorized();
        }
    }

    public function test_toda_rota_do_admin_passa_pelo_login_e_pela_conferencia_de_admin_ativo(): void
    {
        $semProtecao = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Rota $rota) => str_starts_with($rota->uri(), 'api/admin/')
                || in_array($rota->uri(), self::ROTAS_DO_ADMIN_FORA_DE_ADMIN, true))
            ->reject(fn (Rota $rota) => in_array('auth:sanctum', $rota->gatherMiddleware(), true)
                && in_array('admin.ativo', $rota->gatherMiddleware(), true))
            ->map(fn (Rota $rota) => $rota->uri())
            ->values()
            ->all();

        $this->assertSame([], $semProtecao);
    }

    public function test_token_de_sistema_parceiro_e_recusado_em_toda_rota_do_admin(): void
    {
        $token = ClienteApi::factory()->comAcesso()->create()->createToken('parceiro', ['*'])->plainTextToken;

        foreach ($this->rotasDoAdmin() as [$metodo, $uri]) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->json($metodo, $uri)->assertForbidden();
        }
    }

    private function rotasDaV1(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Rota $rota) => str_starts_with($rota->uri(), 'api/v1/'))
            ->values()
            ->all();
    }

    public function test_toda_rota_da_v1_exige_token_de_parceiro_limite_e_permissao(): void
    {
        $rotas = $this->rotasDaV1();

        $this->assertGreaterThanOrEqual(8, count($rotas));

        foreach ($rotas as $rota) {
            $middlewares = $rota->gatherMiddleware();

            $this->assertContains('auth:sanctum', $middlewares, $rota->uri());
            $this->assertContains('parceiro', $middlewares, $rota->uri());
            $this->assertContains('throttle:parceiros', $middlewares, $rota->uri());
            $this->assertNotEmpty(preg_grep('/^permissao:/', $middlewares), $rota->uri());
        }
    }

    public function test_rotas_da_v1_recusam_sem_token_com_token_de_admin_e_sem_a_permissao_antes_de_buscar_o_registro(): void
    {
        $admin = User::factory()->create()->createToken('api', ['*'], now()->addHour())->plainTextToken;
        $parceiro = ClienteApi::factory()->comAcesso()->create()->createToken('parceiro', [], now()->addYear())->plainTextToken;

        foreach ($this->rotasDaV1() as $rota) {
            $uri = '/'.preg_replace('/\{[^}]+\}/', '999', $rota->uri());
            $metodo = $rota->methods()[0];

            $this->app['auth']->forgetGuards();
            $this->json($metodo, $uri)->assertUnauthorized();
            $this->app['auth']->forgetGuards();
            $this->withToken($admin)->json($metodo, $uri)->assertForbidden();
            $this->app['auth']->forgetGuards();
            $this->withToken($parceiro)->json($metodo, $uri)->assertForbidden();
            $this->flushHeaders();
        }
    }

    public function test_admin_desativado_com_token_ainda_valido_e_recusado_e_perde_os_tokens(): void
    {
        $admin = User::factory()->create();
        $token = $admin->createToken('api')->plainTextToken;
        $admin->forceFill(['ativo' => false])->saveQuietly();

        $this->withToken($token)->getJson('/api/admin/reservas')->assertUnauthorized();

        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_admin_ativo_continua_entrando(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/me')->assertOk();
    }
}
