<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentacaoApiTest extends TestCase
{
    use RefreshDatabase;

    private const ARQUIVO = 'docs/api-v1.openapi.json';

    public function test_arquivo_openapi_da_v1_esta_atualizado_com_o_codigo(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('O arquivo é gerado e conferido no SQLite, porque os tipos das colunas mudam de banco para banco.');
        }

        $gerado = tempnam(sys_get_temp_dir(), 'openapi');
        Artisan::call('scramble:export', ['--path' => $gerado]);

        if (getenv('ATUALIZAR_OPENAPI') === '1') {
            copy($gerado, base_path(self::ARQUIVO));
        }

        $this->assertJsonFileEqualsJsonFile(
            base_path(self::ARQUIVO),
            $gerado,
            'O '.self::ARQUIVO.' está desatualizado. Rode: ATUALIZAR_OPENAPI=1 php artisan test --filter=DocumentacaoApiTest',
        );

        unlink($gerado);
    }

    public function test_documentacao_cobre_so_a_v1_com_token_bearer_e_sem_dados_pessoais_do_aluno(): void
    {
        $documento = json_decode((string) file_get_contents(base_path(self::ARQUIVO)), true);
        $reserva = json_encode($documento['components']['schemas']['ReservaResource']);

        $this->assertSame('https://{dominio}/api/v1', $documento['servers'][0]['url']);
        $this->assertSame('bearer', $documento['components']['securitySchemes']['http']['scheme']);
        $this->assertContains('/reservas', array_keys($documento['paths']));
        $this->assertEmpty(array_filter(array_keys($documento['paths']), fn (string $caminho) => str_contains($caminho, 'admin')));

        foreach (['registro_academico', 'email', 'nome_completo', 'curso', 'aluno'] as $campo) {
            $this->assertStringNotContainsString($campo, $reserva);
        }
    }
}
