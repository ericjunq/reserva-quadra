<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\DataBloqueada;
use App\Models\Espaco;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VersaoDisponibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function versao(): string
    {
        return $this->getJson('/api/disponibilidade/versao')->assertOk()->json('data.versao');
    }

    public function test_versao_so_muda_quando_algo_que_aparece_no_calendario_muda(): void
    {
        Mail::fake();
        $espaco = Espaco::factory()->create();
        $aluno = Aluno::factory()->create();
        $inicial = $this->versao();

        $this->assertSame($inicial, $this->versao());

        $this->postJson('/api/reservas', [
            'registro_academico' => $aluno->registro_academico,
            'espaco_id'          => $espaco->id,
            'data_reserva'       => '2026-11-12',
            'horario'            => 'PRIMEIRO_HORARIO',
        ])->assertCreated();
        $depoisDaReserva = $this->versao();
        $this->assertNotSame($inicial, $depoisDaReserva);

        DataBloqueada::factory()->entre('2026-11-13', '2026-11-13')->create();
        $this->assertNotSame($depoisDaReserva, $this->versao());
    }

    public function test_versao_muda_a_cada_faixa_de_5_minutos(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 10:01'));
        $primeira = $this->versao();

        $this->travelTo(Carbon::parse('2026-11-09 10:04'));
        $this->assertSame($primeira, $this->versao());

        $this->travelTo(Carbon::parse('2026-11-09 10:05'));
        $this->assertNotSame($primeira, $this->versao());
    }

    public function test_resposta_tem_so_a_versao(): void
    {
        $this->getJson('/api/disponibilidade/versao')->assertOk()
            ->assertExactJsonStructure(['data' => ['versao']]);
    }
}
