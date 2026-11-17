<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Auditoria;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SugestaoCompartilhamentoTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $sala;

    private Espaco $laboratorio;

    private Equipamento $projetor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sala = Espaco::factory()->create(['nome' => 'Sala 10']);
        $this->laboratorio = Espaco::factory()->create(['nome' => 'Lab 2']);
        $this->projetor = Equipamento::factory()->for($this->sala)->create(['nome' => 'Projetor', 'aceita_solicitacao' => true]);
    }

    private function pedidos(int $quantos, string $situacao = 'APROVADA', ?CarbonInterface $quando = null, ?Espaco $espaco = null, ?Equipamento $equipamento = null): void
    {
        for ($i = 0; $i < $quantos; $i++) {
            $reserva = Reserva::factory()->create(['espaco_id' => ($espaco ?? $this->laboratorio)->id]);
            $reserva->equipamentos()->attach(($equipamento ?? $this->projetor)->id, [
                'quantidade'           => 1,
                'situacao_solicitacao' => $situacao,
                'respondida_em'        => $quando ?? now()->subDays(2),
            ]);
        }
    }

    private function sugestoes()
    {
        return $this->getJson('/api/admin/equipamentos/sugestoes-compartilhamento')->assertOk();
    }

    public function test_exige_login(): void
    {
        $this->getJson('/api/admin/equipamentos/sugestoes-compartilhamento')->assertUnauthorized();
        $this->postJson("/api/admin/equipamentos/{$this->projetor->id}/sugestoes-compartilhamento/{$this->laboratorio->id}/aceitar")->assertUnauthorized();
    }

    public function test_tres_pedidos_aprovados_no_periodo_viram_sugestao(): void
    {
        $this->pedidos(2);
        $this->comoAdmin();

        $this->sugestoes()->assertJsonCount(0, 'data');

        $this->pedidos(1);

        $this->sugestoes()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.equipamento.nome', 'Projetor')
            ->assertJsonPath('data.0.espaco_de_origem.nome', 'Sala 10')
            ->assertJsonPath('data.0.espaco_que_pede.nome', 'Lab 2')
            ->assertJsonPath('data.0.pedidos', 3);
    }

    public function test_recusados_e_antigos_nao_contam(): void
    {
        $this->pedidos(2);
        $this->pedidos(3, 'RECUSADA');
        $this->pedidos(3, 'VENCIDA');
        $this->pedidos(3, 'APROVADA', now()->subDays(61));
        $this->comoAdmin();

        $this->sugestoes()->assertJsonCount(0, 'data');
    }

    public function test_ja_compartilhado_ou_desativado_nao_vira_sugestao(): void
    {
        $this->pedidos(3);
        $this->comoAdmin();
        $this->sugestoes()->assertJsonCount(1, 'data');

        $this->projetor->update(['ativo' => false]);
        $this->sugestoes()->assertJsonCount(0, 'data');

        $this->projetor->update(['ativo' => true]);
        $this->laboratorio->update(['ativo' => false]);
        $this->sugestoes()->assertJsonCount(0, 'data');

        $this->laboratorio->update(['ativo' => true]);
        $this->projetor->espacosCompartilhados()->attach($this->laboratorio->id);
        $this->sugestoes()->assertJsonCount(0, 'data');
    }

    public function test_aceitar_compartilha_com_historico_e_a_sugestao_some(): void
    {
        $outro = Espaco::factory()->create();
        $this->projetor->espacosCompartilhados()->attach($outro->id);
        $this->pedidos(3);
        $admin = $this->comoAdmin();

        $this->postJson("/api/admin/equipamentos/{$this->projetor->id}/sugestoes-compartilhamento/{$this->laboratorio->id}/aceitar")
            ->assertOk()
            ->assertJsonCount(2, 'data.espacos_compartilhados');

        $this->assertEqualsCanonicalizing([$outro->id, $this->laboratorio->id], $this->projetor->espacosCompartilhados()->pluck('espacos.id')->all());
        $auditoria = Auditoria::where('auditavel_type', 'equipamento')->where('auditavel_id', $this->projetor->id)->latest('id')->first();
        $this->assertSame($admin->id, $auditoria->user_id);
        $this->assertEqualsCanonicalizing([$outro->id, $this->laboratorio->id], $auditoria->depois['espacos_compartilhados']);
        $this->sugestoes()->assertJsonCount(0, 'data');

        $this->postJson("/api/admin/equipamentos/{$this->projetor->id}/sugestoes-compartilhamento/{$this->sala->id}/aceitar")
            ->assertUnprocessable()->assertJsonPath('message', 'O equipamento já pertence a este espaço.');
    }

    public function test_dispensar_esconde_ate_surgirem_tres_pedidos_novos(): void
    {
        $this->pedidos(4);
        $this->comoAdmin();

        $this->postJson("/api/admin/equipamentos/{$this->projetor->id}/sugestoes-compartilhamento/{$this->laboratorio->id}/dispensar")->assertOk();
        $this->sugestoes()->assertJsonCount(0, 'data');

        $this->travel(1)->day();
        $this->pedidos(2, 'APROVADA', now());
        $this->sugestoes()->assertJsonCount(0, 'data');

        $this->pedidos(1, 'APROVADA', now());
        $this->sugestoes()->assertJsonCount(1, 'data')->assertJsonPath('data.0.pedidos', 3);

        $this->postJson("/api/admin/equipamentos/{$this->projetor->id}/sugestoes-compartilhamento/{$this->laboratorio->id}/dispensar")->assertOk();
        $this->assertSame(1, DB::table('sugestoes_dispensadas')->count());
        $this->sugestoes()->assertJsonCount(0, 'data');
    }

    public function test_filtra_por_equipamento_ordena_pelo_mais_pedido_e_nao_mostra_alunos(): void
    {
        $bola = Equipamento::factory()->for($this->sala)->create(['nome' => 'Bola', 'aceita_solicitacao' => true]);
        $aluno = Aluno::factory()->create(['nome_completo' => 'Carla Mendes']);
        $this->pedidos(3);
        $this->pedidos(5, equipamento: $bola);
        Reserva::query()->update(['aluno_id' => $aluno->id]);
        $this->comoAdmin();

        $resposta = $this->sugestoes()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.equipamento.nome', 'Bola')
            ->assertJsonPath('data.1.equipamento.nome', 'Projetor');
        $this->assertStringNotContainsString('Carla', $resposta->getContent());
        $this->assertStringNotContainsString($aluno->registro_academico, $resposta->getContent());

        $this->getJson("/api/admin/equipamentos/sugestoes-compartilhamento?equipamento_id={$this->projetor->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.equipamento.nome', 'Projetor');
    }

    public function test_consultas_nao_crescem_com_o_numero_de_sugestoes(): void
    {
        $this->comoAdmin();
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->sugestoes();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->pedidos(3);
        $poucas = $contar();

        for ($i = 0; $i < 5; $i++) {
            $this->pedidos(3, equipamento: Equipamento::factory()->for($this->sala)->create(['aceita_solicitacao' => true]));
        }

        $this->sugestoes()->assertJsonCount(6, 'data');
        $this->assertSame($poucas, $contar());
    }
}
