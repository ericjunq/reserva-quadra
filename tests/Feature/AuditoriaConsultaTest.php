<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Espaco;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditoriaConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function consultar(array $params = [])
    {
        return $this->getJson('/api/admin/auditorias?'.http_build_query($params));
    }

    public function test_exige_login(): void
    {
        $this->consultar()->assertUnauthorized();
    }

    public function test_historico_de_um_registro_do_mais_recente_ao_mais_antigo(): void
    {
        $admin = $this->comoAdmin();

        $id = $this->postJson('/api/admin/espacos', ['nome' => 'Sala 1', 'tipo' => 'SALA'])->assertCreated()->json('data.id');
        $this->travel(1)->minutes();
        $this->patchJson("/api/admin/espacos/{$id}", ['nome' => 'Sala 101'])->assertOk();
        $this->postJson('/api/admin/espacos', ['nome' => 'Sala 2', 'tipo' => 'SALA'])->assertCreated();

        $this->consultar(['tipo' => 'ESPACO', 'registro_id' => $id])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.tipo', 'ESPACO')
            ->assertJsonPath('data.0.registro_id', $id)
            ->assertJsonPath('data.0.acao', 'ATUALIZACAO')
            ->assertJsonPath('data.0.campos_alterados', ['nome'])
            ->assertJsonPath('data.0.antes', ['nome' => 'Sala 1'])
            ->assertJsonPath('data.0.depois', ['nome' => 'Sala 101'])
            ->assertJsonPath('data.0.feito_por.id', $admin->id)
            ->assertJsonPath('data.0.feito_por.nome', $admin->name)
            ->assertJsonPath('data.1.acao', 'CRIACAO')
            ->assertJsonPath('data.1.antes', null);
    }

    public function test_banco_guarda_o_nome_curto_do_tipo(): void
    {
        $this->comoAdmin();
        Espaco::factory()->create();

        $this->assertTrue(Auditoria::where('auditavel_type', 'espaco')->exists());
        $this->assertFalse(Auditoria::whereLike('auditavel_type', 'App%')->exists());
    }

    public function test_filtros_por_admin_e_por_acoes_sem_admin(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        Aluno::factory()->create();

        Sanctum::actingAs($ana);
        Curso::factory()->create();
        Sanctum::actingAs($bruno);
        Espaco::factory()->create();

        $tipos = fn ($params) => collect($this->consultar($params)->assertOk()->json('data'))->pluck('tipo')->sort()->values()->all();

        $this->assertSame(['CURSO'], $tipos(['admin_id' => $ana->id]));
        $this->assertSame(['ADMIN', 'ADMIN', 'ALUNO', 'CURSO'], $tipos(['sem_admin' => 'true']));
        $this->assertNull($this->consultar(['sem_admin' => 'true'])->json('data.0.feito_por'));
        $this->assertSame(['CURSO', 'ESPACO'], $tipos(['sem_admin' => 'false']));
    }

    public function test_filtros_por_acao_e_periodo(): void
    {
        $this->comoAdmin();

        $this->travelTo(Carbon::parse('2026-10-20 15:00'));
        $antigo = Espaco::factory()->create();

        $this->travelTo(Carbon::parse('2026-11-09 10:00'));
        $antigo->update(['nome' => 'Renomeado']);
        Espaco::factory()->create();

        $this->consultar(['tipo' => 'ESPACO', 'acao' => 'ATUALIZACAO'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.registro_id', $antigo->id);
        $this->consultar(['tipo' => 'ESPACO', 'de' => '2026-10-01', 'ate' => '2026-10-31'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.acao', 'CRIACAO');
        $this->consultar(['tipo' => 'ESPACO', 'periodo' => 'MES_ANTERIOR'])->assertJsonCount(1, 'data');
        $this->consultar(['tipo' => 'ESPACO', 'periodo' => 'HOJE'])->assertJsonCount(2, 'data');
    }

    public function test_senha_oculta_e_aluno_anonimizado_no_historico(): void
    {
        $this->comoAdmin();
        $outro = User::factory()->create();
        $outro->update(['password' => 'NovaSenha123']);

        $aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza']);
        $this->postJson("/api/admin/alunos/{$aluno->id}/anonimizar", ['confirmar' => true])->assertOk();

        $this->consultar(['tipo' => 'ADMIN', 'registro_id' => $outro->id, 'acao' => 'ATUALIZACAO'])
            ->assertJsonPath('data.0.depois.password', '[oculto]');

        $resposta = $this->consultar(['tipo' => 'ALUNO', 'registro_id' => $aluno->id])->assertOk();
        $this->assertStringNotContainsString('Maria Souza', $resposta->getContent());
        $this->assertStringContainsString('[anonimizado]', $resposta->getContent());
    }

    public function test_validacoes(): void
    {
        $this->comoAdmin();

        $this->consultar(['registro_id' => 1])->assertStatus(422)->assertJsonValidationErrors('tipo');
        $this->consultar(['tipo' => 'PISCINA'])->assertStatus(422)->assertJsonValidationErrors('tipo');
        $this->consultar(['acao' => 'APAGOU'])->assertStatus(422)->assertJsonValidationErrors('acao');
        $this->consultar(['admin_id' => 999])->assertStatus(422)->assertJsonValidationErrors('admin_id');
        $this->consultar(['admin_id' => 1, 'sem_admin' => 'true'])->assertStatus(422)->assertJsonValidationErrors('admin_id');
        $this->consultar(['periodo' => 'HOJE', 'de' => '2026-11-01'])->assertStatus(422)->assertJsonValidationErrors('periodo');
    }

    public function test_paginacao(): void
    {
        $this->comoAdmin();
        Espaco::factory()->count(5)->create();

        $this->consultar(['tipo' => 'ESPACO', 'per_page' => 2])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5);
    }
}
