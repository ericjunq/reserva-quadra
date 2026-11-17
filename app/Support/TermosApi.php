<?php

namespace App\Support;

use App\Constants\PermissaoApiConstant;

class TermosApi
{
    public static function atuais(): array
    {
        return [
            'versao'   => config('reservas.parceiros.termos_versao'),
            'rascunho' => true,
            'secoes'   => self::secoes(),
        ];
    }

    public static function permissoes(): array
    {
        return array_map(fn (PermissaoApiConstant $permissao) => [
            'valor' => $permissao->value,
            'nome'  => $permissao->label(),
        ], PermissaoApiConstant::cases());
    }

    private static function secoes(): array
    {
        $validade = config('reservas.parceiros.validade_meses');
        $aviso = config('reservas.parceiros.aviso_dias_antes');

        return [
            [
                'titulo'     => 'Para que serve o acesso',
                'paragrafos' => [
                    'O acesso à API permite que um sistema parceiro consulte os espaços e faça reservas em nome de alunos da instituição '.Termos::A_CONFIRMAR.', só dentro das permissões e da finalidade informadas no cadastro.',
                    'A instituição é a controladora dos dados do sistema de reservas. O sistema parceiro usa a API em nome da instituição, e a pessoa responsável indicada no cadastro responde pelo uso que o sistema faz dela.',
                ],
            ],
            [
                'titulo'     => 'Dados pessoais',
                'paragrafos' => [
                    'Envie à API só o RA de um aluno que pediu a reserva pelo seu sistema. Não faça reservas sem o pedido do aluno nem com dados de terceiros.',
                    'Toda reserva feita pela API precisa ser confirmada pelo aluno no email dele, com o aceite dos termos do sistema de reservas. Sem essa confirmação, a reserva é cancelada.',
                    'A API não devolve nome, email nem curso de alunos, nem reservas feitas por outros sistemas. Guarde no seu sistema só o necessário para a finalidade cadastrada e informe os seus usuários sobre o envio dos dados ao sistema de reservas.',
                ],
            ],
            [
                'titulo'     => 'Segurança do token',
                'paragrafos' => [
                    'O token é pessoal do sistema cadastrado: guarde-o num cofre de senhas ou variável de ambiente do servidor, nunca em código público, no navegador, em aplicativos instalados nos aparelhos dos usuários ou em mensagens.',
                    'Use sempre HTTPS e não registre o token nem dados pessoais em logs.',
                    'Se o token vazar, avise a instituição na hora. Um administrador revoga o token e envia um link para gerar outro.',
                ],
            ],
            [
                'titulo'     => 'Validade, limites e suspensão',
                'paragrafos' => [
                    "O token vale por {$validade} meses. Cerca de {$aviso} dias antes do vencimento, a pessoa responsável recebe um link para aceitar de novo os termos e gerar um token novo.",
                    'Cada sistema tem um limite de requisições por minuto. Acima dele, a API responde 429 e informa quando tentar de novo.',
                    'A instituição pode suspender o acesso a qualquer momento em caso de uso fora da finalidade, risco à segurança ou descumprimento destes termos.',
                ],
            ],
            [
                'titulo'     => 'Mudanças na API',
                'paragrafos' => [
                    'Os endereços da API começam com /api/v1. Mudanças que não quebram o seu sistema (campos ou rotas novas) entram na mesma versão. Mudanças que quebrariam viram uma versão nova, e a anterior fica no ar por um prazo avisado por email.',
                    'Dúvidas e incidentes: '.Termos::A_CONFIRMAR.'.',
                ],
            ],
        ];
    }
}
