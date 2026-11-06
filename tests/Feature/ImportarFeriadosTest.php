<?php

namespace Tests\Feature;

use App\Models\DataBloqueada;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ImportarFeriadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_feriados_nacionais_sem_duplicar(): void
    {
        $this->artisan('feriados:importar 2027')->expectsOutput('10 feriado(s) importado(s) para 2027.')->assertSuccessful();
        $this->artisan('feriados:importar 2027')->expectsOutput('0 feriado(s) importado(s) para 2027.')->assertSuccessful();

        $this->assertDatabaseHas('datas_bloqueadas', ['data_inicio' => '2027-04-21', 'motivo' => 'Dia de Tiradentes', 'origem' => 'FERIADO', 'espaco_id' => null]);
        $this->assertDatabaseHas('datas_bloqueadas', ['data_inicio' => '2027-03-26', 'origem' => 'FERIADO']);
        $this->assertDatabaseMissing('datas_bloqueadas', ['data_inicio' => '2027-02-09']);
    }

    public function test_facultativos_entram_com_a_opcao(): void
    {
        $this->artisan('feriados:importar 2027 --facultativos')->expectsOutput('14 feriado(s) importado(s) para 2027.');

        $this->assertDatabaseHas('datas_bloqueadas', ['data_inicio' => '2027-02-09', 'motivo' => 'Terça-feira de Carnaval']);
        $this->assertDatabaseHas('datas_bloqueadas', ['data_inicio' => '2027-05-27', 'motivo' => 'Corpus Christi']);
    }

    public function test_feriado_importado_revoga_reservas_existentes(): void
    {
        Mail::fake();
        $reserva = Reserva::factory()->create(['data_reserva' => '2026-11-20']);

        $this->artisan('feriados:importar 2026')->assertSuccessful();

        $reserva->refresh();
        $this->assertSame('REVOGADO', $reserva->status->value);
        $this->assertNull($reserva->revogada_por);
        $this->assertStringStartsWith('Data bloqueada:', $reserva->justificativa_revogacao);
        $this->assertSame(1, DataBloqueada::where('data_inicio', '2026-11-20')->count());
    }
}
