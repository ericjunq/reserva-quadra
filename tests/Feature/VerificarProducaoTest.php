<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificarProducaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_aponta_o_que_falta_e_termina_com_falha(): void
    {
        config([
            'app.debug'                    => true,
            'app.url'                      => 'http://localhost',
            'reservas.alertas_email'       => null,
            'reservas.admin_inicial.senha' => 'Admin',
        ]);

        $this->artisan('sistema:verificar-producao')
            ->expectsOutputToContain('❌ APP_ENV é production')
            ->expectsOutputToContain('❌ APP_DEBUG desligado: defina APP_DEBUG=false')
            ->expectsOutputToContain('❌ APP_URL com HTTPS')
            ->expectsOutputToContain('❌ ALERTAS_EMAIL definido')
            ->expectsOutputToContain('❌ ADMIN_INICIAL_SENHA vazio')
            ->expectsOutputToContain('❌ Admin principal ativo')
            ->expectsOutputToContain('✅ APP_KEY definida')
            ->expectsOutputToContain('✅ Banco de dados responde')
            ->assertFailed();
    }

    public function test_marca_os_itens_configurados(): void
    {
        User::factory()->create()->forceFill(['principal' => true])->save();
        $this->artisan('feriados:importar')->assertSuccessful();

        config([
            'app.debug'                       => false,
            'app.url'                         => 'https://api.fac.edu',
            'app.frontend_url'                => 'https://reservas.fac.edu',
            'cors.allowed_origins'            => ['https://reservas.fac.edu'],
            'mail.default'                    => 'smtp',
            'mail.from.address'               => 'reservas@fac.edu',
            'logging.default'                 => 'stack',
            'logging.channels.stack.channels' => ['daily'],
            'logging.channels.daily.level'    => 'warning',
            'reservas.alertas_email'          => 'ti@fac.edu',
            'reservas.admin_inicial.senha'    => null,
        ]);

        $this->artisan('sistema:verificar-producao')
            ->expectsOutputToContain('✅ APP_DEBUG desligado')
            ->expectsOutputToContain('✅ APP_URL com HTTPS')
            ->expectsOutputToContain('✅ FRONTEND_URL com HTTPS')
            ->expectsOutputToContain('✅ CORS liberado só para endereços HTTPS')
            ->expectsOutputToContain('✅ Email configurado')
            ->expectsOutputToContain('✅ Logs diários com nível warning ou acima')
            ->expectsOutputToContain('✅ ALERTAS_EMAIL definido')
            ->expectsOutputToContain('✅ ADMIN_INICIAL_SENHA vazio')
            ->expectsOutputToContain('✅ Admin principal ativo')
            ->expectsOutputToContain('✅ Feriados do ano importados')
            ->assertFailed();
    }
}
