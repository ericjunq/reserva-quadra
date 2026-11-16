<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Mail\UsoRegistradoMail;
use App\Models\Aluno;
use App\Models\DataBloqueada;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistroPosteriorTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;

    private Espaco $lab;

    private Aluno $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->quadra = Espaco::factory()->quadra(1)->create();
        $this->lab = Espaco::factory()->laboratorio()->livre('07:00', '22:00', 180)->create();
        $this->aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza']);
    }

    private function registrar(array $dados)
    {
        return $this->postJson('/api/admin/reservas/registro-posterior', $dados);
    }

    private function doAluno(array $extra = []): array
    {
        return [
            'espaco_id'          => $this->lab->id,
            'data_reserva'       => '2026-11-06',
            'hora_inicio'        => '14:00',
            'hora_fim'           => '15:30',
            'registro_academico' => $this->aluno->registro_academico,
            ...$extra,
        ];
    }

    public function test_exige_login(): void
    {
        $this->registrar($this->doAluno())->assertUnauthorized();
    }

    public function test_registra_uso_passado_do_aluno_sem_confirmacao_e_avisa_por_email(): void
    {
        $admin = $this->comoAdmin();

        $resposta = $this->registrar($this->doAluno())->assertCreated()
            ->assertJsonPath('data.origem', 'REGISTRO_POSTERIOR')
            ->assertJsonPath('data.tipo', 'ALUNO')
            ->assertJsonPath('data.aluno.nome_completo', 'Maria Souza')
            ->assertJsonPath('data.situacao_confirmacao', null)
            ->assertJsonPath('data.confirmar_ate', null)
            ->assertJsonPath('data.criado_por.id', $admin->id)
            ->assertJsonPath('aviso', null);

        Mail::assertQueued(UsoRegistradoMail::class, fn (UsoRegistradoMail $mail) => $mail->hasTo($this->aluno->email));

        $html = (new UsoRegistradoMail(Reserva::find($resposta->json('data.id'))->load('aluno', 'espaco')))->render();
        $this->assertStringContainsString('06/11/2026', $html);
        $this->assertStringContainsString('14:00', $html);
    }

    public function test_grupo_sem_ra_vira_registro_com_descricao_e_sem_email(): void
    {
        $this->comoAdmin();

        $this->registrar([
            'espaco_id'    => $this->quadra->id,
            'data_reserva' => '2026-11-07',
            'horario'      => HorarioReservaConstant::PRIMEIRO_HORARIO->value,
            'descricao'    => 'Turma de Enfermagem',
        ])->assertCreated()
            ->assertJsonPath('data.tipo', 'EVENTO')
            ->assertJsonPath('data.origem', 'REGISTRO_POSTERIOR')
            ->assertJsonPath('data.descricao', 'Turma de Enfermagem');

        Mail::assertNothingQueued();
    }

    public function test_ra_ou_descricao_um_dos_dois(): void
    {
        $this->comoAdmin();

        $this->registrar($this->doAluno(['registro_academico' => null]))->assertUnprocessable()
            ->assertJsonPath('errors.registro_academico.0', 'Informe o RA do aluno ou a descrição do grupo.');
        $this->registrar($this->doAluno(['descricao' => 'Turma']))->assertUnprocessable()
            ->assertJsonValidationErrors('registro_academico');
        $this->registrar($this->doAluno(['registro_academico' => '0000000000']))->assertUnprocessable()
            ->assertJsonPath('errors.registro_academico.0', 'Não há aluno ativo com esse RA.');
    }

    public function test_so_os_ultimos_30_dias_e_hoje_so_o_que_ja_comecou(): void
    {
        $this->comoAdmin();

        $this->registrar($this->doAluno(['data_reserva' => '2026-10-09']))->assertUnprocessable()
            ->assertJsonPath('errors.data_reserva.0', 'Só dá para registrar usos dos últimos 30 dias.');
        $this->registrar($this->doAluno(['data_reserva' => '2026-11-10']))->assertUnprocessable()
            ->assertJsonPath('errors.data_reserva.0', 'Para datas futuras, use a reserva ou o evento.');
        $this->registrar($this->doAluno(['data_reserva' => '2026-11-09', 'hora_inicio' => '10:30', 'hora_fim' => '12:00']))->assertUnprocessable()
            ->assertJsonPath('errors.hora_inicio.0', 'Para hoje, só dá para registrar um uso que já começou.');

        $this->registrar($this->doAluno(['data_reserva' => '2026-10-10']))->assertCreated();
        $this->registrar($this->doAluno(['data_reserva' => '2026-11-09', 'hora_inicio' => '09:00', 'hora_fim' => '11:00']))->assertCreated();
    }

    public function test_aceita_fim_de_semana_e_fora_da_janela_mas_nao_bloqueio(): void
    {
        $this->comoAdmin();

        $this->registrar($this->doAluno(['data_reserva' => '2026-11-08', 'hora_inicio' => '06:00', 'hora_fim' => '07:30']))->assertCreated();

        DataBloqueada::factory()->entre('2026-11-05', '2026-11-05')->create(['motivo' => 'Recesso']);
        $this->registrar($this->doAluno(['data_reserva' => '2026-11-05']))->assertUnprocessable()
            ->assertJsonValidationErrors('data_reserva');
    }

    public function test_conflito_responde_409_sem_revogar(): void
    {
        $this->comoAdmin();
        $existente = Reserva::factory()->create([
            'espaco_id'    => $this->lab->id,
            'data_reserva' => '2026-11-06',
            'horario'      => null,
            'hora_inicio'  => '15:00:00',
            'hora_fim'     => '16:00:00',
        ]);

        $this->registrar($this->doAluno())->assertStatus(409)
            ->assertJsonPath('dados.reserva_conflitante_id', $existente->id);

        $this->assertSame('RESERVADO', $existente->fresh()->status->value);
    }

    public function test_limite_semanal_conta_mas_so_avisa(): void
    {
        $this->comoAdmin();
        $colega = Aluno::factory()->create(['curso_id' => $this->aluno->curso_id]);
        Reserva::factory()->create(['espaco_id' => $this->quadra->id, 'aluno_id' => $colega->id, 'data_reserva' => '2026-11-04']);

        $this->registrar([
            'espaco_id'          => $this->quadra->id,
            'data_reserva'       => '2026-11-05',
            'horario'            => HorarioReservaConstant::SEGUNDO_HORARIO->value,
            'registro_academico' => $this->aluno->registro_academico,
        ])->assertCreated()
            ->assertJsonPath('aviso', "O curso do aluno passou do limite semanal em {$this->quadra->nome}: 2 de 1 nesta semana.");
    }

    public function test_equipamentos_respeitam_o_estoque(): void
    {
        $this->comoAdmin();
        $microscopio = Equipamento::factory()->create(['espaco_id' => $this->lab->id, 'quantidade_total' => 1]);

        $this->registrar($this->doAluno(['equipamentos' => [['id' => $microscopio->id, 'quantidade' => 2]]]))
            ->assertUnprocessable();
        $this->registrar($this->doAluno(['equipamentos' => [['id' => $microscopio->id, 'quantidade' => 1]]]))
            ->assertCreated()->assertJsonPath('data.equipamentos.0.quantidade', 1);
    }

    public function test_nao_entra_na_confirmacao_nem_na_revogacao_automatica(): void
    {
        $this->comoAdmin();
        $id = $this->registrar($this->doAluno())->json('data.id');

        $this->travel(2)->days();
        Artisan::call('reservas:revogar-nao-confirmadas');

        $this->assertSame('RESERVADO', Reserva::find($id)->status->value);
        $this->assertSame([], collect($this->getJson('/api/admin/reservas?de=2026-11-01&ate=2026-11-30&confirmacao=AGUARDANDO_CONFIRMACAO')->json('data'))->pluck('id')->all());
    }

    public function test_filtro_e_exportacao_por_origem(): void
    {
        $this->comoAdmin();
        $registro = $this->registrar($this->doAluno())->json('data.id');
        Reserva::factory()->create(['data_reserva' => '2026-11-06']);
        Reserva::factory()->evento()->create(['data_reserva' => '2026-11-06']);

        $ids = collect($this->getJson('/api/admin/reservas?de=2026-11-01&ate=2026-11-30&origem=REGISTRO_POSTERIOR')->json('data'))->pluck('id')->all();
        $this->assertSame([$registro], $ids);
        $this->getJson('/api/admin/reservas?origem=OUTRA')->assertUnprocessable();

        $csv = $this->get('/api/admin/reservas/exportar?de=2026-11-01&ate=2026-11-30&colunas[]=origem')->streamedContent();
        $this->assertStringContainsString('Origem', $csv);
        $this->assertStringContainsString('Registro posterior', $csv);
        $this->assertStringContainsString('Evento do admin', $csv);
        $this->assertStringContainsString('Site', $csv);
    }

    public function test_calendario_publico_mostra_so_reservado(): void
    {
        $this->comoAdmin();
        $this->registrar($this->doAluno(['data_reserva' => '2026-11-09', 'hora_inicio' => '07:00', 'hora_fim' => '08:00']))->assertCreated();

        $resposta = $this->getJson("/api/espacos/{$this->lab->id}/disponibilidade?de=2026-11-09")->assertOk()
            ->assertJsonPath('data.dias.0.ocupados.0.situacao', 'RESERVADO');

        $this->assertStringNotContainsString('REGISTRO_POSTERIOR', $resposta->getContent());
        $this->assertStringNotContainsString('Maria Souza', $resposta->getContent());
    }
}
