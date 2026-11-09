<?php

namespace Tests\Feature;

use App\Constants\FinalidadeTokenConstant;
use App\Models\Reserva;
use App\Models\TokenReserva;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AgendadorTest extends TestCase
{
    use RefreshDatabase;

    private function tarefa(string $comando): Event
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn (Event $evento) => str_contains($evento->command, $comando));

        $this->assertNotNull($evento, "Tarefa {$comando} não está agendada.");

        return $evento;
    }

    public function test_tarefas_agendadas_com_horario_e_sem_sobreposicao(): void
    {
        $esperado = [
            'reservas:pedir-confirmacao'       => '0 8 * * *',
            'reservas:revogar-nao-confirmadas' => '5 0 * * *',
            'feriados:importar'                => '0 3 1 12 *',
            'reservas:limpar-tokens'           => '10 3 * * *',
            'sanctum:prune-expired'            => '20 3 * * *',
            'queue:prune-failed'               => '30 3 * * *',
        ];

        foreach ($esperado as $comando => $expressao) {
            $evento = $this->tarefa($comando);

            $this->assertSame($expressao, $evento->expression, $comando);
            $this->assertTrue($evento->withoutOverlapping, $comando);
        }
    }

    public function test_feriados_importa_o_ano_seguinte_sem_facultativos(): void
    {
        $comando = $this->tarefa('feriados:importar')->command;

        $this->assertStringContainsString('2027', $comando);
        $this->assertStringNotContainsString('--facultativos', $comando);
    }

    public function test_fila_guarda_jobs_falhos_por_sete_dias(): void
    {
        $this->assertStringContainsString('--hours=168', $this->tarefa('queue:prune-failed')->command);
    }

    public function test_limpa_so_os_tokens_antigos(): void
    {
        $reserva = Reserva::factory()->create();
        $criar = fn (array $dados) => $reserva->tokens()->create([
            'finalidade' => FinalidadeTokenConstant::CANCELAR,
            'token_hash' => hash('sha256', uniqid('', true)),
            ...$dados,
        ]);

        $usadoHaMuito = $criar(['expira_em' => now()->addDay(), 'usado_em' => now()->subDays(31)]);
        $vencidoHaMuito = $criar(['expira_em' => now()->subDays(31)]);
        $usadoRecente = $criar(['expira_em' => now()->addDay(), 'usado_em' => now()->subDays(5)]);
        $vencidoRecente = $criar(['expira_em' => now()->subDays(5)]);
        $valido = $criar(['expira_em' => now()->addDays(3)]);

        Artisan::call('reservas:limpar-tokens');

        $this->assertSame(
            [$usadoRecente->id, $vencidoRecente->id, $valido->id],
            TokenReserva::orderBy('id')->pluck('id')->all(),
        );
        $this->assertNull(TokenReserva::find($usadoHaMuito->id));
        $this->assertNull(TokenReserva::find($vencidoHaMuito->id));
    }
}
