<?php

namespace Tests\Feature;

use App\Mail\AceiteApiMail;
use App\Models\Auditoria;
use App\Models\ClienteApi;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClienteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['app.frontend_url' => 'https://reservas.exemplo.edu']);
    }

    private function dados(array $extra = []): array
    {
        return [
            'nome'              => 'App da Atlética',
            'organizacao'       => 'Atlética de Computação',
            'responsavel_nome'  => 'Bruno Lima',
            'responsavel_email' => ' Bruno.Lima@Atletica.org ',
            'finalidade'        => 'Reservar a quadra para os treinos das equipes.',
            'permissoes'        => ['reservas:ler', 'espacos:ler', 'reservas:criar'],
            ...$extra,
        ];
    }

    private function tokenDoLink(?string $email = null): string
    {
        $mail = Mail::queued(AceiteApiMail::class)
            ->filter(fn (AceiteApiMail $m) => $email === null || $m->hasTo($email))
            ->last();

        $this->assertNotNull($mail, 'Nenhum email de aceite enviado.');

        return basename($mail->link);
    }

    private function criarPeloAdmin(array $extra = []): ClienteApi
    {
        $this->comoAdmin();
        $id = $this->postJson('/api/admin/clientes-api', $this->dados($extra))->assertCreated()->json('data.id');

        return ClienteApi::findOrFail($id);
    }

    private function aceitar(string $token): string
    {
        return $this->postJson("/api/parceiros/aceite/{$token}", ['aceite_termos' => true])->assertOk()->json('data.token');
    }

    public function test_admin_cadastra_o_sistema_e_o_responsavel_recebe_o_link_sem_token_na_resposta(): void
    {
        $this->comoAdmin();

        $resposta = $this->postJson('/api/admin/clientes-api', $this->dados())->assertCreated()
            ->assertJsonPath('data.nome', 'App da Atlética')
            ->assertJsonPath('data.responsavel.email', 'bruno.lima@atletica.org')
            ->assertJsonPath('data.permissoes', ['espacos:ler', 'reservas:criar', 'reservas:ler'])
            ->assertJsonPath('data.limite_por_minuto', 60)
            ->assertJsonPath('data.situacao', 'AGUARDANDO_ACEITE')
            ->assertJsonPath('data.criado_por.nome', 'Admin dos Testes');

        $this->assertNotNull($resposta->json('data.link_aceite.expira_em'));
        $this->assertStringNotContainsString('token', strtolower(json_encode(array_keys($resposta->json('data')))));
        $this->assertSame(0, PersonalAccessToken::count());

        Mail::assertQueued(AceiteApiMail::class, fn (AceiteApiMail $m) => $m->hasTo('bruno.lima@atletica.org')
            && str_starts_with($m->link, 'https://reservas.exemplo.edu/parceiros/aceite/')
            && $m->acessoVenceEm === null);
    }

    public function test_cadastro_valida_nome_unico_e_permissoes(): void
    {
        ClienteApi::factory()->create(['nome' => 'App da Atlética']);
        $this->comoAdmin();

        $this->postJson('/api/admin/clientes-api', $this->dados(['nome' => 'APP DA ATLÉTICA']))->assertUnprocessable()->assertJsonValidationErrors('nome');
        $this->postJson('/api/admin/clientes-api', $this->dados(['nome' => 'Outro', 'permissoes' => []]))->assertUnprocessable()->assertJsonValidationErrors('permissoes');
        $this->postJson('/api/admin/clientes-api', $this->dados(['nome' => 'Outro', 'permissoes' => ['admin:tudo']]))->assertUnprocessable()->assertJsonValidationErrors('permissoes.0');
        $this->postJson('/api/admin/clientes-api', $this->dados(['nome' => 'Outro', 'limite_por_minuto' => 1000]))->assertUnprocessable()->assertJsonValidationErrors('limite_por_minuto');
    }

    public function test_pagina_de_aceite_mostra_o_cadastro_e_os_termos_e_exige_o_aceite(): void
    {
        $this->criarPeloAdmin();
        $token = $this->tokenDoLink();

        $this->getJson("/api/parceiros/aceite/{$token}")->assertOk()
            ->assertJsonPath('data.sistema', 'App da Atlética')
            ->assertJsonPath('data.responsavel_nome', 'Bruno Lima')
            ->assertJsonPath('data.permissoes.0.valor', 'espacos:ler')
            ->assertJsonPath('data.renovacao', false)
            ->assertJsonPath('data.termos.versao', '2026-11')
            ->assertJsonMissingPath('data.responsavel_email');

        $this->postJson("/api/parceiros/aceite/{$token}", [])->assertUnprocessable()
            ->assertJsonPath('errors.aceite_termos.0', 'Para liberar o acesso, leia e aceite os termos de uso da API.');
    }

    public function test_aceite_gera_o_token_uma_vez_so_com_as_permissoes_e_a_validade(): void
    {
        $cliente = $this->criarPeloAdmin();
        $link = $this->tokenDoLink();

        $resposta = $this->postJson("/api/parceiros/aceite/{$link}", ['aceite_termos' => true])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.permissoes', ['espacos:ler', 'reservas:criar', 'reservas:ler'])
            ->assertJsonPath('data.expira_em', '2027-11-09T10:00:00-03:00');

        $acesso = PersonalAccessToken::findToken($resposta->json('data.token'));
        $this->assertTrue($acesso->tokenable->is($cliente));
        $this->assertSame(['espacos:ler', 'reservas:criar', 'reservas:ler'], $acesso->abilities);
        $this->assertSame('2027-11-09 10:00:00', $acesso->expires_at->toDateTimeString());

        $cliente->refresh();
        $this->assertSame('ATIVO', $cliente->situacao()->value);
        $this->assertSame('2026-11', $cliente->termos_versao);
        $this->assertNotNull($cliente->termos_aceitos_em);

        $this->postJson("/api/parceiros/aceite/{$link}", ['aceite_termos' => true])->assertNotFound()
            ->assertJsonPath('message', 'Link inválido ou expirado. Peça um novo link a um administrador do sistema de reservas.');
        $this->getJson("/api/parceiros/aceite/{$link}")->assertNotFound();
    }

    public function test_link_vencido_ou_inventado_tem_a_mesma_resposta(): void
    {
        $this->criarPeloAdmin();
        $link = $this->tokenDoLink();

        $this->getJson('/api/parceiros/aceite/'.str_repeat('a', 64))->assertNotFound();

        $this->travel(8)->days();

        $this->getJson("/api/parceiros/aceite/{$link}")->assertNotFound()
            ->assertJsonPath('message', 'Link inválido ou expirado. Peça um novo link a um administrador do sistema de reservas.');
    }

    public function test_token_de_parceiro_nao_entra_em_nenhuma_rota_do_admin(): void
    {
        $this->criarPeloAdmin();
        $token = $this->aceitar($this->tokenDoLink());
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/admin/reservas')->assertForbidden()
            ->assertJsonPath('message', 'Esta rota é só para os admins do sistema.');
        $this->withToken($token)->getJson('/api/me')->assertForbidden();
    }

    public function test_desativar_corta_o_acesso_na_hora_e_reativar_manda_link_novo(): void
    {
        $cliente = $this->criarPeloAdmin();
        $token = $this->aceitar($this->tokenDoLink());

        $this->deleteJson("/api/admin/clientes-api/{$cliente->id}")->assertOk()
            ->assertJsonPath('data.situacao', 'DESATIVADO')
            ->assertJsonPath('data.link_aceite', null);
        $this->assertNull(PersonalAccessToken::findToken($token));

        Mail::fake();
        $this->patchJson("/api/admin/clientes-api/{$cliente->id}", ['ativo' => true])->assertOk()
            ->assertJsonPath('data.situacao', 'AGUARDANDO_ACEITE');
        Mail::assertQueued(AceiteApiMail::class, 1);
    }

    public function test_sistema_desativado_nao_recebe_link(): void
    {
        $cliente = ClienteApi::factory()->desativado()->create();
        $this->comoAdmin();

        $this->postJson("/api/admin/clientes-api/{$cliente->id}/enviar-aceite")->assertConflict();
        Mail::assertNothingQueued();
    }

    public function test_link_novo_sem_revogar_mantem_o_token_atual_ate_vencer(): void
    {
        $cliente = $this->criarPeloAdmin();
        $antigo = $this->aceitar($this->tokenDoLink());

        $this->postJson("/api/admin/clientes-api/{$cliente->id}/enviar-aceite")->assertOk()
            ->assertJsonPath('data.situacao', 'ATIVO');
        $novo = $this->aceitar($this->tokenDoLink());

        $this->assertNotNull(PersonalAccessToken::findToken($antigo));
        $this->assertNotNull(PersonalAccessToken::findToken($novo));
    }

    public function test_link_novo_revogando_corta_o_token_atual_na_hora(): void
    {
        $cliente = $this->criarPeloAdmin();
        $antigo = $this->aceitar($this->tokenDoLink());

        $this->postJson("/api/admin/clientes-api/{$cliente->id}/enviar-aceite", ['revogar_atual' => true])->assertOk()
            ->assertJsonPath('data.situacao', 'AGUARDANDO_ACEITE');

        $this->assertNull(PersonalAccessToken::findToken($antigo));
    }

    public function test_trocar_permissoes_vale_para_o_token_atual(): void
    {
        $cliente = $this->criarPeloAdmin();
        $token = $this->aceitar($this->tokenDoLink());

        $this->patchJson("/api/admin/clientes-api/{$cliente->id}", ['permissoes' => ['espacos:ler']])->assertOk();

        $this->assertSame(['espacos:ler'], PersonalAccessToken::findToken($token)->abilities);
    }

    public function test_trocar_o_responsavel_invalida_o_link_pendente_e_manda_para_o_novo(): void
    {
        $cliente = $this->criarPeloAdmin();
        $antigo = $this->tokenDoLink('bruno.lima@atletica.org');

        $this->patchJson("/api/admin/clientes-api/{$cliente->id}", [
            'responsavel_nome'  => 'Carla Souza',
            'responsavel_email' => 'carla@atletica.org',
        ])->assertOk()->assertJsonPath('data.responsavel.email', 'carla@atletica.org');

        $this->getJson("/api/parceiros/aceite/{$antigo}")->assertNotFound();
        $this->getJson('/api/parceiros/aceite/'.$this->tokenDoLink('carla@atletica.org'))->assertOk()
            ->assertJsonPath('data.responsavel_nome', 'Carla Souza');
    }

    public function test_aviso_de_vencimento_manda_link_de_renovacao_uma_vez(): void
    {
        $vencendo = ClienteApi::factory()->comAcesso()->create(['acesso_expira_em' => now()->addDays(20)]);
        ClienteApi::factory()->comAcesso()->create(['acesso_expira_em' => now()->addDays(60)]);
        ClienteApi::factory()->comAcesso()->desativado()->create(['acesso_expira_em' => now()->addDays(10)]);

        Artisan::call('parceiros:avisar-vencimento');
        Artisan::call('parceiros:avisar-vencimento');

        Mail::assertQueued(AceiteApiMail::class, 1);
        $mail = Mail::queued(AceiteApiMail::class)->first();
        $this->assertTrue($mail->hasTo($vencendo->responsavel_email));
        $this->assertSame('2026-11-29', $mail->acessoVenceEm->toDateString());
        $this->assertSame('2026-11-29', $mail->expiraEm->toDateString());

        $link = basename($mail->link);
        $this->getJson("/api/parceiros/aceite/{$link}")->assertOk()->assertJsonPath('data.renovacao', true);
        $this->aceitar($link);

        $vencendo->refresh();
        $this->assertNull($vencendo->aviso_vencimento_enviado_em);
        $this->assertSame('2027-11-09', $vencendo->acesso_expira_em->toDateString());
    }

    public function test_situacao_vencido_quando_passa_da_validade(): void
    {
        $cliente = ClienteApi::factory()->comAcesso()->create(['acesso_expira_em' => now()->addDay()]);
        $this->comoAdmin();

        $this->travel(2)->days();

        $this->getJson("/api/admin/clientes-api/{$cliente->id}")->assertOk()->assertJsonPath('data.situacao', 'VENCIDO');
    }

    public function test_cadastro_e_aceite_ficam_no_historico_sem_o_hash_do_link(): void
    {
        $cliente = $this->criarPeloAdmin();
        $this->aceitar($this->tokenDoLink());

        $auditorias = Auditoria::where('auditavel_type', 'cliente_api')->where('auditavel_id', $cliente->id)->orderBy('id')->get();

        $this->assertSame('CRIACAO', $auditorias->first()->acao->value);
        $this->assertNotNull($auditorias->first()->user_id);
        $this->assertTrue($auditorias->contains(fn (Auditoria $a) => ($a->depois['aceite_token_hash'] ?? null) === '[oculto]'));
        $this->assertFalse($auditorias->contains(fn (Auditoria $a) => str_contains(json_encode($a->depois), $cliente->fresh()->aceite_token_hash ?? 'nada-aqui')));
    }

    public function test_acao_feita_por_parceiro_fica_no_historico_com_o_sistema_e_sem_admin(): void
    {
        $cliente = ClienteApi::factory()->comAcesso()->create(['nome' => 'App da Atlética']);
        Sanctum::actingAs($cliente, ['reservas:criar']);

        $reserva = Reserva::factory()->create();

        $this->assertNull($reserva->criado_por);
        $auditoria = $reserva->auditorias()->firstOrFail();
        $this->assertNull($auditoria->user_id);
        $this->assertSame($cliente->id, $auditoria->cliente_api_id);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/admin/auditorias?cliente_api_id={$cliente->id}&tipo=RESERVA")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.feito_por', null)
            ->assertJsonPath('data.0.sistema_parceiro.nome', 'App da Atlética');
    }

    public function test_lista_busca_por_nome_ou_organizacao(): void
    {
        ClienteApi::factory()->create(['nome' => 'Portal Acadêmico', 'organizacao' => 'TI da Faculdade']);
        ClienteApi::factory()->create(['nome' => 'App da Atlética', 'organizacao' => 'Atlética']);
        $this->comoAdmin();

        $this->getJson('/api/admin/clientes-api?busca=portal')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'Portal Acadêmico');
        $this->getJson('/api/admin/clientes-api?busca=TI da')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/clientes-api/permissoes')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_sessao_do_admin_continua_vencendo_em_oito_horas(): void
    {
        $admin = User::factory()->create(['email' => 'eric@fac.edu']);
        $token = $this->entrar('eric@fac.edu', 'password')->assertOk()->json('token');

        $this->travel(7)->hours();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->travel(2)->hours();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->assertTrue($admin->is(PersonalAccessToken::findToken($token)->tokenable));
    }
}
