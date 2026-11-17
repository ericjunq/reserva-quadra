<?php

namespace Tests\Feature;

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
