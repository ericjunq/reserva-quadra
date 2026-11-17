<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\ClienteApi;
use App\Models\Curso;
use App\Models\DataBloqueada;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultasDasListagensTest extends TestCase
{
    use RefreshDatabase;

    public static function listagens(): array
    {
        return [
            'histórico' => [
                fn () => Curso::factory()->create()->update(['nome' => fake()->unique()->words(3, true)]),
                fn (TestCase $teste) => $teste->getJson('/api/admin/auditorias'),
            ],
            'espaços' => [
                fn () => Espaco::factory()->create(),
                fn (TestCase $teste) => $teste->getJson('/api/admin/espacos'),
            ],
            'equipamentos' => [
                fn () => Equipamento::factory()->create(),
                fn (TestCase $teste) => $teste->getJson('/api/admin/equipamentos'),
            ],
            'cursos' => [
                fn () => Curso::factory()->create(),
                fn (TestCase $teste) => $teste->getJson('/api/admin/cursos'),
            ],
            'datas bloqueadas' => [
                fn () => DataBloqueada::factory()->create(),
                fn (TestCase $teste) => $teste->getJson('/api/admin/datas-bloqueadas'),
            ],
            'alunos' => [
                fn () => Aluno::factory()->create(),
                fn (TestCase $teste) => $teste->postJson('/api/admin/alunos/consulta', []),
            ],
            'sistemas parceiros' => [
                fn () => ClienteApi::factory()->comAcesso()->create(),
                fn (TestCase $teste) => $teste->getJson('/api/admin/clientes-api'),
            ],
            'admins' => [
                fn () => User::factory()->create(),
                fn (TestCase $teste) => $teste->postJson('/api/admin/admins/consulta', []),
            ],
        ];
    }

    #[DataProvider('listagens')]
    public function test_a_listagem_nao_faz_uma_consulta_por_item(Closure $criar, Closure $listar): void
    {
        $this->comoAdmin();

        $contar = function () use ($listar): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $resposta = $listar($this);
            DB::disableQueryLog();

            $this->assertInstanceOf(TestResponse::class, $resposta);
            $resposta->assertOk();

            return count(DB::getQueryLog());
        };

        for ($i = 0; $i < 2; $i++) {
            $criar();
        }
        $poucos = $contar();

        for ($i = 0; $i < 10; $i++) {
            $criar();
        }

        $this->assertSame($poucos, $contar());
    }
}
