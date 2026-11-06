<?php

namespace Tests\Feature;

use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Mail\ReservaRevogadaMail;
use App\Models\Aluno;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Espaco;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlunoAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_rotas_exigem_login(): void
    {
        $aluno = Aluno::factory()->create();

        $this->getJson("/api/admin/alunos/{$aluno->id}")->assertUnauthorized();
        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['nome_completo' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/admin/alunos/{$aluno->id}")->assertUnauthorized();
        $this->postJson("/api/admin/alunos/{$aluno->id}/anonimizar", ['confirmar' => true])->assertUnauthorized();
    }

    public function test_listagem_com_filtros(): void
    {
        $this->comoAdmin();

        $curso = Curso::factory()->create();
        $maria = Aluno::factory()->create(['nome_completo' => 'Maria Souza', 'curso_id' => $curso->id]);
        $joao = Aluno::factory()->create(['nome_completo' => 'João Lima', 'email' => 'joao.lima@email.com']);
        $inativo = Aluno::factory()->inativo()->create(['nome_completo' => 'Ana Costa', 'curso_id' => $curso->id]);

        $ids = fn ($params) => collect($this->getJson('/api/admin/alunos?'.http_build_query($params))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$maria->id], $ids(['busca' => 'SOUZA']));
        $this->assertSame([$joao->id], $ids(['busca' => 'joao.lima']));
        $this->assertSame([$maria->id], $ids(['busca' => substr($maria->registro_academico, 2, 6)]));
        $this->assertSame([$inativo->id, $maria->id], $ids(['curso_id' => $curso->id]));
        $this->assertSame([$inativo->id], $ids(['ativo' => 'false']));
        $this->assertSame([$maria->id], $ids(['curso_id' => $curso->id, 'ativo' => '1']));

        $this->getJson('/api/admin/alunos?ativo=talvez')->assertStatus(422)->assertJsonValidationErrors('ativo');
    }

    public function test_detalhe_mostra_reservas_futuras_e_auditoria(): void
    {
        $admin = $this->comoAdmin();
        $aluno = Aluno::factory()->create(['criado_por' => $admin->id]);

        Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-10']);
        Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-06']);
        Reserva::factory()->revogada()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-11']);

        $this->getJson("/api/admin/alunos/{$aluno->id}")->assertOk()
            ->assertJsonPath('data.ativo', true)
            ->assertJsonPath('data.reservas_futuras', 1)
            ->assertJsonPath('data.curso.id', $aluno->curso_id)
            ->assertJsonPath('data.criado_por.id', $admin->id);
    }

    public function test_edicao_de_dados_e_curso(): void
    {
        $admin = $this->comoAdmin();
        $aluno = Aluno::factory()->create();
        $outro = Aluno::factory()->create();
        $curso = Curso::factory()->create(['nome' => 'Nutrição']);

        $this->patchJson("/api/admin/alunos/{$aluno->id}", [
            'nome_completo'      => 'Nome Corrigido',
            'registro_academico' => '2024999999',
            'email'              => 'novo@email.com',
            'curso_id'           => $curso->id,
        ])->assertOk()
            ->assertJsonPath('data.nome_completo', 'Nome Corrigido')
            ->assertJsonPath('data.registro_academico', '2024999999')
            ->assertJsonPath('data.curso.nome', 'Nutrição')
            ->assertJsonPath('data.atualizado_por.id', $admin->id)
            ->assertJsonPath('reservas_revogadas', 0);

        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['registro_academico' => '2024999999'])->assertOk();
        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['registro_academico' => $outro->registro_academico])
            ->assertStatus(422)->assertJsonValidationErrors('registro_academico');
        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['email' => 'invalido'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['curso_id' => Curso::factory()->inativo()->create()->id])
            ->assertStatus(422)->assertJsonValidationErrors('curso_id');
    }

    public function test_desativar_revoga_reservas_futuras_e_avisa_o_aluno(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 19:30'));
        $admin = $this->comoAdmin();
        $aluno = Aluno::factory()->create();

        $passada = Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-09']);
        $hojeEmAndamento = Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-10']);
        $amanha = Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-11']);
        $deOutro = Reserva::factory()->create(['data_reserva' => '2026-11-11']);

        $this->deleteJson("/api/admin/alunos/{$aluno->id}")->assertOk()
            ->assertJsonPath('data.ativo', false)
            ->assertJsonPath('reservas_revogadas', 2);

        $this->assertSame(StatusConstant::RESERVADO, $passada->fresh()->status);
        $this->assertSame(StatusConstant::REVOGADO, $hojeEmAndamento->fresh()->status);
        $this->assertSame(StatusConstant::REVOGADO, $amanha->fresh()->status);
        $this->assertSame('Cadastro do aluno desativado.', $amanha->fresh()->justificativa_revogacao);
        $this->assertSame($admin->id, $amanha->fresh()->revogada_por);
        $this->assertSame(StatusConstant::RESERVADO, $deOutro->fresh()->status);

        Mail::assertQueued(ReservaRevogadaMail::class, 2);
    }

    public function test_desativar_pelo_patch_tambem_revoga_e_reativar_funciona(): void
    {
        $this->comoAdmin();
        $aluno = Aluno::factory()->create();
        Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-10']);

        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['ativo' => false])->assertOk()->assertJsonPath('reservas_revogadas', 1);
        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['ativo' => true])->assertOk()
            ->assertJsonPath('data.ativo', true)
            ->assertJsonPath('reservas_revogadas', 0);
    }

    public function test_aluno_inativo_nao_reserva_nem_consulta_com_ra(): void
    {
        $aluno = Aluno::factory()->inativo()->create();
        $espaco = Espaco::factory()->create();

        $this->postJson('/api/reservas', [
            'registro_academico' => $aluno->registro_academico,
            'espaco_id'          => $espaco->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => 'PRIMEIRO_HORARIO',
        ])->assertStatus(422)->assertJsonValidationErrors('registro_academico');

        $this->getJson("/api/espacos/{$espaco->id}/disponibilidade?de=2026-11-10&registro_academico={$aluno->registro_academico}")
            ->assertStatus(422)->assertJsonValidationErrors('registro_academico');
    }

    public function test_mudanca_de_curso_leva_as_reservas_para_o_limite_do_curso_novo(): void
    {
        $this->comoAdmin();
        $quadra = Espaco::factory()->quadra(1)->create();
        $cursoNovo = Curso::factory()->create();
        $aluno = Aluno::factory()->create();
        Reserva::factory()->create(['aluno_id' => $aluno->id, 'espaco_id' => $quadra->id, 'data_reserva' => '2026-11-10']);

        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['curso_id' => $cursoNovo->id])->assertOk();

        $this->postJson('/api/reservas', [
            'registro_academico' => Aluno::factory()->create(['curso_id' => $cursoNovo->id])->registro_academico,
            'espaco_id'          => $quadra->id,
            'data_reserva'       => '2026-11-11',
            'horario'            => 'PRIMEIRO_HORARIO',
        ])->assertStatus(409);
    }

    public function test_anonimizacao_exige_confirmacao(): void
    {
        $this->comoAdmin();
        $aluno = Aluno::factory()->create();

        $this->postJson("/api/admin/alunos/{$aluno->id}/anonimizar")->assertStatus(422)->assertJsonValidationErrors('confirmar');
        $this->assertNull($aluno->fresh()->anonimizado_em);
    }

    public function test_anonimizacao_remove_dados_pessoais_e_mantem_as_reservas(): void
    {
        $this->comoAdmin();
        $aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza', 'registro_academico' => '2024000001', 'email' => 'maria@email.com']);
        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['email' => 'maria.souza@email.com'])->assertOk();

        $passada = Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-06']);
        $futura = Reserva::factory()->create(['aluno_id' => $aluno->id, 'data_reserva' => '2026-11-10']);

        $this->postJson("/api/admin/alunos/{$aluno->id}/anonimizar", ['confirmar' => true])->assertOk()
            ->assertJsonPath('data.nome_completo', 'Aluno anonimizado')
            ->assertJsonPath('data.registro_academico', 'A'.str_pad((string) $aluno->id, 9, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.ativo', false);

        $this->assertNotNull($aluno->fresh()->anonimizado_em);
        $this->assertSame(StatusConstant::RESERVADO, $passada->fresh()->status);
        $this->assertSame(StatusConstant::REVOGADO, $futura->fresh()->status);
        $this->assertSame(TipoReservaConstant::ALUNO, $passada->fresh()->tipo);
        Mail::assertNothingQueued();

        $historico = Auditoria::where('auditavel_type', Aluno::class)->where('auditavel_id', $aluno->id)->get()->toJson();
        $this->assertStringNotContainsString('Maria Souza', $historico);
        $this->assertStringNotContainsString('2024000001', $historico);
        $this->assertStringNotContainsString('maria', $historico);
        $this->assertStringContainsString('[anonimizado]', $historico);
    }

    public function test_aluno_anonimizado_nao_pode_ser_alterado(): void
    {
        $this->comoAdmin();
        $aluno = Aluno::factory()->create();

        $this->postJson("/api/admin/alunos/{$aluno->id}/anonimizar", ['confirmar' => true])->assertOk();

        $this->patchJson("/api/admin/alunos/{$aluno->id}", ['ativo' => true])->assertStatus(409);
        $this->postJson("/api/admin/alunos/{$aluno->id}/anonimizar", ['confirmar' => true])->assertStatus(409);
        $this->deleteJson("/api/admin/alunos/{$aluno->id}")->assertStatus(409);
    }

    public function test_ra_original_fica_livre_para_novo_cadastro(): void
    {
        $this->comoAdmin();
        $curso = Curso::factory()->create();
        $aluno = Aluno::factory()->create(['registro_academico' => '2024000001', 'curso_id' => $curso->id]);

        $this->postJson("/api/admin/alunos/{$aluno->id}/anonimizar", ['confirmar' => true])->assertOk();

        $this->postJson('/api/alunos', [
            'nome_completo'      => 'Maria Souza',
            'registro_academico' => '2024000001',
            'email'              => 'maria@email.com',
            'curso_id'           => $curso->id,
        ])->assertCreated();
    }
}
