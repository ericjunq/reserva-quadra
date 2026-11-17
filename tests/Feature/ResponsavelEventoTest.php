<?php

namespace Tests\Feature;

use App\Mail\DevolucaoComPendenciaMail;
use App\Models\Auditoria;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResponsavelEventoTest extends TestCase
{
    use RefreshDatabase;

    private function evento(array $extra = []): array
    {
        return [
            'espaco_id'    => Espaco::factory()->create()->id,
            'data_reserva' => '2026-11-10',
            'horario'      => 'PRIMEIRO_HORARIO',
            'descricao'    => 'Palestra de abertura',
            ...$extra,
        ];
    }

    public function test_todo_evento_exige_o_nome_do_responsavel_e_o_contato_e_opcional(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/admin/reservas/eventos', $this->evento())
            ->assertJsonValidationErrors('responsavel_nome');

        $this->postJson('/api/admin/reservas/eventos', $this->evento(['responsavel_nome' => 'Prof. Marcos Lima']))
            ->assertCreated()
            ->assertJsonPath('data.responsavel.nome', 'Prof. Marcos Lima')
            ->assertJsonPath('data.responsavel.contato', null);
    }

    public function test_registro_posterior_de_grupo_exige_o_responsavel_e_o_de_aluno_nao_aceita(): void
    {
        $this->comoAdmin();
        $base = ['espaco_id' => Espaco::factory()->create()->id, 'data_reserva' => '2026-11-06', 'horario' => 'PRIMEIRO_HORARIO'];

        $this->postJson('/api/admin/reservas/registro-posterior', $base + ['descricao' => 'Turma de Enfermagem'])
            ->assertJsonValidationErrors(['responsavel_nome' => 'Informe o responsável pelo grupo.']);
        $this->postJson('/api/admin/reservas/registro-posterior', $base + [
            'descricao' => 'Turma de Enfermagem', 'responsavel_nome' => 'Prof. Ana', 'responsavel_contato' => 'ana@fac.edu',
        ])->assertCreated()->assertJsonPath('data.responsavel.contato', 'ana@fac.edu');
    }

    public function test_responsavel_aparece_so_para_os_admins(): void
    {
        $evento = Reserva::factory()->evento()->create(['data_reserva' => '2026-11-10', 'responsavel_contato' => '(77) 99999-0000']);

        $publico = $this->getJson("/api/espacos/{$evento->espaco_id}/disponibilidade?de=2026-11-09&ate=2026-11-13")->assertOk();
        $this->assertStringNotContainsString('Marcos', $publico->getContent());
        $this->assertStringNotContainsString('99999', $publico->getContent());

        $this->comoAdmin();
        $this->getJson("/api/admin/reservas/{$evento->id}")
            ->assertJsonPath('data.responsavel.nome', 'Prof. Marcos Lima')
            ->assertJsonPath('data.responsavel.contato', '(77) 99999-0000');
        $this->getJson('/api/admin/reservas/'.Reserva::factory()->create()->id)->assertJsonMissingPath('data.responsavel');
    }

    public function test_corrigir_e_remover_o_responsavel_a_pedido_apaga_tambem_do_historico(): void
    {
        $this->comoAdmin();
        $evento = Reserva::factory()->evento()->create(['responsavel_contato' => 'marcos@fac.edu']);
        $url = "/api/admin/reservas/{$evento->id}/responsavel";

        $this->patchJson($url, ['responsavel_nome' => 'Prof. Marcos Lima Souza'])->assertOk()
            ->assertJsonPath('data.responsavel.nome', 'Prof. Marcos Lima Souza')
            ->assertJsonPath('data.responsavel.contato', null);

        $this->patchJson($url, ['remover' => true])->assertOk()->assertJsonPath('data.responsavel.nome', null);

        $historico = Auditoria::where('auditavel_type', 'reserva')->where('auditavel_id', $evento->id)->get()->toJson();
        $this->assertStringNotContainsString('Marcos', $historico);
        $this->assertStringNotContainsString('marcos@fac.edu', $historico);
        $this->assertStringContainsString('[removido]', $historico);
        $this->assertSame('Evento da faculdade', $evento->fresh()->descricao);

        $this->patchJson('/api/admin/reservas/'.Reserva::factory()->create()->id.'/responsavel', ['responsavel_nome' => 'Fulano'])
            ->assertStatus(422)->assertJsonPath('message', 'Só eventos têm responsável.');
    }

    public function test_pendencia_na_devolucao_do_evento_vai_para_o_contato_se_for_email(): void
    {
        Mail::fake();
        $this->comoAdmin();
        $bola = Equipamento::factory()->create(['quantidade_total' => 3]);
        $comEmail = Reserva::factory()->evento()->create(['espaco_id' => $bola->espaco_id, 'data_reserva' => '2026-11-06', 'responsavel_contato' => 'marcos@fac.edu']);
        $comTelefone = Reserva::factory()->evento()->create(['espaco_id' => $bola->espaco_id, 'data_reserva' => '2026-11-05', 'responsavel_contato' => '(77) 99999-0000']);
        foreach ([$comEmail, $comTelefone] as $evento) {
            $evento->equipamentos()->attach($bola->id, ['quantidade' => 2]);
            $this->postJson("/api/admin/reservas/{$evento->id}/devolucao", ['itens' => [
                ['equipamento_id' => $bola->id, 'quantidade_devolvida' => 1, 'situacao' => 'NAO_DEVOLVIDO', 'observacao' => 'Uma bola sumiu.'],
            ]])->assertOk();
        }

        Mail::assertQueued(DevolucaoComPendenciaMail::class, 1);
        Mail::assertQueued(DevolucaoComPendenciaMail::class, fn ($mail) => $mail->hasTo('marcos@fac.edu') && $mail->nome === 'Prof. Marcos Lima');
    }
}
