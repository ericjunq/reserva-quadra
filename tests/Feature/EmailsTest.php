<?php

namespace Tests\Feature;

use App\Constants\EtapaConfirmacaoConstant;
use App\Mail\AlertaSistemaMail;
use App\Mail\CodigoLoginMail;
use App\Mail\ConviteAdminMail;
use App\Mail\DevolucaoComPendenciaMail;
use App\Mail\PedidoConfirmacaoMail;
use App\Mail\RedefinicaoSenhaMail;
use App\Mail\ReservaCanceladaMail;
use App\Mail\ReservaRevogadaMail;
use App\Mail\SenhaAlteradaMail;
use App\Mail\SolicitacaoRespondidaMail;
use App\Mail\UsoRegistradoMail;
use App\Models\Reserva;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

class EmailsTest extends TestCase
{
    use RefreshDatabase;

    private const FRONT = 'https://reservas.exemplo.edu';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => self::FRONT, 'app.url' => 'https://api.exemplo.edu']);
    }

    private function reserva(): Reserva
    {
        return Reserva::factory()->create()->load('aluno', 'espaco');
    }

    private function emails(): array
    {
        $reserva = $this->reserva();
        $prazo = now()->addDay();

        return [
            'alerta'    => [new AlertaSistemaMail('Fila parada', 'Há jobs falhando.', now()), '[Alerta] Fila parada', 'Há jobs falhando.', false],
            'código'    => [new CodigoLoginMail('Eric', '123456', 10), 'Seu código de acesso', '123456', false],
            'convite'   => [new ConviteAdminMail('Ana', self::FRONT.'/admin/convite#t', $prazo, 'Eric'), 'Convite para acessar o sistema de reservas', self::FRONT.'/admin/convite', true],
            'devolução' => [new DevolucaoComPendenciaMail($reserva, [[
                'nome' => 'Bola', 'quantidade' => 2, 'quantidade_devolvida' => 1, 'situacao' => 'Não devolvido', 'observacao' => 'Faltou uma',
            ]], 'Carla'), 'Pendência na devolução de equipamentos', 'Faltou uma', true],
            'confirmação' => [new PedidoConfirmacaoMail(
                $reserva, 'Carla', self::FRONT.'/reservas/confirmar#a', self::FRONT.'/reservas/cancelar#b', $prazo, EtapaConfirmacaoConstant::CONFIRMACAO,
            ), null, self::FRONT.'/reservas/confirmar', true],
            'reconfirmação' => [new PedidoConfirmacaoMail(
                $reserva, 'Carla', self::FRONT.'/reservas/confirmar#a', self::FRONT.'/reservas/cancelar#b', $prazo, EtapaConfirmacaoConstant::RECONFIRMACAO,
            ), null, self::FRONT.'/reservas/cancelar', true],
            'redefinição'     => [new RedefinicaoSenhaMail('Eric', self::FRONT.'/admin/redefinir-senha#t', $prazo), 'Redefinição de senha', self::FRONT.'/admin/redefinir-senha', true],
            'cancelada'       => [new ReservaCanceladaMail($reserva, 'Carla'), 'Reserva cancelada', $reserva->espaco->nome, true],
            'revogada'        => [new ReservaRevogadaMail($reserva), 'Sua reserva foi revogada', $reserva->espaco->nome, true],
            'senha alterada'  => [new SenhaAlteradaMail('Eric', now()), 'Sua senha foi alterada', 'Eric', true],
            'pedido aprovado' => [new SolicitacaoRespondidaMail(
                $reserva, [['nome' => 'Projetor', 'quantidade' => 1, 'situacao' => 'Aprovado', 'justificativa' => null]], false, null, null, null,
            ), 'Seu pedido de equipamento foi aprovado', 'Projetor', true],
            'pedido não liberado' => [new SolicitacaoRespondidaMail(
                $reserva, [['nome' => 'Projetor', 'quantidade' => 1, 'situacao' => 'Recusado', 'justificativa' => 'Em manutenção']], true, now()->addHours(12),
                self::FRONT.'/reservas/manter#m', self::FRONT.'/reservas/cancelar#c',
            ), 'Equipamento não liberado: confirme se quer manter a reserva', self::FRONT.'/reservas/manter', true],
            'uso registrado' => [new UsoRegistradoMail($reserva), 'Registramos um uso de espaço em seu nome', $reserva->espaco->nome, true],
        ];
    }

    public function test_todos_os_emails_montam_com_o_assunto_o_conteudo_e_os_links_do_front(): void
    {
        foreach ($this->emails() as $nome => [$email, $assunto, $trecho, $naFila]) {
            /** @var Mailable $email */
            $html = $email->render();

            if ($assunto !== null) {
                $email->assertHasSubject($assunto);
            }
            $this->assertStringContainsString(e($trecho), $html, "{$nome}: falta o trecho esperado");
            $this->assertStringNotContainsString('api.exemplo.edu', $html, "{$nome}: link apontando para a API");
            $this->assertStringNotContainsString('localhost', $html, "{$nome}: link de desenvolvimento");
            $this->assertSame($naFila, $email instanceof ShouldQueue, "{$nome}: fila errada");
        }
    }
}
