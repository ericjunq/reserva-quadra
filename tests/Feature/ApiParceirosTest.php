<?php

namespace Tests\Feature;

use App\Constants\StatusConstant;
use App\Mail\PedidoConfirmacaoMail;
use App\Mail\ReservaCanceladaMail;
use App\Models\Aluno;
use App\Models\ClienteApi;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiParceirosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function comoParceiro(array $permissoes = ['espacos:ler', 'reservas:criar', 'reservas:ler', 'reservas:cancelar'], array $extra = []): ClienteApi
    {
        $cliente = ClienteApi::factory()->comAcesso()->create(['permissoes' => $permissoes, 'nome' => 'App da Atlética', ...$extra]);
        Sanctum::actingAs($cliente, $permissoes);

        return $cliente;
    }

    private function payload(Aluno $aluno, Espaco $espaco, array $extra = []): array
    {
        return [
            'registro_academico' => $aluno->registro_academico,
            'espaco_id'          => $espaco->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => 'PRIMEIRO_HORARIO',
            ...$extra,
        ];
    }

    public function test_sem_token_a_v1_responde_401(): void
    {
        $this->getJson('/api/v1/espacos')->assertUnauthorized();
        $this->postJson('/api/v1/reservas', [])->assertUnauthorized();
    }

    public function test_token_de_admin_nao_usa_a_v1(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/espacos')->assertForbidden()
            ->assertJsonPath('message', 'Esta rota é só para sistemas parceiros.');
    }

    public function test_sem_a_permissao_a_rota_responde_403_com_a_permissao_que_falta(): void
    {
        $this->comoParceiro(['espacos:ler']);

        $this->getJson('/api/v1/reservas')->assertForbidden()
            ->assertJsonPath('message', 'O token deste sistema não tem a permissão necessária para esta ação.')
            ->assertJsonPath('dados.permissoes_necessarias', ['reservas:ler']);
        $this->getJson('/api/v1/espacos')->assertOk();
    }

    public function test_sistema_desativado_perde_o_acesso_e_os_tokens(): void
    {
        $cliente = ClienteApi::factory()->comAcesso()->create();
        $token = $cliente->createToken('parceiro', ['espacos:ler'], now()->addYear())->plainTextToken;
        $cliente->forceFill(['ativo' => false])->saveQuietly();

        $this->withToken($token)->getJson('/api/v1/espacos')->assertUnauthorized();
        $this->assertSame(0, $cliente->tokens()->count());
    }

    public function test_token_vencido_e_recusado(): void
    {
        $cliente = ClienteApi::factory()->comAcesso()->create();
        $token = $cliente->createToken('parceiro', ['espacos:ler'], now()->addDay())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/espacos')->assertOk();

        $this->travel(2)->days();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/espacos')->assertUnauthorized();
    }

    public function test_espacos_e_equipamentos_mostram_so_o_que_o_site_publico_mostra(): void
    {
        $this->comoParceiro(['espacos:ler']);
        $quadra = Espaco::factory()->create(['nome' => 'Quadra']);
        Espaco::factory()->inativo()->create();
        $aluno = Aluno::factory()->create(['nome_completo' => 'Carla Mendes']);
        Reserva::factory()->create(['espaco_id' => $quadra->id, 'aluno_id' => $aluno->id, 'data_reserva' => '2026-11-10']);

        $this->getJson('/api/v1/espacos')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Quadra')
            ->assertJsonMissingPath('data.0.criado_por')
            ->assertJsonMissingPath('data.0.ativo');

        $calendario = $this->getJson("/api/v1/espacos/{$quadra->id}/disponibilidade?de=2026-11-10")->assertOk()->getContent();

        $this->assertStringNotContainsString('Carla', $calendario);
        $this->assertStringNotContainsString($aluno->registro_academico, $calendario);
        $this->assertStringNotContainsString($aluno->email, $calendario);
        $this->getJson("/api/v1/espacos/{$quadra->id}/equipamentos")->assertOk();
    }

    public function test_parceiro_reserva_pelo_ra_com_as_regras_do_site_e_o_aluno_confirma_pelo_email(): void
    {
        $cliente = $this->comoParceiro();
        $aluno = Aluno::factory()->create();
        $espaco = Espaco::factory()->create();

        $resposta = $this->postJson('/api/v1/reservas', $this->payload($aluno, $espaco))->assertCreated()
            ->assertJsonPath('data.status', 'RESERVADO')
            ->assertJsonPath('data.situacao_confirmacao', 'AGUARDANDO_CONFIRMACAO')
            ->assertJsonPath('data.espaco.id', $espaco->id);

        foreach (['aluno', 'aluno_id', 'registro_academico', 'email', 'nome_completo', 'curso'] as $campo) {
            $resposta->assertJsonMissingPath("data.{$campo}");
        }

        $reserva = Reserva::findOrFail($resposta->json('data.id'));
        $this->assertSame('API', $reserva->origem->value);
        $this->assertSame($cliente->id, $reserva->cliente_api_id);
        $this->assertNull($reserva->criado_por);
        $this->assertSame($cliente->id, $reserva->auditorias()->first()->cliente_api_id);
        Mail::assertQueued(PedidoConfirmacaoMail::class, fn ($m) => $m->hasTo($aluno->email));

        $this->postJson('/api/v1/reservas', $this->payload(Aluno::factory()->create(), $espaco))->assertConflict();
        $this->postJson('/api/v1/reservas', $this->payload($aluno, $espaco, ['registro_academico' => '0000000000', 'horario' => 'SEGUNDO_HORARIO']))
            ->assertUnprocessable()->assertJsonValidationErrors('registro_academico');
    }

    public function test_admin_ve_qual_sistema_fez_a_reserva_e_filtra_pela_origem(): void
    {
        $cliente = ClienteApi::factory()->comAcesso()->create(['nome' => 'App da Atlética']);
        $reserva = Reserva::factory()->create(['origem' => 'API', 'cliente_api_id' => $cliente->id]);
        Reserva::factory()->create();
        $this->comoAdmin();

        $this->getJson("/api/admin/reservas/{$reserva->id}")->assertOk()
            ->assertJsonPath('data.origem', 'API')
            ->assertJsonPath('data.sistema_parceiro.nome', 'App da Atlética');
        $this->getJson('/api/admin/reservas?origem=API')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_parceiro_ve_so_as_proprias_reservas_sem_dados_pessoais(): void
    {
        $cliente = $this->comoParceiro(['reservas:ler']);
        $outro = ClienteApi::factory()->comAcesso()->create();
        $minha = Reserva::factory()->create(['origem' => 'API', 'cliente_api_id' => $cliente->id, 'data_reserva' => '2026-11-10']);
        Reserva::factory()->create(['origem' => 'API', 'cliente_api_id' => $cliente->id, 'data_reserva' => '2026-11-12', 'status' => StatusConstant::CANCELADO]);
        $deOutro = Reserva::factory()->create(['origem' => 'API', 'cliente_api_id' => $outro->id]);
        $doSite = Reserva::factory()->create();

        $this->getJson('/api/v1/reservas')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $minha->id)
            ->assertJsonMissingPath('data.0.aluno_id');
        $this->getJson('/api/v1/reservas?status=CANCELADO')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/reservas?de=2026-11-11')->assertOk()->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/reservas/{$minha->id}")->assertOk()->assertJsonPath('data.id', $minha->id);
        $this->getJson("/api/v1/reservas/{$deOutro->id}")->assertNotFound();
        $this->getJson("/api/v1/reservas/{$doSite->id}")->assertNotFound();
    }

    public function test_listagem_da_v1_nao_faz_uma_consulta_por_reserva(): void
    {
        $cliente = $this->comoParceiro(['reservas:ler']);
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/reservas')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        Reserva::factory()->count(2)->create(['origem' => 'API', 'cliente_api_id' => $cliente->id]);
        $poucas = $contar();
        Reserva::factory()->count(10)->create(['origem' => 'API', 'cliente_api_id' => $cliente->id]);

        $this->assertSame($poucas, $contar());
    }

    public function test_parceiro_cancela_so_as_proprias_reservas_e_o_aluno_e_avisado(): void
    {
        $cliente = $this->comoParceiro(['reservas:cancelar']);
        $minha = Reserva::factory()->create(['origem' => 'API', 'cliente_api_id' => $cliente->id]);
        $doSite = Reserva::factory()->create();

        $this->postJson("/api/v1/reservas/{$minha->id}/cancelar")->assertOk()->assertJsonPath('data.status', 'CANCELADO');
        Mail::assertQueued(ReservaCanceladaMail::class, 1);

        $this->postJson("/api/v1/reservas/{$minha->id}/cancelar")->assertConflict();
        $this->postJson("/api/v1/reservas/{$doSite->id}/cancelar")->assertNotFound();
        $this->assertSame(StatusConstant::RESERVADO, $doSite->fresh()->status);
    }

    public function test_limite_por_minuto_e_de_cada_sistema(): void
    {
        $this->app->forgetInstance(ThrottleRequests::class);

        $limitado = ClienteApi::factory()->comAcesso()->create(['limite_por_minuto' => 2, 'permissoes' => ['espacos:ler']]);
        $outro = ClienteApi::factory()->comAcesso()->create(['permissoes' => ['espacos:ler']]);
        $tokenLimitado = $limitado->createToken('parceiro', ['espacos:ler'], now()->addYear())->plainTextToken;
        $tokenOutro = $outro->createToken('parceiro', ['espacos:ler'], now()->addYear())->plainTextToken;

        $chamar = function (string $token) {
            $this->app['auth']->forgetGuards();

            return $this->withToken($token)->getJson('/api/v1/espacos');
        };

        $chamar($tokenLimitado)->assertOk();
        $chamar($tokenLimitado)->assertOk();
        $chamar($tokenLimitado)->assertStatus(429)->assertHeader('Retry-After');
        $chamar($tokenOutro)->assertOk();
    }
}
