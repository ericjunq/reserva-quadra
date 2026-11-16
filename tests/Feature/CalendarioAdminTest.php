<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Models\Aluno;
use App\Models\DataBloqueada;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarioAdminTest extends TestCase
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
        return $this->getJson('/api/admin/espacos/'.($espaco ?? $this->quadra)->id.'/disponibilidade?'.http_build_query($params));
    }

    public function test_exige_login(): void
    {
        $this->consultar(['de' => '2026-11-09'])->assertUnauthorized();
    }

    public function test_mostra_quem_reservou_e_a_situacao_da_confirmacao(): void
    {
        $this->comoAdmin();
        $aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza']);
        Reserva::factory()->create([
            'data_reserva'  => '2026-11-10',
            'espaco_id'     => $this->quadra->id,
            'aluno_id'      => $aluno->id,
            'confirmada_em' => now(),
        ]);
        $admin = User::factory()->create(['name' => 'Coordenação']);
        Reserva::factory()->evento()->noHorario(HorarioReservaConstant::SEGUNDO_HORARIO)->create([
            'data_reserva' => '2026-11-10',
            'espaco_id'    => $this->quadra->id,
            'criado_por'   => $admin->id,
            'descricao'    => 'Palestra',
        ]);

        $resposta = $this->consultar(['de' => '2026-11-10'])->assertOk()
            ->assertJsonPath('data.dias.0.horarios.0.reserva.reservado_por.nome', 'Maria Souza')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.reservado_por.curso', $aluno->curso->nome)
            ->assertJsonPath('data.dias.0.horarios.0.reserva.situacao_confirmacao', 'CONFIRMADA')
            ->assertJsonPath('data.dias.0.horarios.0.reserva.origem', 'SITE')
            ->assertJsonPath('data.dias.0.horarios.1.reserva.origem', 'ADMIN')
            ->assertJsonPath('data.dias.0.horarios.1.reserva.reservado_por.tipo', 'ADMIN')
            ->assertJsonPath('data.dias.0.horarios.1.reserva.reservado_por.nome', 'Coordenação')
            ->assertJsonPath('data.dias.0.horarios.1.reserva.descricao', 'Palestra')
            ->assertJsonPath('data.dias.0.horarios.1.reserva.situacao_confirmacao', null);

        $this->assertStringNotContainsString($aluno->registro_academico, $resposta->getContent());
        $this->assertStringNotContainsString($aluno->email, $resposta->getContent());
    }

    public function test_horario_livre_tambem_identifica_quem_reservou(): void
    {
        $this->comoAdmin();
        $lab = Espaco::factory()->laboratorio()->livre('07:00', '22:00', 180)->create();
        $aluno = Aluno::factory()->create(['nome_completo' => 'João Lima']);
        Reserva::factory()->create([
            'data_reserva' => '2026-11-12',
            'espaco_id'    => $lab->id,
            'aluno_id'     => $aluno->id,
            'horario'      => null,
            'hora_inicio'  => '14:00:00',
            'hora_fim'     => '15:30:00',
        ]);

        $this->consultar(['de' => '2026-11-12'], $lab)->assertOk()
            ->assertJsonPath('data.dias.0.ocupados.0.reserva.reservado_por.nome', 'João Lima')
            ->assertJsonPath('data.dias.0.ocupados.0.reserva.situacao_confirmacao', 'AGUARDANDO_CONFIRMACAO');
    }

    public function test_bloqueio_do_dia_inteiro_ocupa_a_janela_no_horario_livre(): void
    {
        $this->comoAdmin();
        $lab = Espaco::factory()->laboratorio()->livre('07:00', '22:00', 180)->create();
        DataBloqueada::factory()->entre('2026-11-12', '2026-11-12')->create(['motivo' => 'Recesso']);

        $this->consultar(['de' => '2026-11-12'], $lab)->assertOk()
            ->assertJsonPath('data.dias.0.ocupados.0.inicio', '07:00')
            ->assertJsonPath('data.dias.0.ocupados.0.fim', '22:00')
            ->assertJsonPath('data.dias.0.ocupados.0.motivo', 'Recesso');

        $this->getJson("/api/espacos/{$lab->id}/disponibilidade?de=2026-11-12")->assertOk()
            ->assertJsonCount(0, 'data.dias.0.ocupados');
    }

    public function test_ve_o_passado_e_depois_do_fim_do_semestre_ate_31_dias(): void
    {
        $this->comoAdmin();
        Reserva::factory()->create(['data_reserva' => '2026-10-05', 'espaco_id' => $this->quadra->id]);

        $this->consultar(['de' => '2026-10-05', 'ate' => '2026-10-11'])->assertOk()
            ->assertJsonCount(7, 'data.dias')
            ->assertJsonPath('data.dias.0.horarios.0.situacao', 'RESERVADO');

        $this->consultar(['de' => '2027-03-01', 'ate' => '2027-03-07'])->assertOk()
            ->assertJsonCount(7, 'data.dias')
            ->assertJsonPath('data.ate', '2027-03-07');

        $this->consultar(['de' => '2026-11-01', 'ate' => '2026-12-15'])->assertUnprocessable()->assertJsonValidationErrors('ate');
        $this->consultar(['de' => '09/11/2026'])->assertUnprocessable()->assertJsonValidationErrors('de');
    }

    public function test_espaco_inativo_pode_ser_consultado_pelo_admin(): void
    {
        $this->comoAdmin();
        $inativo = Espaco::factory()->quadra()->create(['ativo' => false]);

        $this->consultar(['de' => '2026-11-10'], $inativo)->assertOk();
        $this->getJson("/api/espacos/{$inativo->id}/disponibilidade?de=2026-11-10")->assertNotFound();
    }

    public function test_numero_de_consultas_nao_cresce_com_as_reservas(): void
    {
        $this->comoAdmin();
        $contar = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->consultar(['de' => '2026-11-09', 'ate' => '2026-11-15'])->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        Reserva::factory()->create(['data_reserva' => '2026-11-10', 'espaco_id' => $this->quadra->id]);
        $poucas = $contar();

        foreach (['2026-11-11', '2026-11-12', '2026-11-13'] as $data) {
            Reserva::factory()->create(['data_reserva' => $data, 'espaco_id' => $this->quadra->id]);
            Reserva::factory()->evento()->noHorario(HorarioReservaConstant::SEGUNDO_HORARIO)->create(['data_reserva' => $data, 'espaco_id' => $this->quadra->id]);
        }

        $this->assertSame($poucas, $contar());
    }
}
