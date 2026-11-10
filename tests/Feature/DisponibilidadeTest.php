<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\DataBloqueada;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quadra = Espaco::factory()->quadra(2)->create();
    }

    private function consultar(array $params, ?Espaco $espaco = null)
    {
        return $this->getJson('/api/espacos/'.($espaco ?? $this->quadra)->id.'/disponibilidade?'.http_build_query($params));
    }

    private function consultarComRa(array $dados, ?Espaco $espaco = null)
    {
        return $this->postJson('/api/espacos/'.($espaco ?? $this->quadra)->id.'/disponibilidade/curso', $dados);
    }

    private function reservar(string $data, ?Aluno $aluno = null, HorarioReservaConstant $horario = HorarioReservaConstant::PRIMEIRO_HORARIO): Reserva
    {
        return Reserva::factory()->noHorario($horario)->create([
            'data_reserva' => $data,
            'espaco_id'    => $this->quadra->id,
            'aluno_id'     => ($aluno ?? Aluno::factory()->create())->id,
        ]);
    }

    public function test_mostra_horario_livre_e_reservado_com_quem_reservou(): void
    {
        $aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza']);
        $this->reservar('2026-11-10', $aluno);

        $resposta = $this->consultar(['de' => '2026-11-10'])->assertOk()
            ->assertJsonCount(1, 'data.dias')
            ->assertJsonPath('data.dias.0.reservavel', true)
            ->assertJsonPath('data.dias.0.horarios.0.situacao', 'RESERVADO')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.reservado_por.tipo', 'ALUNO')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.reservado_por.nome', 'Maria Souza')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.reservado_por.curso', $aluno->curso->nome)
            ->assertJsonPath('data.dias.0.horarios.1.situacao', 'LIVRE')
            ->assertJsonPath('data.dias.0.horarios.1.reserva', null);

        $this->assertStringNotContainsString($aluno->registro_academico, $resposta->getContent());
        $this->assertStringNotContainsString($aluno->email, $resposta->getContent());
    }

    public function test_evento_mostra_admin_e_descricao(): void
    {
        $admin = User::factory()->create(['name' => 'Coordenação']);
        Reserva::factory()->evento()->create([
            'data_reserva' => '2026-11-10',
            'espaco_id'    => $this->quadra->id,
            'criado_por'   => $admin->id,
            'descricao'    => 'Palestra de Enfermagem',
        ]);

        $this->consultar(['de' => '2026-11-10'])->assertOk()
            ->assertJsonPath('data.dias.0.horarios.0.situacao', 'EVENTO')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.descricao', 'Palestra de Enfermagem')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.reservado_por.tipo', 'ADMIN')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.reservado_por.nome', 'Coordenação');
    }

    public function test_reservas_revogadas_nao_ocupam_horario(): void
    {
        Reserva::factory()->revogada()->create(['data_reserva' => '2026-11-10', 'espaco_id' => $this->quadra->id]);

        $this->consultar(['de' => '2026-11-10'])->assertJsonPath('data.dias.0.horarios.0.situacao', 'LIVRE');
    }

    public function test_bloqueios_de_dia_inteiro_e_de_parte_do_dia(): void
    {
        DataBloqueada::factory()->entre('2026-11-10', '2026-11-10')->create(['motivo' => 'Recesso']);
        DataBloqueada::factory()->entre('2026-11-11', '2026-11-11')->dasHoras('20:30', '22:00')
            ->create(['espaco_id' => $this->quadra->id, 'motivo' => 'Manutenção']);

        $this->consultar(['de' => '2026-11-10', 'ate' => '2026-11-11'])->assertOk()
            ->assertJsonPath('data.dias.0.reservavel', false)
            ->assertJsonPath('data.dias.0.motivo', 'Recesso')
            ->assertJsonPath('data.dias.0.horarios.0.situacao', 'BLOQUEADO')
            ->assertJsonPath('data.dias.0.horarios.1.situacao', 'BLOQUEADO')
            ->assertJsonPath('data.dias.1.reservavel', true)
            ->assertJsonPath('data.dias.1.horarios.0.situacao', 'LIVRE')
            ->assertJsonPath('data.dias.1.horarios.1.situacao', 'BLOQUEADO')
            ->assertJsonPath('data.dias.1.horarios.1.motivo', 'Manutenção');
    }

    public function test_calendario_mostra_motivos_fora_da_janela_de_reserva(): void
    {
        $resposta = $this->consultar(['de' => '2026-11-06', 'ate' => '2026-11-24'])->assertOk()
            ->assertJsonCount(19, 'data.dias')
            ->assertJsonPath('data.reservas_ate', '2026-11-23');

        $dias = collect($resposta->json('data.dias'))->keyBy('data');

        $this->assertSame('Data passada.', $dias['2026-11-06']['motivo']);
        $this->assertSame('Fim de semana.', $dias['2026-11-14']['motivo']);
        $this->assertSame('INDISPONIVEL', $dias['2026-11-14']['horarios'][0]['situacao']);
        $this->assertTrue($dias['2026-11-23']['reservavel']);
        $this->assertSame('Reservas abertas até 23/11/2026.', $dias['2026-11-24']['motivo']);
    }

    public function test_horario_de_hoje_encerrado_apos_o_limite(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 20:11'));

        $this->consultar(['de' => '2026-11-09'])->assertOk()
            ->assertJsonPath('data.dias.0.horarios.0.situacao', 'ENCERRADO')
            ->assertJsonPath('data.dias.0.horarios.0.motivo', 'Reservas para hoje são aceitas até 20:10.')
            ->assertJsonPath('data.dias.0.horarios.1.situacao', 'LIVRE');
    }

    public function test_equipamentos_disponiveis_por_horario_incluindo_compartilhaveis(): void
    {
        $bola = Equipamento::factory()->for($this->quadra)->create(['nome' => 'Bola', 'quantidade_total' => 10]);
        $laboratorio = Espaco::factory()->laboratorio()->create();
        $microscopio = Equipamento::factory()->compartilhavel()->for($laboratorio)->create(['nome' => 'Microscópio', 'quantidade_total' => 5]);
        Equipamento::factory()->for($laboratorio)->create(['nome' => 'Pipeta']);

        $this->reservar('2026-11-10')->equipamentos()->attach($bola->id, ['quantidade' => 3]);
        Reserva::factory()->create(['data_reserva' => '2026-11-10', 'espaco_id' => $laboratorio->id])
            ->equipamentos()->attach($microscopio->id, ['quantidade' => 2]);

        $resposta = $this->consultar(['de' => '2026-11-10'])->assertOk();

        $primeiro = collect($resposta->json('data.dias.0.horarios.0.equipamentos'))->keyBy('nome');
        $segundo = collect($resposta->json('data.dias.0.horarios.1.equipamentos'))->keyBy('nome');

        $this->assertSame(['Bola', 'Microscópio'], $primeiro->keys()->sort()->values()->all());
        $this->assertSame(7, $primeiro['Bola']['disponivel']);
        $this->assertSame(3, $primeiro['Microscópio']['disponivel']);
        $this->assertSame(10, $segundo['Bola']['disponivel']);
        $this->assertSame(5, $segundo['Microscópio']['disponivel']);
    }

    public function test_com_ra_por_post_mostra_todas_as_reservas_e_o_limite_do_curso(): void
    {
        $curso = Curso::factory()->create();
        $aluno = Aluno::factory()->create(['curso_id' => $curso->id]);
        $outroCurso = Aluno::factory()->create();

        $this->reservar('2026-11-10', Aluno::factory()->create(['curso_id' => $curso->id]));
        $this->reservar('2026-11-11', Aluno::factory()->create(['curso_id' => $curso->id]));
        $this->reservar('2026-11-12', $outroCurso);

        $resposta = $this->consultarComRa(['de' => '2026-11-12', 'ate' => '2026-11-16', 'registro_academico' => $aluno->registro_academico])
            ->assertOk()
            ->assertJsonMissingPath('data.curso.id')
            ->assertJsonMissingPath('data.curso.nome')
            ->assertJsonPath('data.curso.limite_semanal', 2)
            ->assertJsonPath('data.curso.semanas.0.inicio', '2026-11-09')
            ->assertJsonPath('data.curso.semanas.0.reservas', 2)
            ->assertJsonPath('data.curso.semanas.0.restantes', 0)
            ->assertJsonPath('data.curso.semanas.1.restantes', 2);

        $dias = collect($resposta->json('data.dias'))->keyBy('data');

        $this->assertSame('RESERVADO', $dias['2026-11-12']['horarios'][0]['situacao']);
        $this->assertFalse($dias['2026-11-12']['horarios'][0]['reserva']['do_curso']);
        $this->assertSame('LIVRE', $dias['2026-11-12']['horarios'][1]['situacao']);
        $this->assertFalse($dias['2026-11-12']['horarios'][1]['disponivel_para_o_curso']);
        $this->assertTrue($dias['2026-11-16']['horarios'][0]['disponivel_para_o_curso']);
    }

    public function test_filtro_por_curso_marca_reservas_do_curso(): void
    {
        $curso = Curso::factory()->create();
        $this->reservar('2026-11-10', Aluno::factory()->create(['curso_id' => $curso->id]));

        $this->consultar(['de' => '2026-11-10', 'curso_id' => $curso->id])->assertOk()
            ->assertJsonPath('data.curso.semanas.0.restantes', 1)
            ->assertJsonPath('data.dias.0.horarios.0.reserva.do_curso', true)
            ->assertJsonPath('data.dias.0.horarios.1.disponivel_para_o_curso', true);
    }

    public function test_sem_curso_nao_mostra_dados_do_curso(): void
    {
        $this->consultar(['de' => '2026-11-10'])->assertOk()
            ->assertJsonPath('data.curso', null)
            ->assertJsonMissingPath('data.dias.0.horarios.0.disponivel_para_o_curso');
    }

    public function test_validacoes_da_consulta(): void
    {
        $this->consultar([])->assertStatus(422)->assertJsonValidationErrors('de');
        $this->consultar(['de' => '2026-11-10', 'ate' => '2026-11-09'])->assertStatus(422)->assertJsonValidationErrors('ate');
        $this->consultar(['de' => '2026-11-01', 'ate' => '2026-12-02'])->assertStatus(422)->assertJsonValidationErrors('ate');
    }

    public function test_consulta_publica_por_get_ignora_o_ra(): void
    {
        $aluno = Aluno::factory()->create();

        $this->consultar(['de' => '2026-11-10', 'registro_academico' => $aluno->registro_academico])->assertOk()
            ->assertJsonPath('data.curso', null);
    }

    public function test_consulta_com_ra_so_aceita_post(): void
    {
        $this->getJson("/api/espacos/{$this->quadra->id}/disponibilidade/curso?de=2026-11-10")->assertStatus(405);
    }

    public function test_consulta_com_ra_so_em_espaco_com_limite_por_curso(): void
    {
        $sala = Espaco::factory()->create(['limite_semanal_por_curso' => null]);

        $this->consultarComRa(['de' => '2026-11-10', 'registro_academico' => Aluno::factory()->create()->registro_academico], $sala)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['registro_academico' => 'Este espaço não tem limite semanal por curso.']);
    }

    public function test_validacoes_da_consulta_com_ra(): void
    {
        $mensagem = 'Não encontramos um aluno ativo com este RA.';

        $this->consultarComRa(['de' => '2026-11-10'])->assertStatus(422)->assertJsonValidationErrors('registro_academico');
        $this->consultarComRa(['registro_academico' => '2024000001'])->assertStatus(422)->assertJsonValidationErrors('de');
        $this->consultarComRa(['de' => '2026-11-10', 'registro_academico' => '123'])->assertStatus(422)->assertJsonValidationErrors('registro_academico');
        $this->consultarComRa(['de' => '2026-11-10', 'registro_academico' => '2099999999'])
            ->assertStatus(422)->assertJsonValidationErrors(['registro_academico' => $mensagem]);
        $this->consultarComRa(['de' => '2026-11-10', 'registro_academico' => Aluno::factory()->inativo()->create()->registro_academico])
            ->assertStatus(422)->assertJsonValidationErrors(['registro_academico' => $mensagem]);
        $this->consultarComRa(['de' => '2026-11-01', 'ate' => '2026-12-02', 'registro_academico' => Aluno::factory()->create()->registro_academico])
            ->assertStatus(422)->assertJsonValidationErrors('ate');
    }

    public function test_consulta_com_ra_em_espaco_inativo_retorna_404(): void
    {
        $espaco = Espaco::factory()->inativo()->create(['limite_semanal_por_curso' => 3]);

        $this->consultarComRa(['de' => '2026-11-10', 'registro_academico' => Aluno::factory()->create()->registro_academico], $espaco)
            ->assertNotFound();
    }

    public function test_espaco_inativo_retorna_404(): void
    {
        $this->consultar(['de' => '2026-11-10'], Espaco::factory()->inativo()->create())->assertNotFound();
    }
}
