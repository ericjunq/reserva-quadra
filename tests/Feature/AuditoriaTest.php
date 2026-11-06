<?php

namespace Tests\Feature;

use App\Constants\AcaoAuditoriaConstant;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\DataBloqueada;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private function historico($model)
    {
        return Auditoria::where('auditavel_type', $model::class)->where('auditavel_id', $model->id)->orderBy('id')->get();
    }

    public function test_criacao_pelo_admin_preenche_colunas_e_historico(): void
    {
        $admin = $this->comoAdmin();

        $id = $this->postJson('/api/admin/espacos', ['nome' => 'Quadra', 'tipo' => 'QUADRA'])
            ->assertCreated()
            ->assertJsonPath('data.criado_por.id', $admin->id)
            ->assertJsonPath('data.criado_por.nome', $admin->name)
            ->assertJsonPath('data.atualizado_por.id', $admin->id)
            ->json('data.id');

        $espaco = Espaco::find($id);
        $this->assertSame($admin->id, $espaco->criado_por);
        $this->assertSame($admin->id, $espaco->atualizado_por);

        $historico = $this->historico($espaco);
        $this->assertCount(1, $historico);
        $this->assertSame(AcaoAuditoriaConstant::CRIACAO, $historico[0]->acao);
        $this->assertSame($admin->id, $historico[0]->user_id);
        $this->assertNull($historico[0]->antes);
        $this->assertSame('Quadra', $historico[0]->depois['nome']);
        $this->assertArrayNotHasKey('updated_at', $historico[0]->depois);
    }

    public function test_alteracao_guarda_quem_alterou_e_o_antes_e_depois(): void
    {
        $criador = User::factory()->create();
        $espaco = Espaco::factory()->create(['nome' => 'Sala 1', 'criado_por' => $criador->id]);

        $editor = $this->comoAdmin();
        $this->patchJson("/api/admin/espacos/{$espaco->id}", ['nome' => 'Sala 101'])
            ->assertOk()
            ->assertJsonPath('data.criado_por.id', $criador->id)
            ->assertJsonPath('data.atualizado_por.id', $editor->id);

        $alteracao = $this->historico($espaco)->last();
        $this->assertSame(AcaoAuditoriaConstant::ATUALIZACAO, $alteracao->acao);
        $this->assertSame($editor->id, $alteracao->user_id);
        $this->assertSame(['nome' => 'Sala 1'], $alteracao->antes);
        $this->assertSame(['nome' => 'Sala 101'], $alteracao->depois);
    }

    public function test_desativacao_fica_no_historico(): void
    {
        $this->comoAdmin();
        $curso = Curso::factory()->create();

        $this->deleteJson("/api/admin/cursos/{$curso->id}")->assertNoContent();

        $alteracao = $this->historico($curso)->last();
        $this->assertSame(['ativo' => true], $alteracao->antes);
        $this->assertSame(['ativo' => false], $alteracao->depois);
    }

    public function test_alteracao_sem_mudanca_nao_gera_historico(): void
    {
        $this->comoAdmin();
        $espaco = Espaco::factory()->create(['nome' => 'Sala 1']);

        $this->patchJson("/api/admin/espacos/{$espaco->id}", ['nome' => 'Sala 1'])->assertOk();

        $this->assertCount(1, $this->historico($espaco));
    }

    public function test_acoes_publicas_ficam_sem_admin(): void
    {
        $id = $this->postJson('/api/alunos', [
            'nome_completo'      => 'Maria Souza',
            'registro_academico' => '2024000001',
            'email'              => 'maria@email.com',
            'curso_id'           => Curso::factory()->create()->id,
        ])->assertCreated()->assertJsonMissingPath('data.criado_por')->json('data.id');

        $reservaId = $this->postJson('/api/reservas', [
            'registro_academico' => '2024000001',
            'espaco_id'          => Espaco::factory()->create()->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => 'PRIMEIRO_HORARIO',
        ])->assertCreated()->json('data.id');

        $reserva = Reserva::find($reservaId);
        $this->assertNull($reserva->criado_por);
        $this->assertNull($reserva->atualizado_por);
        $this->assertNull($this->historico($reserva)->first()->user_id);
        $this->assertSame($id, $reserva->aluno_id);
    }

    public function test_revogacao_registra_o_admin_e_a_mudanca_de_status(): void
    {
        Mail::fake();
        $reserva = Reserva::factory()->create();
        $admin = $this->comoAdmin();

        $this->postJson("/api/admin/reservas/{$reserva->id}/revogar", ['justificativa' => 'Manutenção da quadra'])->assertOk();

        $this->assertSame($admin->id, $reserva->fresh()->atualizado_por);

        $alteracao = $this->historico($reserva)->last();
        $this->assertSame('RESERVADO', $alteracao->antes['status']);
        $this->assertSame('REVOGADO', $alteracao->depois['status']);
        $this->assertSame($admin->id, $alteracao->user_id);
    }

    public function test_exclusao_guarda_o_registro_apagado(): void
    {
        $bloqueio = DataBloqueada::factory()->create(['motivo' => 'Recesso']);
        $admin = $this->comoAdmin();

        $this->deleteJson("/api/admin/datas-bloqueadas/{$bloqueio->id}")->assertNoContent();

        $exclusao = $this->historico($bloqueio)->last();
        $this->assertSame(AcaoAuditoriaConstant::EXCLUSAO, $exclusao->acao);
        $this->assertSame('Recesso', $exclusao->antes['motivo']);
        $this->assertNull($exclusao->depois);
        $this->assertSame($admin->id, $exclusao->user_id);
    }

    public function test_rotas_publicas_nao_mostram_auditoria(): void
    {
        Espaco::factory()->create(['criado_por' => User::factory()->create()->id]);

        $this->getJson('/api/espacos')->assertOk()
            ->assertJsonMissingPath('data.0.criado_por')
            ->assertJsonMissingPath('data.0.atualizado_em');
    }

    public function test_listagens_do_admin_mostram_auditoria(): void
    {
        $admin = User::factory()->create(['name' => 'Coordenação']);
        Espaco::factory()->create(['criado_por' => $admin->id]);
        $this->comoAdmin();

        $this->getJson('/api/admin/espacos')->assertOk()
            ->assertJsonPath('data.0.criado_por.nome', 'Coordenação');
    }

    public function test_historico_sobrevive_quando_o_admin_e_removido(): void
    {
        $admin = $this->comoAdmin();
        $espaco = Espaco::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $admin->delete();

        $this->assertNull($espaco->fresh()->criado_por);
        $this->assertNull($this->historico($espaco)->first()->user_id);
    }
}
