<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraducaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_mensagens_de_validacao_em_portugues_com_o_nome_do_campo(): void
    {
        $this->postJson('/api/login', [])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'É obrigatória a indicação de um valor para o campo email.')
            ->assertJsonPath('errors.password.0', 'É obrigatória a indicação de um valor para o campo senha.');
    }
}
