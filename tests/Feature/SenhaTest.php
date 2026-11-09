<?php

namespace Tests\Feature;

use App\Mail\ConviteAdminMail;
use App\Mail\RedefinicaoSenhaMail;
use App\Mail\SenhaAlteradaMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SenhaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->admin = User::factory()->create(['email' => 'eric@fac.edu']);
    }

    private function tokenDoLink(string $link): string
    {
        return substr($link, strrpos($link, '/') + 1);
    }

    private function pedirRedefinicao(string $email = 'eric@fac.edu'): string
    {
        $this->postJson('/api/esqueci-senha', ['email' => $email])->assertOk();

        $link = null;
        Mail::assertQueued(RedefinicaoSenhaMail::class, function (RedefinicaoSenhaMail $mail) use (&$link) {
            $link = $mail->link;

            return true;
        });

        return $this->tokenDoLink($link);
    }

    private function redefinir(string $token, string $senha = 'NovaSenha123', string $email = 'eric@fac.edu')
    {
        return $this->postJson('/api/redefinir-senha', [
            'token'                 => $token,
            'email'                 => $email,
            'password'              => $senha,
            'password_confirmation' => $senha,
        ]);
    }

    private function login(string $senha, string $email = 'eric@fac.edu')
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $senha]);
    }

    public function test_esqueci_a_senha_envia_link_so_com_o_token(): void
    {
        $this->pedirRedefinicao('ERIC@fac.edu');

        Mail::assertQueued(RedefinicaoSenhaMail::class, function (RedefinicaoSenhaMail $mail) {
            return $mail->hasTo('eric@fac.edu')
                && str_starts_with($mail->link, 'http://localhost:4200/redefinir-senha/')
                && ! str_contains($mail->link, 'eric')
                && $mail->expiraEm->equalTo(now()->addMinutes(60));
        });
    }

    public function test_resposta_igual_para_email_inexistente_ou_inativo(): void
    {
        User::factory()->inativo()->create(['email' => 'inativo@fac.edu']);
        $mensagem = 'Se o email estiver cadastrado, você receberá um link para redefinir a senha.';

        $this->postJson('/api/esqueci-senha', ['email' => 'naoexiste@fac.edu'])->assertOk()->assertExactJson(['message' => $mensagem]);
        $this->postJson('/api/esqueci-senha', ['email' => 'inativo@fac.edu'])->assertOk()->assertExactJson(['message' => $mensagem]);

        Mail::assertNotQueued(RedefinicaoSenhaMail::class);
    }

    public function test_redefinir_troca_a_senha_derruba_sessoes_e_avisa(): void
    {
        $this->admin->createToken('celular');
        $token = $this->pedirRedefinicao();

        $this->redefinir($token, email: 'Eric@Fac.edu')->assertOk();

        $this->assertSame(0, $this->admin->tokens()->count());
        $this->login('password')->assertUnauthorized();
        $this->login('NovaSenha123')->assertOk();
        Mail::assertQueued(SenhaAlteradaMail::class, fn (SenhaAlteradaMail $mail) => $mail->hasTo('eric@fac.edu'));
    }

    public function test_link_de_redefinicao_e_de_uso_unico_e_vence_em_60_minutos(): void
    {
        $token = $this->pedirRedefinicao();
        $this->redefinir($token)->assertOk();
        $this->redefinir($token, 'OutraSenha456')->assertStatus(422)->assertJsonPath('message', 'Link inválido ou expirado.');

        $this->travel(2)->minutes();
        Mail::fake();
        $vencido = $this->pedirRedefinicao();
        $this->travel(61)->minutes();
        $this->redefinir($vencido, 'OutraSenha456')->assertStatus(422);
    }

    public function test_token_invalido_ou_email_errado(): void
    {
        $token = $this->pedirRedefinicao();

        $this->redefinir('token-qualquer')->assertStatus(422)->assertJsonPath('message', 'Link inválido ou expirado.');
        $this->redefinir($token, email: 'outro@fac.edu')->assertStatus(422)->assertJsonPath('message', 'Link inválido ou expirado.');
    }

    public function test_regras_da_senha_nova(): void
    {
        $token = $this->pedirRedefinicao();

        $this->redefinir($token, 'Curta1')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->redefinir($token, 'semnumeros')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->redefinir($token, '12345678')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/redefinir-senha', [
            'token' => $token, 'email' => 'eric@fac.edu', 'password' => 'NovaSenha123', 'password_confirmation' => 'Diferente123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_aceitar_convite_libera_o_acesso(): void
    {
        $this->comoAdmin();
        $this->postJson('/api/admin/admins', ['nome' => 'Ana', 'email' => 'ana@fac.edu'])->assertCreated();

        $link = null;
        Mail::assertQueued(ConviteAdminMail::class, function (ConviteAdminMail $mail) use (&$link) {
            $link = $mail->link;

            return true;
        });

        $this->travel(47)->hours();

        $this->postJson('/api/convites/aceitar', [
            'token'                 => $this->tokenDoLink($link),
            'email'                 => 'ana@fac.edu',
            'password'              => 'SenhaDaAna1',
            'password_confirmation' => 'SenhaDaAna1',
        ])->assertOk();

        $this->assertNotNull(User::where('email', 'ana@fac.edu')->value('convite_aceito_em'));
        $this->login('SenhaDaAna1', 'ana@fac.edu')->assertOk();
    }

    public function test_convite_vence_em_dois_dias(): void
    {
        $this->comoAdmin();
        $this->postJson('/api/admin/admins', ['nome' => 'Ana', 'email' => 'ana@fac.edu'])->assertCreated();

        $link = null;
        Mail::assertQueued(ConviteAdminMail::class, function (ConviteAdminMail $mail) use (&$link) {
            $link = $mail->link;

            return true;
        });

        $this->travel(2)->days();
        $this->travel(1)->minutes();

        $this->postJson('/api/convites/aceitar', [
            'token'                 => $this->tokenDoLink($link),
            'email'                 => 'ana@fac.edu',
            'password'              => 'SenhaDaAna1',
            'password_confirmation' => 'SenhaDaAna1',
        ])->assertStatus(422);
    }

    public function test_link_de_redefinicao_nao_serve_para_aceitar_convite(): void
    {
        $token = $this->pedirRedefinicao();

        $this->postJson('/api/convites/aceitar', [
            'token'                 => $token,
            'email'                 => 'eric@fac.edu',
            'password'              => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertStatus(422);
    }

    public function test_trocar_a_senha_logado_mantem_a_sessao_atual(): void
    {
        $outraSessao = $this->admin->createToken('celular');
        $token = $this->login('password')->json('token');
        $cabecalho = ['Authorization' => 'Bearer '.$token];

        $this->patchJson('/api/me/senha', ['senha_atual' => 'errada', 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123'], $cabecalho)
            ->assertStatus(422)->assertJsonValidationErrors('senha_atual');
        $this->patchJson('/api/me/senha', ['senha_atual' => 'password', 'password' => 'password', 'password_confirmation' => 'password'], $cabecalho)
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->patchJson('/api/me/senha', ['senha_atual' => 'password', 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123'], $cabecalho)
            ->assertOk();

        $this->assertNull($this->admin->tokens()->find($outraSessao->accessToken->id));
        $this->assertSame(1, $this->admin->tokens()->count());
        $this->getJson('/api/me', $cabecalho)->assertOk();
        $this->login('NovaSenha123')->assertOk();
        Mail::assertQueued(SenhaAlteradaMail::class, 1);
    }
}
