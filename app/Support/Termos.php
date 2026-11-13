<?php

namespace App\Support;

class Termos
{
    public const A_CONFIRMAR = '[a confirmar com a instituição]';

    public static function atuais(): array
    {
        return [
            'versao'        => config('reservas.termos_versao'),
            'rascunho'      => true,
            'termos_de_uso' => self::termosDeUso(),
            'privacidade'   => self::privacidade(),
        ];
    }

    private static function termosDeUso(): array
    {
        $antecedencia = config('reservas.antecedencia_maxima_dias_uteis');
        $horas = config('reservas.confirmacao_horas');
        $minutosHoje = config('reservas.confirmacao_minutos_mesmo_dia');
        $diasReconfirmacao = config('reservas.reconfirmacao_dias_uteis_antes');

        return [
            [
                'titulo'     => 'Para que serve o sistema',
                'paragrafos' => [
                    'O sistema organiza o uso dos espaços (quadra, laboratórios e salas) e dos equipamentos da instituição '.self::A_CONFIRMAR.'. A reserva é pessoal: ela é feita com o seu RA e confirmada pelo seu email.',
                ],
            ],
            [
                'titulo'     => 'Como funciona a reserva',
                'paragrafos' => [
                    "As reservas podem ser feitas com até {$antecedencia} dias úteis de antecedência, para que todos tenham a mesma chance de reservar.",
                    "Toda reserva precisa ser confirmada pelo link enviado ao seu email: em até {$horas} horas, ou em até {$minutosHoje} minutos quando ela é para o mesmo dia (sempre antes do início). Sem a confirmação, a reserva é cancelada e o horário fica livre.",
                    "Cerca de {$diasReconfirmacao} dias úteis antes da data, você pode receber um novo pedido de confirmação. Sem ele, a reserva é cancelada no começo da véspera.",
                    'Se não for usar o horário, cancele pelo link do email. Isso libera o espaço para outras pessoas.',
                ],
            ],
            [
                'titulo'     => 'Regras de uso',
                'paragrafos' => [
                    'Use o espaço e os equipamentos só no horário reservado e para atividades acadêmicas, esportivas ou de convivência permitidas pela instituição.',
                    'Os equipamentos devem ser devolvidos no fim do horário, nas mesmas condições. Avarias e itens não devolvidos ficam registrados na reserva e você é avisado por email.',
                    'Algumas regras podem limitar o número de reservas por curso em certos espaços (por exemplo, a quadra).',
                    'A administração pode cancelar uma reserva por necessidade da instituição (evento, manutenção, feriado), sempre com justificativa e aviso por email.',
                ],
            ],
            [
                'titulo'     => 'Responsabilidades',
                'paragrafos' => [
                    'Não use o RA de outra pessoa. Se receber um email de uma reserva que você não fez, não confirme: cancele pelo link e avise a administração.',
                    'Dúvidas e problemas: '.self::A_CONFIRMAR.'.',
                ],
            ],
        ];
    }

    private static function privacidade(): array
    {
        $tokens = config('reservas.tokens_retencao_dias');
        $jobs = intdiv(config('reservas.jobs_falhos_retencao_horas'), 24);
        $logs = (int) config('logging.channels.daily.max_files', 14);

        return [
            [
                'titulo'     => 'Quem trata os seus dados',
                'paragrafos' => [
                    'A instituição '.self::A_CONFIRMAR.' é a controladora dos dados deste sistema. Contato do encarregado de dados (LGPD): '.self::A_CONFIRMAR.'.',
                ],
            ],
            [
                'titulo'     => 'Quais dados e para quê',
                'paragrafos' => [
                    'Nome completo, RA, email e curso: para identificar quem reservou, aplicar as regras por curso e enviar os avisos (confirmação, cancelamento, revogação, devolução de equipamentos).',
                    'Dados das reservas (espaço, data, horário, equipamentos, confirmação e aceite destes termos): para organizar o uso dos espaços e registrar o que foi combinado.',
                    'No calendário público aparecem só o seu nome e o seu curso em horários reservados. RA e email nunca aparecem em páginas públicas.',
                ],
            ],
            [
                'titulo'     => 'Por quanto tempo',
                'paragrafos' => [
                    'Os dados de cadastro ficam enquanto você usar o sistema ou até pedir a anonimização.',
                    "Os links enviados por email são apagados {$tokens} dias depois de usados ou vencidos; envios de email que falharam, depois de {$jobs} dias; os registros técnicos do servidor, depois de {$logs} dias.",
                    'O histórico de alterações (quem mudou o quê) é guardado para auditoria, com prazo '.self::A_CONFIRMAR.'.',
                ],
            ],
            [
                'titulo'     => 'Os seus direitos',
                'paragrafos' => [
                    'Você pode consultar as suas reservas em "Minhas reservas" (com RA e email), pedir a correção dos seus dados e pedir a anonimização: nome, RA e email são trocados por dados anônimos, inclusive no histórico, e as reservas futuras são canceladas.',
                    'Os pedidos são feitos pelo contato: '.self::A_CONFIRMAR.'.',
                ],
            ],
            [
                'titulo'     => 'Segurança',
                'paragrafos' => [
                    'O acesso é feito por HTTPS, os links enviados por email têm validade e só podem ser usados uma vez, e os dados pessoais não vão em endereços de páginas nem em registros técnicos.',
                ],
            ],
        ];
    }
}
