<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Models\Equipamento;
use App\Models\Reserva;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibilidadeEquipamentoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DisponibilidadeEquipamentoRepository $repositorio;
    private Equipamento $bola;
    private Equipamento $colete;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = app(DisponibilidadeEquipamentoRepository::class);
        $this->bola = Equipamento::factory()->create(['quantidade_total' => 4]);
        $this->colete = Equipamento::factory()->create(['quantidade_total' => 2]);
    }

    private function reservar(string $data, HorarioReservaConstant $horario, array $itens, array $estado = []): Reserva
    {
        $reserva = Reserva::factory()->noHorario($horario)->create(['data_reserva' => $data] + $estado);

        foreach ($itens as $id => $quantidade) {
            $reserva->equipamentos()->attach($id, ['quantidade' => $quantidade]);
        }

        return $reserva;
    }

    private function disponiveis(string $data, HorarioReservaConstant $horario)
    {
        return $this->repositorio->disponiveis([$this->bola, $this->colete], $data, $horario->horaInicio(), $horario->horaFim());
    }

    public function test_disponivel_e_calculado_por_data_e_horario(): void
    {
        $primeiro = HorarioReservaConstant::PRIMEIRO_HORARIO;
        $segundo = HorarioReservaConstant::SEGUNDO_HORARIO;

        $this->reservar('2026-11-09', $primeiro, [$this->bola->id => 3]);
        $this->reservar('2026-11-10', $primeiro, [$this->bola->id => 4, $this->colete->id => 1]);
        $this->reservar('2026-11-10', $segundo, [$this->bola->id => 1]);

        $this->assertSame([$this->bola->id => 1, $this->colete->id => 2], $this->disponiveis('2026-11-09', $primeiro)->all());
        $this->assertSame([$this->bola->id => 4, $this->colete->id => 2], $this->disponiveis('2026-11-09', $segundo)->all());
        $this->assertSame([$this->bola->id => 0, $this->colete->id => 1], $this->disponiveis('2026-11-10', $primeiro)->all());
        $this->assertSame([$this->bola->id => 3, $this->colete->id => 2], $this->disponiveis('2026-11-10', $segundo)->all());
    }

    public function test_soma_reservas_de_espacos_diferentes(): void
    {
        $horario = HorarioReservaConstant::PRIMEIRO_HORARIO;
        $this->reservar('2026-11-09', $horario, [$this->bola->id => 1]);
        $this->reservar('2026-11-09', $horario, [$this->bola->id => 2]);

        $this->assertSame(1, $this->disponiveis('2026-11-09', $horario)[$this->bola->id]);
    }

    public function test_revogadas_nao_ocupam_estoque(): void
    {
        $horario = HorarioReservaConstant::PRIMEIRO_HORARIO;
        $this->reservar('2026-11-09', $horario, [$this->bola->id => 4], ['status' => 'REVOGADO']);

        $this->assertSame(4, $this->disponiveis('2026-11-09', $horario)[$this->bola->id]);
    }

    public function test_considera_intervalos_sobrepostos(): void
    {
        $this->reservar('2026-11-09', HorarioReservaConstant::PRIMEIRO_HORARIO, [$this->bola->id => 3]);

        $emUso = fn ($inicio, $fim) => $this->repositorio->emUso([$this->bola->id], '2026-11-09', $inicio, $fim)[$this->bola->id] ?? 0;

        $this->assertSame(3, $emUso('20:00:00', '21:00:00'));
        $this->assertSame(0, $emUso('20:30:00', '22:00:00'));
        $this->assertSame(0, $emUso('17:00:00', '19:00:00'));
    }

    public function test_maior_uso_futuro_ignora_reservas_passadas(): void
    {
        $this->reservar('2026-11-06', HorarioReservaConstant::PRIMEIRO_HORARIO, [$this->bola->id => 4]);
        $this->reservar('2026-11-10', HorarioReservaConstant::PRIMEIRO_HORARIO, [$this->bola->id => 1]);
        $this->reservar('2026-11-10', HorarioReservaConstant::PRIMEIRO_HORARIO, [$this->bola->id => 2]);
        $this->reservar('2026-11-11', HorarioReservaConstant::SEGUNDO_HORARIO, [$this->bola->id => 2]);

        $this->assertSame(3, $this->repositorio->maiorUsoFuturo($this->bola));
    }

    public function test_data_da_reserva_e_gravada_sem_hora(): void
    {
        $reserva = Reserva::factory()->create(['data_reserva' => '2026-11-10']);

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'data_reserva' => '2026-11-10']);
        $this->assertSame('2026-11-10', $reserva->fresh()->data_reserva->toDateString());
    }
}
