<?php

namespace Tests\Feature;

use App\Mail\ConviteAdminMail;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function login(string $email, string $senha = 'password')
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $senha]);
    }

    public function test_login_e_me_respondem_com_o_resource_sem_senha(): void
    {
        $admin = User::factory()->create(['name' => 'Eric', 'email' => 'eric@fac.edu']);

        $resposta = $this->login('ERIC@Fac.edu ')->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.nome', 'Eric')
            ->assertJsonPath('user.email', 'eric@fac.edu')
            ->assertJsonMissingPath('user.password');

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$resposta->json('token')])->assertOk()
            ->assertJsonPath('data.email', 'eric@fac.edu')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_admin_inativo_nao_faz_login(): void
    {
        User::factory()->inativo()->create(['email' => 'inativo@fac.edu']);

        $this->login('inativo@fac.edu')->assertUnauthorized()->assertJsonPath('message', 'Credenciais inválidas');
        $this->login('naoexiste@fac.edu')->assertUnauthorized()->assertJsonPath('message', 'Credenciais inválidas');
    }

    public function test_rotas_exigem_login(): void
    {
        $this->getJson('/api/admin/admins')->assertUnauthorized();
        $this->postJson('/api/admin/admins', [])->assertUnauthorized();
        $this->patchJson('/api/me/senha', [])->assertUnauthorized();
    }

    public function test_convidar_admin_envia_convite_valido_por_dois_dias(): void
    {
        $quemConvidou = $this->comoAdmin();

        $id = $this->postJson('/api/admin/admins', ['nome' => 'Ana Lima', 'email' => '  Ana.Lima@Fac.edu '])
            ->assertCreated()
            ->assertJsonPath('data.email', 'ana.lima@fac.edu')
            ->assertJsonPath('data.ativo', true)
            ->assertJsonPath('data.convite_aceito_em', null)
            ->assertJsonPath('data.criado_por.id', $quemConvidou->id)
            ->json('data.id');

        $this->assertTrue(DB::table('convites_admin_tokens')->where('email', 'ana.lima@fac.edu')->exists());

        Mail::assertQueued(ConviteAdminMail::class, function (ConviteAdminMail $mail) use ($quemConvidou) {
            return $mail->hasTo('ana.lima@fac.edu')
                && $mail->convidadoPor === $quemConvidou->name
                && str_starts_with($mail->link, 'http://localhost:4200/aceitar-convite/')
                && ! str_contains($mail->link, 'ana.lima')
                && $mail->expiraEm->equalTo(now()->addDays(2));
        });

        $this->login('ana.lima@fac.edu')->assertUnauthorized();
        $this->assertNotNull(User::find($id));
    }

    public function test_email_unico_sem_diferenciar_maiusculas(): void
    {
        $this->comoAdmin();
        User::factory()->create(['email' => 'ana@fac.edu']);
        $outro = User::factory()->create(['email' => 'joao@fac.edu']);

        $this->postJson('/api/admin/admins', ['nome' => 'Ana', 'email' => 'ANA@fac.edu'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->patchJson("/api/admin/admins/{$outro->id}", ['email' => 'Ana@Fac.Edu'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->patchJson("/api/admin/admins/{$outro->id}", ['email' => 'JOAO@fac.edu'])->assertOk();
    }

    public function test_listagem_com_filtros(): void
    {
        $this->comoAdmin();
        $ana = User::factory()->create(['name' => 'Ana Lima', 'email' => 'ana@fac.edu']);
        $inativo = User::factory()->inativo()->create(['name' => 'Bruno']);

        $ids = fn ($params) => collect($this->getJson('/api/admin/admins?'.http_build_query($params))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$ana->id], $ids(['busca' => 'LIMA']));
        $this->assertSame([$ana->id], $ids(['busca' => 'ana@']));
        $this->assertSame([$inativo->id], $ids(['ativo' => 'false']));
    }

    public function test_editar_nome_e_email(): void
    {
        $responsavel = $this->comoAdmin();
        $admin = User::factory()->create();

        $this->patchJson("/api/admin/admins/{$admin->id}", ['nome' => 'Nome Novo', 'email' => 'Novo@Fac.edu'])->assertOk()
            ->assertJsonPath('data.nome', 'Nome Novo')
            ->assertJsonPath('data.email', 'novo@fac.edu')
            ->assertJsonPath('data.atualizado_por.id', $responsavel->id);
    }

    public function test_desativar_bloqueia_login_e_derruba_as_sessoes(): void
    {
        $this->comoAdmin();
        $admin = User::factory()->create(['email' => 'saindo@fac.edu']);
        $admin->createToken('api');

        $this->deleteJson("/api/admin/admins/{$admin->id}")->assertOk()->assertJsonPath('data.ativo', false);

        $this->assertSame(0, $admin->tokens()->count());
        $this->login('saindo@fac.edu')->assertUnauthorized();

        $this->patchJson("/api/admin/admins/{$admin->id}", ['ativo' => true])->assertOk()->assertJsonPath('data.ativo', true);
        $this->login('saindo@fac.edu')->assertOk();
    }

    public function test_ninguem_desativa_a_si_mesmo(): void
    {
        $eu = $this->comoAdmin();
        User::factory()->create();

        $this->deleteJson("/api/admin/admins/{$eu->id}")->assertStatus(422)
            ->assertJsonPath('message', 'Você não pode desativar a si mesmo.');
        $this->patchJson("/api/admin/admins/{$eu->id}", ['ativo' => false])->assertStatus(422);

        $this->assertTrue($eu->fresh()->ativo);
    }

    public function test_sempre_sobra_um_admin_ativo(): void
    {
        $responsavel = User::factory()->inativo()->create();
        $ultimo = User::factory()->create();

        $this->expectExceptionMessage('É preciso manter pelo menos um admin ativo.');

        app(AdminService::class)->desativar($ultimo, $responsavel);
    }

    public function test_reenviar_convite(): void
    {
        $this->comoAdmin();
        $pendente = User::factory()->convitePendente()->create();
        $aceito = User::factory()->create();
        $inativo = User::factory()->convitePendente()->inativo()->create();

        $this->postJson("/api/admin/admins/{$pendente->id}/reenviar-convite")->assertOk();
        Mail::assertQueued(ConviteAdminMail::class, fn (ConviteAdminMail $mail) => $mail->hasTo($pendente->email));

        $this->postJson("/api/admin/admins/{$aceito->id}/reenviar-convite")->assertStatus(409);
        $this->postJson("/api/admin/admins/{$inativo->id}/reenviar-convite")->assertStatus(409);
    }

    public function test_historico_nunca_guarda_a_senha(): void
    {
        $this->comoAdmin();
        $admin = User::factory()->create();
        $hashAntigo = $admin->password;

        $admin->update(['password' => 'NovaSenha123']);

        $historico = Auditoria::where('auditavel_type', User::class)->where('auditavel_id', $admin->id)->get();
        $json = $historico->toJson();

        $this->assertStringNotContainsString($hashAntigo, $json);
        $this->assertStringNotContainsString($admin->fresh()->password, $json);
        $this->assertSame('[oculto]', $historico->last()->depois['password']);
    }
}
