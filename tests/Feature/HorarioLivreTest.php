<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\DataBloqueada;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\TokenReserva;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HorarioLivreTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $lab;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->lab = Espaco::factory()->laboratorio()->livre('07:00', '22:00', 120)->create();
    }

    private function reservar(string $inicio, string $fim, array $extra = [], string $data = '2026-11-12')
    {
        return $this->postJson('/api/reservas', [
            'registro_academico' => Aluno::factory()->create()->registro_academico,
            'espaco_id'          => $this->lab->id,
            'data_reserva'       => $data,
            'hora_inicio'        => $inicio,
            'hora_fim'           => $fim,
            ...$extra,
        ]);
    }

    private function reservaExistente(string $inicio, string $fim, array $itens = [], string $data = '2026-11-12', ?Espaco $espaco = null): Reserva
    {
        $reserva = Reserva::factory()->create([
            'data_reserva' => $data,
            'espaco_id'    => ($espaco ?? $this->lab)->id,
            'horario'      => null,
            'hora_inicio'  => $inicio,
            'hora_fim'     => $fim,
        ]);

        if ($itens) {
            $reserva->equipamentos()->attach(collect($itens)->map(fn ($q) => ['quantidade' => $q])->all());
        }

        return $reserva;
    }

    public function test_admin_configura_espaco_livre(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/admin/espacos', [
            'nome'    => 'Lab Novo', 'tipo' => 'LABORATORIO', 'modo_horario' => 'LIVRE',
            'abre_as' => '08:00', 'fecha_as' => '18:00', 'duracao_maxima_minutos' => 90,
        ])->assertCreated()
            ->assertJsonPath('data.modo_horario', 'LIVRE')
            ->assertJsonPath('data.abre_as', '08:00')
            ->assertJsonPath('data.fecha_as', '18:00')
            ->assertJsonPath('data.duracao_maxima_minutos', 90);

        $this->postJson('/api/admin/espacos', ['nome' => 'Sala Fixa', 'tipo' => 'SALA', 'abre_as' => '08:00'])
            ->assertCreated()
            ->assertJsonPath('data.modo_horario', 'FIXO')
            ->assertJsonPath('data.abre_as', null);
    }

    public function test_validacoes_da_configuracao(): void
    {
        $this->comoAdmin();
        $base = ['tipo' => 'LABORATORIO', 'modo_horario' => 'LIVRE', 'abre_as' => '08:00', 'fecha_as' => '18:00', 'duracao_maxima_minutos' => 90];

        $this->postJson('/api/admin/espacos', ['nome' => 'A', ...$base, 'abre_as' => null])->assertStatus(422)->assertJsonValidationErrors('abre_as');
        $this->postJson('/api/admin/espacos', ['nome' => 'B', ...$base, 'abre_as' => '08:10'])->assertStatus(422)->assertJsonValidationErrors('abre_as');
        $this->postJson('/api/admin/espacos', ['nome' => 'C', ...$base, 'fecha_as' => '07:00'])->assertStatus(422)->assertJsonValidationErrors('fecha_as');
        $this->postJson('/api/admin/espacos', ['nome' => 'D', ...$base, 'duracao_maxima_minutos' => 50])->assertStatus(422)->assertJsonValidationErrors('duracao_maxima_minutos');
        $this->postJson('/api/admin/espacos', ['nome' => 'E', ...$base, 'duracao_maxima_minutos' => 660])->assertStatus(422)->assertJsonValidationErrors('duracao_maxima_minutos');
        $this->patchJson("/api/admin/espacos/{$this->lab->id}", ['fecha_as' => '06:00'])->assertStatus(422)->assertJsonValidationErrors('fecha_as');
    }

    public function test_troca_de_modo_com_reservas_futuras_e_recusada(): void
    {
        $this->comoAdmin();
        $this->reservaExistente('14:00:00', '15:00:00');

        $this->patchJson("/api/admin/espacos/{$this->lab->id}", ['modo_horario' => 'FIXO'])->assertStatus(409);
        $this->patchJson("/api/admin/espacos/{$this->lab->id}", ['fecha_as' => '20:00'])->assertOk();

        $sala = Espaco::factory()->create();
        $this->patchJson("/api/admin/espacos/{$sala->id}", [
            'modo_horario' => 'LIVRE', 'abre_as' => '08:00', 'fecha_as' => '12:00', 'duracao_maxima_minutos' => 60,
        ])->assertOk()->assertJsonPath('data.modo_horario', 'LIVRE');
    }

    public function test_reserva_com_inicio_e_fim(): void
    {
        $this->reservar('14:00', '15:30')->assertCreated()
            ->assertJsonPath('data.horario', null)
            ->assertJsonPath('data.hora_inicio', '14:00')
            ->assertJsonPath('data.hora_fim', '15:30');
    }

    public function test_validacoes_do_intervalo(): void
    {
        $this->reservar('14:00', '15:00', ['horario' => 'PRIMEIRO_HORARIO'])->assertStatus(422)->assertJsonValidationErrors('horario');
        $this->reservar('', '')->assertStatus(422)->assertJsonValidationErrors('hora_inicio');
        $this->reservar('14:03', '14:05')->assertStatus(422)->assertJsonValidationErrors('hora_fim');
        $this->reservar('14:1', '15:00')->assertStatus(422)->assertJsonValidationErrors('hora_inicio');
        $this->reservar('15:00', '14:00')->assertStatus(422)->assertJsonValidationErrors('hora_fim');
        $this->reservar('06:30', '07:30')->assertStatus(422)->assertJsonValidationErrors('hora_inicio');
        $this->reservar('21:00', '22:30')->assertStatus(422)->assertJsonValidationErrors('hora_inicio');
        $this->reservar('14:00', '16:30')->assertStatus(422)->assertJsonValidationErrors('hora_fim');
        $this->reservar('14:00', '16:00')->assertCreated();
    }

    public function test_horarios_quebrados_sao_arredondados_para_15_minutos(): void
    {
        $this->reservar('14:07', '15:38')->assertCreated()
            ->assertJsonPath('data.hora_inicio', '14:00')
            ->assertJsonPath('data.hora_fim', '15:45');
        $this->reservar('16:08', '16:52')->assertCreated()
            ->assertJsonPath('data.hora_inicio', '16:15')
            ->assertJsonPath('data.hora_fim', '16:45');
        $this->reservar('13:52', '14:08')->assertStatus(409);
        $this->reservar('09:00', '11:08')->assertStatus(422)->assertJsonValidationErrors('hora_fim');
        $this->reservar('09:00', '11:07')->assertCreated()->assertJsonPath('data.hora_fim', '11:00');
    }

    public function test_espaco_fixo_nao_aceita_inicio_e_fim(): void
    {
        $sala = Espaco::factory()->create();

        $this->postJson('/api/reservas', [
            'registro_academico' => Aluno::factory()->create()->registro_academico,
            'espaco_id'          => $sala->id,
            'data_reserva'       => '2026-11-12',
            'hora_inicio'        => '19:00',
            'hora_fim'           => '20:30',
        ])->assertStatus(422)->assertJsonValidationErrors('hora_inicio');
    }

    public function test_conflito_por_sobreposicao(): void
    {
        $this->reservaExistente('14:00:00', '15:00:00');

        $this->reservar('14:30', '15:30')->assertStatus(409);
        $this->reservar('13:00', '14:30')->assertStatus(409);
        $this->reservar('15:00', '16:00')->assertCreated();
        $this->reservar('13:00', '14:00')->assertCreated();
    }

    public function test_hoje_aceita_ate_vinte_minutos_antes_do_fim_escolhido(): void
    {
        $this->reservar('10:00', '11:00', data: '2026-11-09')->assertCreated();

        $this->travelTo(Carbon::parse('2026-11-09 11:41'));
        $this->reservar('11:00', '12:00', data: '2026-11-09')->assertStatus(422)->assertJsonValidationErrors('hora_fim');
        $this->reservar('11:30', '12:30', data: '2026-11-09')->assertCreated();
    }

    public function test_bloqueio_de_parte_do_dia(): void
    {
        DataBloqueada::factory()->entre('2026-11-12', '2026-11-12')->dasHoras('14:00', '15:00')->create(['espaco_id' => $this->lab->id]);

        $this->reservar('14:30', '15:30')->assertStatus(422)->assertJsonValidationErrors('data_reserva');
        $this->reservar('15:00', '16:00')->assertCreated();
    }

    public function test_evento_pode_sair_da_janela_e_passar_da_duracao_maxima(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/admin/reservas/eventos', [
            'espaco_id'    => $this->lab->id,
            'data_reserva' => '2026-11-14',
            'hora_inicio'  => '18:00',
            'hora_fim'     => '23:30',
            'descricao'    => 'Maratona de programação',
        ])->assertCreated()->assertJsonPath('data.hora_fim', '23:30');

        $this->postJson('/api/admin/reservas/eventos', [
            'espaco_id'    => $this->lab->id,
            'data_reserva' => '2026-11-15',
            'hora_inicio'  => '18:07',
            'hora_fim'     => '19:08',
            'descricao'    => 'Horário quebrado',
        ])->assertCreated()
            ->assertJsonPath('data.hora_inicio', '18:00')
            ->assertJsonPath('data.hora_fim', '19:15');
    }

    public function test_equipamentos_pelo_pico_de_uso(): void
    {
        [$sala, $outraSala] = Espaco::factory()->livre()->count(2)->create();
        $projetor = Equipamento::factory()->for($this->lab)->compartilhadoCom($sala, $outraSala)->create(['nome' => 'Projetor', 'quantidade_total' => 3]);
        $this->reservaExistente('14:00:00', '15:00:00', [$projetor->id => 2], espaco: $sala);
        $this->reservaExistente('15:00:00', '16:00:00', [$projetor->id => 2], espaco: $outraSala);

        $disponivel = app(DisponibilidadeEquipamentoRepository::class)->disponiveis([$projetor], '2026-11-12', '14:00:00', '16:00:00');
        $this->assertSame(1, $disponivel[$projetor->id]);

        $this->reservar('14:00', '16:00', ['equipamentos' => [['id' => $projetor->id, 'quantidade' => 2]]])->assertStatus(409);
        $this->reservar('14:00', '16:00', ['equipamentos' => [['id' => $projetor->id, 'quantidade' => 1]]])->assertCreated();
    }

    public function test_reduzir_estoque_considera_o_pico(): void
    {
        $this->comoAdmin();
        $projetor = Equipamento::factory()->for($this->lab)->create(['quantidade_total' => 5]);
        $this->reservaExistente('14:00:00', '15:00:00', [$projetor->id => 2]);
        $this->reservaExistente('15:00:00', '16:00:00', [$projetor->id => 2]);
        $this->reservaExistente('14:30:00', '15:30:00', [$projetor->id => 1], '2026-11-13');

        $this->patchJson("/api/admin/equipamentos/{$projetor->id}", ['quantidade_total' => 1])->assertStatus(409);
        $this->patchJson("/api/admin/equipamentos/{$projetor->id}", ['quantidade_total' => 2])->assertOk();
    }

    public function test_calendario_mostra_a_janela_e_os_intervalos_ocupados(): void
    {
        $this->reservaExistente('14:00:00', '15:30:00');
        DataBloqueada::factory()->entre('2026-11-12', '2026-11-12')->dasHoras('09:00', '10:00')->create([
            'espaco_id' => $this->lab->id, 'motivo' => 'Manutenção',
        ]);

        $resposta = $this->getJson("/api/espacos/{$this->lab->id}/disponibilidade?de=2026-11-12")->assertOk()
            ->assertJsonPath('data.espaco.modo_horario', 'LIVRE')
            ->assertJsonPath('data.espaco.abre_as', '07:00')
            ->assertJsonPath('data.espaco.fecha_as', '22:00')
            ->assertJsonPath('data.espaco.bloco_minutos', 15)
            ->assertJsonPath('data.dias.0.reservavel', true)
            ->assertJsonPath('data.dias.0.fim_minimo', null)
            ->assertJsonMissingPath('data.dias.0.horarios')
            ->assertJsonCount(2, 'data.dias.0.ocupados');

        $this->assertSame(
            [['09:00', '10:00', 'BLOQUEADO', 'Manutenção'], ['14:00', '15:30', 'RESERVADO', null]],
            collect($resposta->json('data.dias.0.ocupados'))->map(fn ($o) => [$o['inicio'], $o['fim'], $o['situacao'], $o['motivo']])->all(),
        );
        $this->assertArrayNotHasKey('reservado_por', $resposta->json('data.dias.0.ocupados.1.reserva'));
        $this->assertStringNotContainsString('registro_academico', $resposta->getContent());
    }

    public function test_calendario_de_hoje_informa_o_fim_minimo(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 14:07:30'));

        $this->getJson("/api/espacos/{$this->lab->id}/disponibilidade?de=2026-11-09")->assertOk()
            ->assertJsonPath('data.dias.0.reservavel', true)
            ->assertJsonPath('data.dias.0.fim_minimo', '14:30');

        $this->travelTo(Carbon::parse('2026-11-09 21:45'));

        $this->getJson("/api/espacos/{$this->lab->id}/disponibilidade?de=2026-11-09")->assertOk()
            ->assertJsonPath('data.dias.0.reservavel', false)
            ->assertJsonPath('data.dias.0.motivo', 'Os horários de hoje já foram encerrados.')
            ->assertJsonPath('data.dias.0.fim_minimo', null);
    }

    public function test_consulta_de_equipamentos_por_intervalo(): void
    {
        $projetor = Equipamento::factory()->for($this->lab)->create(['nome' => 'Projetor', 'quantidade_total' => 3]);
        $this->reservaExistente('14:00:00', '15:00:00', [$projetor->id => 2]);

        $url = "/api/espacos/{$this->lab->id}/equipamentos/disponibilidade";

        $this->getJson("{$url}?data=2026-11-12&hora_inicio=14:30&hora_fim=16:00")->assertOk()
            ->assertJsonPath('data.0.nome', 'Projetor')
            ->assertJsonPath('data.0.total', 3)
            ->assertJsonPath('data.0.disponivel', 1);
        $this->getJson("{$url}?data=2026-11-12&hora_inicio=15:00&hora_fim=16:00")->assertJsonPath('data.0.disponivel', 3);
        $this->getJson("{$url}?data=2026-11-12&hora_inicio=16:00&hora_fim=15:00")->assertStatus(422)->assertJsonValidationErrors('hora_fim');
    }

    public function test_link_de_cancelamento_vence_no_inicio_escolhido(): void
    {
        $id = $this->reservar('14:00', '15:00')->assertCreated()->json('data.id');

        $this->assertSame('2026-11-12 14:00:00', TokenReserva::where('reserva_id', $id)->value('expira_em')->format('Y-m-d H:i:s'));
    }
}
