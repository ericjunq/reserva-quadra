<?php

namespace Tests\Feature;

use App\Mail\AlertaSistemaMail;
use App\Models\Aluno;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AlertaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['reservas.alertas_email' => ' TI@Fac.edu , ']);
    }

    private function falharJob(string $mensagem = 'erro'): void
    {
        try {
            dispatch(fn () => throw new RuntimeException($mensagem));
        } catch (RuntimeException) {
        }
    }

    public function test_job_falho_avisa_os_admins_ativos_e_o_email_de_alertas(): void
    {
        $ativo = User::factory()->create();
        User::factory()->inativo()->create();
        User::factory()->convitePendente()->create();

        $this->falharJob();

        Mail::assertSentCount(2);
        Mail::assertSent(AlertaSistemaMail::class, fn (AlertaSistemaMail $mail) => $mail->hasTo($ativo->email));
        Mail::assertSent(AlertaSistemaMail::class, fn (AlertaSistemaMail $mail) => $mail->hasTo('ti@fac.edu'));
        Mail::assertNothingQueued();
    }

    public function test_alerta_nao_leva_dados_pessoais_nem_a_mensagem_do_erro(): void
    {
        User::factory()->create();
        $aluno = Aluno::factory()->create();

        $this->falharJob("Falha ao enviar para {$aluno->email}");

        Mail::assertSent(AlertaSistemaMail::class, function (AlertaSistemaMail $mail) use ($aluno) {
            $texto = $mail->render();

            return str_contains($mail->descricao, 'RuntimeException')
                && ! str_contains($texto, $aluno->email)
                && ! str_contains($texto, 'Falha ao enviar');
        });
    }

    public function test_no_maximo_um_alerta_por_hora_para_o_mesmo_problema(): void
    {
        config(['reservas.alertas_email' => 'ti@fac.edu']);

        $this->falharJob();
        $this->falharJob();
        Mail::assertSentCount(1);

        $this->travel(61)->minutes();
        $this->falharJob();
        Mail::assertSentCount(2);
    }

    public function test_toda_tarefa_agendada_avisa_quando_falha(): void
    {
        config(['reservas.alertas_email' => 'ti@fac.edu']);
        Artisan::call('schedule:list');

        $tarefas = app(Schedule::class)->events();
        $this->assertCount(6, $tarefas);

        foreach ($tarefas as $tarefa) {
            $tarefa->exitCode = 1;
            $tarefa->callAfterCallbacks(app());
        }

        Mail::assertSentCount(count($tarefas));
        Mail::assertSent(AlertaSistemaMail::class, fn (AlertaSistemaMail $mail) => str_contains($mail->descricao, 'Pedido de confirmação'));
    }

    public function test_tarefa_que_termina_bem_nao_gera_alerta(): void
    {
        Artisan::call('schedule:list');

        foreach (app(Schedule::class)->events() as $tarefa) {
            $tarefa->exitCode = 0;
            $tarefa->callAfterCallbacks(app());
        }

        Mail::assertNothingSent();
    }
}
