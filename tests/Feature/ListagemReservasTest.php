<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListagemReservasTest extends TestCase
{
    use RefreshDatabase;

    private function listar(array $params = [])
    {
        return $this->getJson('/api/admin/reservas?' . http_build_query($params));
    }

    private function ids($resposta): array
    {
        return collect($resposta->json('data'))->pluck('id')->all();
    }

    public function test_exige_login(): void
    {
        $this->listar()->assertUnauthorized();
        $this->getJson('/api/admin/reservas/1')->assertUnauthorized();
    }

    public function test_sem_filtro_de_data_mostra_de_hoje_em_diante_em_ordem_crescente(): void
    {
        $this->comoAdmin();

        $passada = Reserva::factory()->create(['data_reserva' => '2026-11-06']);
        $depois = Reserva::factory()->create(['data_reserva' => '2026-11-12']);
        $segundoHorario = Reserva::factory()->noHorario(HorarioReservaConstant::SEGUNDO_HORARIO)->create(['data_reserva' => '2026-11-10']);
        $primeiroHorario = Reserva::factory()->create(['data_reserva' => '2026-11-10']);

        $this->assertSame([$primeiroHorario->id, $segundoHorario->id, $depois->id], $this->ids($this->listar()->assertOk()));
        $this->assertSame([$depois->id, $segundoHorario->id, $primeiroHorario->id], $this->ids($this->listar(['ordem' => 'desc'])));
        $this->assertContains($passada->id, $this->ids($this->listar(['de' => '2026-11-01'])));
    }

    public function test_filtro_por_periodo(): void
    {
        $this->comoAdmin();

        Reserva::factory()->create(['data_reserva' => '2026-11-10']);
        $dentro = Reserva::factory()->create(['data_reserva' => '2026-11-11']);
        Reserva::factory()->create(['data_reserva' => '2026-11-12']);

        $this->assertSame([$dentro->id], $this->ids($this->listar(['de' => '2026-11-11', 'ate' => '2026-11-11'])));
    }

    public function test_filtros_por_espaco_tipo_de_espaco_status_tipo_e_horario(): void
    {
        $this->comoAdmin();

        $quadra = Espaco::factory()->quadra()->create();
        $laboratorio = Espaco::factory()->laboratorio()->create();

        $naQuadra = Reserva::factory()->create(['espaco_id' => $quadra->id]);
        $noLaboratorio = Reserva::factory()->create(['espaco_id' => $laboratorio->id]);
        $revogada = Reserva::factory()->revogada()->create(['espaco_id' => $laboratorio->id]);
        $evento = Reserva::factory()->evento()->noHorario(HorarioReservaConstant::SEGUNDO_HORARIO)->create(['espaco_id' => $laboratorio->id]);

        $this->assertSame([$naQuadra->id], $this->ids($this->listar(['espaco_id' => $quadra->id])));
        $this->assertSame([$naQuadra->id], $this->ids($this->listar(['tipo_espaco' => 'QUADRA'])));
        $this->assertSame([$revogada->id], $this->ids($this->listar(['status' => 'REVOGADO'])));
        $this->assertSame([$evento->id], $this->ids($this->listar(['tipo' => 'EVENTO'])));
        $this->assertSame([$evento->id], $this->ids($this->listar(['horario' => 'SEGUNDO_HORARIO'])));
        $this->assertSame([$noLaboratorio->id], $this->ids($this->listar(['espaco_id' => $laboratorio->id, 'status' => 'RESERVADO', 'tipo' => 'ALUNO'])));
    }

    public function test_filtros_por_curso_e_busca_por_nome_ou_ra(): void
    {
        $this->comoAdmin();

        $curso = Curso::factory()->create();
        $maria = Aluno::factory()->create(['nome_completo' => 'Maria Souza', 'registro_academico' => '2024000001', 'curso_id' => $curso->id]);
        $joao = Aluno::factory()->create(['nome_completo' => 'João Lima', 'registro_academico' => '2024000002']);

        $daMaria = Reserva::factory()->create(['aluno_id' => $maria->id]);
        $doJoao = Reserva::factory()->create(['aluno_id' => $joao->id]);
        Reserva::factory()->evento()->create();

        $this->assertSame([$daMaria->id], $this->ids($this->listar(['curso_id' => $curso->id])));
        $this->assertSame([$daMaria->id], $this->ids($this->listar(['busca' => 'souza'])));
        $this->assertSame([$doJoao->id], $this->ids($this->listar(['busca' => '0002'])));
        $this->assertSame([], $this->ids($this->listar(['busca' => 'souza', 'curso_id' => Curso::factory()->create()->id])));
    }

    public function test_resposta_traz_aluno_espaco_equipamentos_e_revogacao(): void
    {
        $admin = $this->comoAdmin();

        $aluno = Aluno::factory()->create();
        $reserva = Reserva::factory()->revogada()->create(['aluno_id' => $aluno->id, 'revogada_por' => $admin->id]);
        $reserva->equipamentos()->attach(Equipamento::factory()->create(['nome' => 'Bola'])->id, ['quantidade' => 2]);

        $this->listar()->assertOk()
            ->assertJsonPath('data.0.aluno.registro_academico', $aluno->registro_academico)
            ->assertJsonPath('data.0.aluno.email', $aluno->email)
            ->assertJsonPath('data.0.aluno.curso.id', $aluno->curso_id)
            ->assertJsonPath('data.0.espaco.id', $reserva->espaco_id)
            ->assertJsonPath('data.0.equipamentos.0.nome', 'Bola')
            ->assertJsonPath('data.0.equipamentos.0.quantidade', 2)
            ->assertJsonPath('data.0.revogada_por.id', $admin->id);
    }

    public function test_evento_traz_quem_criou(): void
    {
        $this->comoAdmin();
        $criador = User::factory()->create(['name' => 'Coordenação']);
        $evento = Reserva::factory()->evento()->create(['criado_por' => $criador->id]);

        $this->getJson("/api/admin/reservas/{$evento->id}")->assertOk()
            ->assertJsonPath('data.criado_por.nome', 'Coordenação')
            ->assertJsonPath('data.aluno', null);
    }

    public function test_totais_por_status_ignoram_o_filtro_de_status(): void
    {
        $this->comoAdmin();

        Reserva::factory()->count(2)->create();
        Reserva::factory()->revogada()->create();

        $this->listar(['status' => 'REVOGADO'])->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('totais_por_status', ['RESERVADO' => 2, 'CANCELADO' => 0, 'REVOGADO' => 1]);
    }

    public function test_paginacao(): void
    {
        $this->comoAdmin();

        Reserva::factory()->count(5)->create();

        $this->listar(['per_page' => 2, 'page' => 3])->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_numero_de_consultas_nao_cresce_com_as_reservas(): void
    {
        $this->comoAdmin();

        $contar = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->listar()->assertOk();

            return count(DB::getQueryLog());
        };

        Reserva::factory()->count(2)->create();
        Reserva::factory()->evento()->create();
        $poucas = $contar();

        Reserva::factory()->count(10)->create();
        Reserva::factory()->evento()->count(3)->create();

        $this->assertSame($poucas, $contar());
    }

    public function test_validacoes_dos_filtros(): void
    {
        $this->comoAdmin();

        $this->listar(['de' => '10/11/2026'])->assertStatus(422)->assertJsonValidationErrors('de');
        $this->listar(['de' => '2026-11-10', 'ate' => '2026-11-09'])->assertStatus(422)->assertJsonValidationErrors('ate');
        $this->listar(['status' => 'APAGADO'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->listar(['tipo_espaco' => 'PISCINA'])->assertStatus(422)->assertJsonValidationErrors('tipo_espaco');
        $this->listar(['espaco_id' => 999])->assertStatus(422)->assertJsonValidationErrors('espaco_id');
        $this->listar(['ordem' => 'aleatoria'])->assertStatus(422)->assertJsonValidationErrors('ordem');
        $this->listar(['per_page' => 500])->assertStatus(422)->assertJsonValidationErrors('per_page');
    }

    public function test_show_de_reserva_inexistente(): void
    {
        $this->comoAdmin();

        $this->getJson('/api/admin/reservas/999')->assertNotFound();
    }
}
