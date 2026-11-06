<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportacaoReservasTest extends TestCase
{
    use RefreshDatabase;

    private function exportar(array $params = [])
    {
        return $this->get('/api/admin/reservas/exportar?' . http_build_query($params), ['Accept' => 'application/json']);
    }

    private function linhas($resposta): array
    {
        $conteudo = $resposta->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}", $conteudo);

        $linhas = array_filter(explode("\n", substr($conteudo, 3)));

        return array_values(array_map(fn ($linha) => str_getcsv($linha, ';', '"', ''), $linhas));
    }

    public function test_exige_login(): void
    {
        $this->exportar()->assertUnauthorized();
        $this->getJson('/api/admin/reservas/exportar/colunas')->assertUnauthorized();
    }

    public function test_exporta_so_as_colunas_obrigatorias_por_padrao(): void
    {
        $this->comoAdmin();

        $aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza']);
        $espaco = Espaco::factory()->quadra()->create(['nome' => 'Quadra']);
        $reserva = Reserva::factory()->create(['data_reserva' => '2026-11-10', 'aluno_id' => $aluno->id, 'espaco_id' => $espaco->id]);

        $resposta = $this->exportar()->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('reservas_a_partir_de_2026-11-09.csv');

        $this->assertSame([
            ['ID', 'Data', 'Horário', 'Espaço', 'Tipo', 'Status', 'Responsável'],
            [(string) $reserva->id, '10/11/2026', '19:00–20:30', 'Quadra', 'ALUNO', 'RESERVADO', 'Maria Souza'],
        ], $this->linhas($resposta));
    }

    public function test_exporta_colunas_opcionais_escolhidas(): void
    {
        $admin = $this->comoAdmin();

        $curso = Curso::factory()->create(['nome' => 'Enfermagem']);
        $aluno = Aluno::factory()->create(['registro_academico' => '2024000001', 'email' => 'maria@email.com', 'curso_id' => $curso->id]);
        $reserva = Reserva::factory()->revogada()->create([
            'data_reserva'            => '2026-11-10',
            'aluno_id'                => $aluno->id,
            'revogada_por'            => $admin->id,
            'justificativa_revogacao' => 'Evento da coordenação',
        ]);
        $reserva->equipamentos()->attach(Equipamento::factory()->create(['nome' => 'Bola'])->id, ['quantidade' => 2]);

        $linhas = $this->linhas($this->exportar(['colunas' => ['curso', 'registro_academico', 'equipamentos', 'revogacao']])->assertOk());

        $this->assertSame(
            ['ID', 'Data', 'Horário', 'Espaço', 'Tipo', 'Status', 'Responsável', 'RA', 'Curso', 'Equipamentos', 'Revogada por', 'Revogada em', 'Justificativa'],
            $linhas[0],
        );
        $this->assertSame(['2024000001', 'Enfermagem', 'Bola x2', $admin->name], array_slice($linhas[1], 7, 4));
        $this->assertSame('Evento da coordenação', $linhas[1][12]);
        $this->assertNotContains('maria@email.com', $linhas[1]);
    }

    public function test_todas_as_colunas(): void
    {
        $this->comoAdmin();
        Reserva::factory()->create();

        $linhas = $this->linhas($this->exportar(['colunas' => ['todas']]));

        $this->assertCount(7 + 10, $linhas[0]);
        $this->assertCount(7 + 10, $linhas[1]);
    }

    public function test_evento_tem_o_admin_como_responsavel(): void
    {
        $this->comoAdmin();
        Reserva::factory()->evento()->create(['user_id' => User::factory()->create(['name' => 'Coordenação'])->id]);

        $this->assertSame('Coordenação', $this->linhas($this->exportar())[1][6]);
    }

    public function test_periodo_especifico_e_atalhos_de_periodo(): void
    {
        $this->comoAdmin();

        $outubro = Reserva::factory()->create(['data_reserva' => '2026-10-20']);
        $estaSemana = Reserva::factory()->create(['data_reserva' => '2026-11-11']);
        $proximaSemana = Reserva::factory()->create(['data_reserva' => '2026-11-17']);

        $ids = fn ($params) => array_column(array_slice($this->linhas($this->exportar($params)), 1), 0);

        $this->assertSame([(string) $outubro->id], $ids(['de' => '2026-10-01', 'ate' => '2026-10-31']));
        $this->assertSame([(string) $outubro->id], $ids(['periodo' => 'MES_ANTERIOR']));
        $this->assertSame([(string) $estaSemana->id], $ids(['periodo' => 'SEMANA_ATUAL']));
        $this->assertSame([(string) $proximaSemana->id], $ids(['periodo' => 'PROXIMA_SEMANA']));
        $this->assertSame([(string) $estaSemana->id, (string) $proximaSemana->id], $ids(['periodo' => 'MES_ATUAL']));

        $this->exportar(['periodo' => 'MES_ATUAL'])->assertDownload('reservas_2026-11-01_a_2026-11-30.csv');
    }

    public function test_aplica_os_mesmos_filtros_da_listagem(): void
    {
        $this->comoAdmin();

        $curso = Curso::factory()->create();
        $doCurso = Reserva::factory()->create(['aluno_id' => Aluno::factory()->create(['curso_id' => $curso->id])->id]);
        Reserva::factory()->create();
        Reserva::factory()->revogada()->create(['aluno_id' => Aluno::factory()->create(['curso_id' => $curso->id])->id]);

        $linhas = $this->linhas($this->exportar(['curso_id' => $curso->id, 'status' => 'RESERVADO']));

        $this->assertCount(2, $linhas);
        $this->assertSame((string) $doCurso->id, $linhas[1][0]);
    }

    public function test_exporta_mais_de_um_lote(): void
    {
        $this->comoAdmin();
        Reserva::factory()->count(510)->create();

        $this->assertCount(511, $this->linhas($this->exportar()));
    }

    public function test_protege_contra_formulas_no_excel(): void
    {
        $this->comoAdmin();
        Reserva::factory()->create(['aluno_id' => Aluno::factory()->create(['nome_completo' => '=HYPERLINK("http://x")'])->id]);

        $this->assertSame("'=HYPERLINK(\"http://x\")", $this->linhas($this->exportar())[1][6]);
    }

    public function test_lista_as_colunas_disponiveis(): void
    {
        $this->comoAdmin();

        $this->getJson('/api/admin/reservas/exportar/colunas')->assertOk()
            ->assertJsonCount(7, 'data.obrigatorias')
            ->assertJsonCount(8, 'data.opcionais')
            ->assertJsonPath('data.opcionais.0.chave', 'tipo_espaco');
    }

    public function test_validacoes(): void
    {
        $this->comoAdmin();

        $this->exportar(['colunas' => ['senha']])->assertStatus(422)->assertJsonValidationErrors('colunas.0');
        $this->exportar(['periodo' => 'SECULO'])->assertStatus(422)->assertJsonValidationErrors('periodo');
        $this->exportar(['periodo' => 'HOJE', 'de' => '2026-11-10'])->assertStatus(422)->assertJsonValidationErrors('periodo');
        $this->exportar(['de' => '2025-01-01', 'ate' => '2026-11-09'])->assertStatus(422)->assertJsonValidationErrors('ate');
    }

    public function test_listagem_tambem_aceita_atalho_de_periodo(): void
    {
        $this->comoAdmin();

        Reserva::factory()->create(['data_reserva' => '2026-11-09']);
        Reserva::factory()->create(['data_reserva' => '2026-11-10']);

        $this->getJson('/api/admin/reservas?periodo=HOJE')->assertOk()->assertJsonCount(1, 'data');
    }
}
