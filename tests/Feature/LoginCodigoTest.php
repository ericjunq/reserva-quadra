<?php

namespace Tests\Feature;

use App\Mail\CodigoLoginMail;
use App\Models\DesafioLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class LoginCodigoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->admin = User::factory()->create(['name' => 'Eric', 'email' => 'eric@fac.edu']);
    }

    private function senhaCerta()
    {
        return $this->postJson('/api/login', ['email' => 'eric@fac.edu', 'password' => 'password']);
    }

    private function ultimoCodigo(): string
    {
        return Mail::sent(CodigoLoginMail::class)->last()->codigo;
    }

    private function confirmar(string $desafio, string $codigo)
    {
        return $this->postJson('/api/login/codigo', ['desafio' => $desafio, 'codigo' => $codigo]);
    }

    private function codigoErrado(string $certo): string
    {
        return str_pad((string) (((int) $certo + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_senha_certa_manda_o_codigo_na_hora_e_nao_entrega_o_token(): void
    {
        $resposta = $this->senhaCerta()->assertOk()
            ->assertJsonPath('etapa', 'CODIGO')
            ->assertJsonMissingPath('token')
            ->assertJsonPath('expira_em', now()->addMinutes(10)->toIso8601String());

        $this->assertSame(64, strlen($resposta->json('desafio')));
        Mail::assertSent(CodigoLoginMail::class, fn (CodigoLoginMail $mail) => $mail->hasTo('eric@fac.edu')
            && preg_match('/^\d{6}$/', $mail->codigo) === 1
            && $mail->envelope()->subject === 'Seu código de acesso');
        Mail::assertNotQueued(CodigoLoginMail::class);

        $registro = DesafioLogin::sole();
        $this->assertNotSame($resposta->json('desafio'), $registro->desafio_hash);
        $this->assertNotSame($this->ultimoCodigo(), $registro->codigo_hash);
    }

    public function test_senha_errada_nao_manda_codigo(): void
    {
        $this->postJson('/api/login', ['email' => 'eric@fac.edu', 'password' => 'errada'])
            ->assertUnauthorized()->assertJsonPath('message', 'Credenciais inválidas');

        Mail::assertNothingSent();
        $this->assertSame(0, DesafioLogin::count());
    }

    public function test_codigo_certo_entrega_o_token_uma_vez_so(): void
    {
        $desafio = $this->senhaCerta()->json('desafio');
        $codigo = $this->ultimoCodigo();

        $resposta = $this->confirmar($desafio, $codigo)->assertOk()
            ->assertJsonPath('user.id', $this->admin->id)
            ->assertJsonStructure(['token', 'expires_at', 'user']);

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$resposta->json('token')])->assertOk();
        $this->confirmar($desafio, $codigo)->assertUnauthorized()
            ->assertJsonPath('message', 'Código inválido ou vencido. Entre de novo se precisar.');
    }

    public function test_codigo_vencido_nao_vale(): void
    {
        $desafio = $this->senhaCerta()->json('desafio');
        $codigo = $this->ultimoCodigo();

        $this->travel(11)->minutes();

        $this->confirmar($desafio, $codigo)->assertUnauthorized();
    }

    public function test_na_quinta_tentativa_errada_o_desafio_morre(): void
    {
        $desafio = $this->senhaCerta()->json('desafio');
        $codigo = $this->ultimoCodigo();

        foreach (range(1, 5) as $tentativa) {
            $this->confirmar($desafio, $this->codigoErrado($codigo))->assertUnauthorized();
        }

        $this->confirmar($desafio, $codigo)->assertUnauthorized();
        $this->assertSame(5, DesafioLogin::sole()->tentativas);
    }

    public function test_desafio_de_outro_login_nao_serve_e_novo_login_invalida_o_anterior(): void
    {
        $primeiro = $this->senhaCerta()->json('desafio');
        $codigoDoPrimeiro = $this->ultimoCodigo();
        $segundo = $this->senhaCerta()->json('desafio');

        $this->confirmar($primeiro, $codigoDoPrimeiro)->assertUnauthorized();
        $this->confirmar(str_repeat('a', 64), $this->ultimoCodigo())->assertUnauthorized();
        $this->confirmar($segundo, $this->ultimoCodigo())->assertOk();
    }

    public function test_admin_desativado_depois_da_senha_nao_entra(): void
    {
        $desafio = $this->senhaCerta()->json('desafio');
        $this->admin->update(['ativo' => false]);

        $this->confirmar($desafio, $this->ultimoCodigo())->assertUnauthorized();
    }

    public function test_reenvio_troca_o_codigo_com_espera_e_limite(): void
    {
        $desafio = $this->senhaCerta()->json('desafio');
        $antigo = $this->ultimoCodigo();

        $this->postJson('/api/login/reenviar', ['desafio' => $desafio])->assertStatus(429)
            ->assertJsonPath('dados.segundos', 60);

        foreach (range(1, 3) as $vez) {
            $this->travel(61)->seconds();
            $this->postJson('/api/login/reenviar', ['desafio' => $desafio])->assertOk()
                ->assertJsonPath('expira_em', now()->addMinutes(10)->toIso8601String());
        }

        $this->travel(61)->seconds();
        $this->postJson('/api/login/reenviar', ['desafio' => $desafio])->assertStatus(429)
            ->assertJsonPath('message', 'Você já pediu o código novo muitas vezes. Entre de novo com a sua senha.');

        Mail::assertSentCount(4);
        $novo = $this->ultimoCodigo();
        if ($novo !== $antigo) {
            $this->confirmar($desafio, $antigo)->assertUnauthorized();
        }
        $this->confirmar($desafio, $novo)->assertOk();
    }

    public function test_reenvio_com_desafio_invalido_responde_generico(): void
    {
        $this->postJson('/api/login/reenviar', ['desafio' => str_repeat('b', 64)])->assertUnauthorized()
            ->assertJsonPath('message', 'Código inválido ou vencido. Entre de novo se precisar.');
        $this->postJson('/api/login/reenviar', [])->assertUnprocessable()->assertJsonValidationErrors('desafio');
    }

    public function test_falha_no_envio_responde_503_e_descarta_o_desafio(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP fora do ar'));

        $this->senhaCerta()->assertStatus(503)
            ->assertJsonPath('message', 'Não foi possível enviar o código agora. Tente de novo em instantes.');

        $this->assertSame(0, DesafioLogin::count());
    }

    public function test_formato_do_codigo_e_validado(): void
    {
        $desafio = $this->senhaCerta()->json('desafio');

        $this->confirmar($desafio, '12ab')->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'O código tem 6 números.');
    }

    public function test_codigo_nunca_vai_para_o_log(): void
    {
        Log::spy();

        $desafio = $this->senhaCerta()->json('desafio');
        $codigo = $this->ultimoCodigo();
        $this->confirmar($desafio, $this->codigoErrado($codigo));
        $this->confirmar($desafio, $codigo)->assertOk();

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_desligado_na_configuracao_entrega_o_token_direto(): void
    {
        config(['reservas.login_com_codigo' => false]);

        $this->senhaCerta()->assertOk()->assertJsonStructure(['token', 'expires_at', 'user']);
        Mail::assertNothingSent();
    }

    public function test_limpeza_apaga_desafios_vencidos_ha_mais_de_um_dia(): void
    {
        $this->senhaCerta();
        $this->travel(2)->days();
        $this->senhaCerta();

        Artisan::call('model:prune', ['--model' => [DesafioLogin::class]]);

        $this->assertSame(1, DesafioLogin::count());
    }
}
