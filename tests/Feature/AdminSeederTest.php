<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_o_admin_principal_com_os_dados_do_env(): void
    {
        config(['reservas.admin_inicial' => ['nome' => 'Eric', 'email' => ' Eric@Fac.edu', 'senha' => 'SenhaForte123']]);

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $admin = User::sole();
        $this->assertSame('eric@fac.edu', $admin->email);
        $this->assertTrue($admin->principal);
        $this->assertTrue(Hash::check('SenhaForte123', $admin->password));
    }

    public function test_sem_senha_no_env_nao_cria_admin(): void
    {
        config(['reservas.admin_inicial' => ['nome' => 'Eric', 'email' => 'eric@fac.edu', 'senha' => null]]);

        $this->seed(AdminSeeder::class);

        $this->assertSame(0, User::count());
    }
}
