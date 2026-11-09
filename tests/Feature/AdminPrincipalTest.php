<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPrincipalTest extends TestCase
{
    use RefreshDatabase;

    private User $principal;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->principal = User::factory()->principal()->create(['name' => 'Eric', 'email' => 'eric@fac.edu']);
    }

    public function test_ninguem_desativa_o_principal(): void
    {
        $this->comoAdmin();

        $this->deleteJson("/api/admin/admins/{$this->principal->id}")->assertStatus(409)
            ->assertJsonPath('message', 'O admin principal não pode ser desativado.');
        $this->patchJson("/api/admin/admins/{$this->principal->id}", ['ativo' => false])->assertStatus(409);

        $this->assertTrue($this->principal->fresh()->ativo);
    }

    public function test_outros_admins_nao_editam_o_principal(): void
    {
        $this->comoAdmin();

        $this->patchJson("/api/admin/admins/{$this->principal->id}", ['email' => 'invasor@fac.edu'])->assertStatus(403)
            ->assertJsonPath('message', 'O admin principal só pode ser alterado por ele mesmo.');
        $this->patchJson("/api/admin/admins/{$this->principal->id}", ['nome' => 'Outro'])->assertStatus(403);

        $this->assertSame('eric@fac.edu', $this->principal->fresh()->email);
    }

    public function test_principal_edita_os_proprios_dados_mas_nao_se_desativa(): void
    {
        Sanctum::actingAs($this->principal);

        $this->patchJson("/api/admin/admins/{$this->principal->id}", ['nome' => 'Eric Junqueira'])->assertOk()
            ->assertJsonPath('data.nome', 'Eric Junqueira')
            ->assertJsonPath('data.principal', true);
        $this->deleteJson("/api/admin/admins/{$this->principal->id}")->assertStatus(409);
    }

    public function test_principal_desativa_os_outros(): void
    {
        Sanctum::actingAs($this->principal);
        $admin = User::factory()->create();

        $this->deleteJson("/api/admin/admins/{$admin->id}")->assertOk()->assertJsonPath('data.ativo', false);
    }

    public function test_flag_principal_nao_muda_pela_api(): void
    {
        $admin = $this->comoAdmin();

        $this->patchJson("/api/admin/admins/{$admin->id}", ['nome' => 'Novo', 'principal' => true])->assertOk()
            ->assertJsonPath('data.principal', false);
        $this->postJson('/api/admin/admins', ['nome' => 'Ana', 'email' => 'ana@fac.edu', 'principal' => true])->assertCreated()
            ->assertJsonPath('data.principal', false);

        $this->assertSame(1, User::where('principal', true)->count());
    }

    public function test_seeder_cria_o_admin_principal(): void
    {
        $this->principal->forceFill(['principal' => false])->save();

        $this->seed(AdminSeeder::class);

        $this->assertTrue(User::where('email', 'admin@email.com')->value('principal'));
        $this->assertSame(1, User::where('principal', true)->count());
    }

    public function test_comando_cria_o_principal_quando_nao_existe(): void
    {
        $this->principal->forceFill(['principal' => false])->save();

        $this->artisan('admin:criar-principal', ['--nome' => 'Maria', '--email' => 'Maria@Fac.edu'])
            ->expectsQuestion('Senha (mínimo 8 caracteres, com letras e números)', 'SenhaForte123')
            ->expectsQuestion('Confirme a senha', 'SenhaForte123')
            ->assertSuccessful();

        $maria = User::where('email', 'maria@fac.edu')->sole();
        $this->assertTrue($maria->principal);
        $this->assertNotNull($maria->convite_aceito_em);
        $this->assertTrue(Hash::check('SenhaForte123', $maria->password));
    }

    public function test_comando_recusa_senha_fraca_e_principal_duplicado(): void
    {
        $this->artisan('admin:criar-principal', ['--nome' => 'Maria', '--email' => 'maria@fac.edu'])
            ->expectsOutputToContain('Já existe um admin principal (eric@fac.edu)')
            ->assertFailed();

        $this->principal->forceFill(['principal' => false])->save();

        $this->artisan('admin:criar-principal', ['--nome' => 'Maria', '--email' => 'maria@fac.edu'])
            ->expectsQuestion('Senha (mínimo 8 caracteres, com letras e números)', 'fraca')
            ->expectsQuestion('Confirme a senha', 'fraca')
            ->assertFailed();

        $this->assertNull(User::where('email', 'maria@fac.edu')->first());
    }

    public function test_transferir_o_principal(): void
    {
        $novo = User::factory()->create(['email' => 'novo@fac.edu']);

        $this->artisan('admin:transferir-principal', ['email' => 'NOVO@fac.edu'])->assertSuccessful();

        $this->assertFalse($this->principal->fresh()->principal);
        $this->assertTrue($novo->fresh()->principal);
        $this->assertSame(1, User::where('principal', true)->count());

        $historico = Auditoria::where('auditavel_type', User::class)->where('auditavel_id', $novo->id)->get()->last();
        $this->assertTrue($historico->depois['principal']);
    }

    public function test_transferir_exige_admin_ativo_com_senha_definida(): void
    {
        User::factory()->inativo()->create(['email' => 'inativo@fac.edu']);
        User::factory()->convitePendente()->create(['email' => 'pendente@fac.edu']);

        $this->artisan('admin:transferir-principal', ['email' => 'inativo@fac.edu'])->assertFailed();
        $this->artisan('admin:transferir-principal', ['email' => 'pendente@fac.edu'])->assertFailed();
        $this->artisan('admin:transferir-principal', ['email' => 'naoexiste@fac.edu'])->assertFailed();

        $this->assertTrue($this->principal->fresh()->principal);
    }
}
