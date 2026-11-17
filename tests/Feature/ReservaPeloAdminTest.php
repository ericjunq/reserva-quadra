<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Mail\PedidoConfirmacaoMail;
use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\ItemReserva;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservaPeloAdminTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;

    private Equipamento $projetor;

    private Aluno $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->quadra = Espaco::factory()->quadra(1)->create();
        $lab = Espaco::factory()->laboratorio()->create();
        $this->projetor = Equipamento::factory()->create([
            'espaco_id'          => $lab->id,
            'nome'               => 'Projetor',
            'quantidade_total'   => 2,
            'aceita_solicitacao' => true,
        ]);
        $this->aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza']);
    }

    private function reservar(array $extra = [])
    {
        return $this->postJson('/api/admin/reservas/aluno', [
            'registro_academico' => $this->aluno->registro_academico,
            'espaco_id'          => $this->quadra->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => HorarioReservaConstant::PRIMEIRO_HORARIO->value,
            ...$extra,
        ]);
    }

    public function test_exige_login(): void
    {
        $this->reservar()->assertUnauthorized();
    }

    public function test_admin_reserva_para_o_aluno_com_origem_admin_e_confirmacao_por_email(): void
    {
        $admin = $this->comoAdmin();

        $this->reservar()->assertCreated()
            ->assertJsonPath('data.tipo', 'ALUNO')
            ->assertJsonPath('data.origem', 'ADMIN')
            ->assertJsonPath('data.aluno.nome_completo', 'Maria Souza')
            ->assertJsonPath('data.criado_por.id', $admin->id)
            ->assertJsonPath('data.situacao_confirmacao', 'AGUARDANDO_CONFIRMACAO');

        Mail::assertQueued(PedidoConfirmacaoMail::class, fn ($mail) => $mail->hasTo($this->aluno->email));
    }

    public function test_valem_as_mesmas_regras_do_site(): void
    {
        $this->comoAdmin();

        $this->reservar(['data_reserva' => '2026-11-14'])->assertUnprocessable()->assertJsonValidationErrors('data_reserva');
        $this->reservar(['registro_academico' => '0000000000'])->assertUnprocessable()->assertJsonValidationErrors('registro_academico');

        $this->reservar()->assertCreated();
        $this->reservar()->assertStatus(409);

        $colega = Aluno::factory()->create(['curso_id' => $this->aluno->curso_id]);
        $this->reservar([
            'registro_academico' => $colega->registro_academico,
            'horario'            => HorarioReservaConstant::SEGUNDO_HORARIO->value,
        ])->assertStatus(409);
    }

    public function test_equipamento_de_outro_espaco_entra_aprovado_na_hora_mesmo_perto_do_inicio(): void
    {
        $admin = $this->comoAdmin();
        $this->travelTo('2026-11-10 17:30');

        $this->getJson("/api/admin/espacos/{$this->quadra->id}/equipamentos/solicitaveis?data=2026-11-10&hora_inicio=19:00&hora_fim=20:30")
            ->assertOk()->assertJsonPath('data.0.nome', 'Projetor');
        $this->getJson("/api/espacos/{$this->quadra->id}/equipamentos/solicitaveis?data=2026-11-10&hora_inicio=19:00&hora_fim=20:30")
            ->assertJsonCount(0, 'data');

        $this->reservar(['solicitacoes' => [['id' => $this->projetor->id, 'quantidade' => 1]]])->assertCreated()
            ->assertJsonPath('data.equipamentos.0.solicitacao.situacao', 'APROVADA');

        $item = ItemReserva::sole();
        $this->assertSame($admin->id, $item->respondida_por);
        $this->assertNull($item->responder_ate);

        $this->reservar([
            'registro_academico' => Aluno::factory()->create()->registro_academico,
            'espaco_id'          => Espaco::factory()->quadra(null)->create()->id,
            'solicitacoes'       => [['id' => $this->projetor->id, 'quantidade' => 2]],
        ])->assertStatus(409);
    }

    public function test_publico_continua_com_origem_site(): void
    {
        $this->postJson('/api/reservas', [
            'registro_academico' => $this->aluno->registro_academico,
            'espaco_id'          => $this->quadra->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => HorarioReservaConstant::PRIMEIRO_HORARIO->value,
        ])->assertCreated();

        $this->assertSame('SITE', Reserva::sole()->origem->value);
        $this->assertNull(Reserva::sole()->criado_por);
    }
}
