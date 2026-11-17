<?php

return [

    'antecedencia_maxima_dias_uteis' => (int) env('RESERVA_ANTECEDENCIA_MAXIMA_DIAS_UTEIS', 10),

    'bloco_minutos' => (int) env('RESERVA_BLOCO_MINUTOS', 15),

    'minutos_antes_do_fim' => (int) env('RESERVA_MINUTOS_ANTES_DO_FIM', 20),

    'fim_dos_semestres' => array_map('trim', explode(',', (string) env('RESERVA_FIM_DOS_SEMESTRES', '06-30,12-31'))),

    'calendario_minimo_semanas' => (int) env('RESERVA_CALENDARIO_MINIMO_SEMANAS', 4),

    'confirmacao_horas' => (int) env('RESERVA_CONFIRMACAO_HORAS', 24),

    'confirmacao_minutos_mesmo_dia' => (int) env('RESERVA_CONFIRMACAO_MINUTOS_MESMO_DIA', 60),

    'reconfirmacao_dias_uteis_antes' => (int) env('RESERVA_RECONFIRMACAO_DIAS_UTEIS_ANTES', 3),

    'reconfirmacao_dispensada_dias_uteis' => (int) env('RESERVA_RECONFIRMACAO_DISPENSADA_DIAS_UTEIS', 2),

    'horario_pedido_reconfirmacao' => env('RESERVA_HORARIO_PEDIDO_RECONFIRMACAO', '08:00'),

    'solicitacao_prazo_horas_antes' => (int) env('RESERVA_SOLICITACAO_PRAZO_HORAS_ANTES', 24),

    'solicitacao_prazo_curto_horas_antes' => (int) env('RESERVA_SOLICITACAO_PRAZO_CURTO_HORAS_ANTES', 3),

    'solicitacao_tempo_minimo_resposta_horas' => (int) env('RESERVA_SOLICITACAO_TEMPO_MINIMO_RESPOSTA_HORAS', 2),

    'sugestao_compartilhamento' => [
        'minimo_pedidos' => (int) env('SUGESTAO_COMPARTILHAMENTO_MINIMO', 3),
        'dias'           => (int) env('SUGESTAO_COMPARTILHAMENTO_DIAS', 60),
    ],

    'retirada_minutos_antes' => (int) env('RETIRADA_MINUTOS_ANTES', 30),

    'manter_reserva_horas' => (int) env('RESERVA_MANTER_HORAS', 12),

    'manter_reserva_minimo_horas_antes' => (int) env('RESERVA_MANTER_MINIMO_HORAS_ANTES', 1),

    'termos_versao' => env('RESERVA_TERMOS_VERSAO', '2026-11'),

    'feriados_facultativos' => (bool) env('RESERVA_FERIADOS_FACULTATIVOS', false),

    'tokens_retencao_dias' => (int) env('RESERVA_TOKENS_RETENCAO_DIAS', 30),

    'jobs_falhos_retencao_horas' => (int) env('RESERVA_JOBS_FALHOS_RETENCAO_HORAS', 168),

    'limites' => [
        'ip_acoes'     => (int) env('LIMITE_IP_ACOES_POR_MINUTO', 60),
        'ip_consultas' => (int) env('LIMITE_IP_CONSULTAS_POR_MINUTO', 120),
        'por_pessoa'   => (int) env('LIMITE_POR_PESSOA_POR_MINUTO', 5),
        'consulta_ra'  => (int) env('LIMITE_CONSULTA_POR_RA_POR_MINUTO', 20),
    ],

    'fila_pelo_agendador' => (bool) env('FILA_PELO_AGENDADOR', env('APP_ENV', 'production') === 'local'),

    'sessao_admin_minutos' => (int) env('SESSAO_ADMIN_MINUTOS', 60 * 8),

    'parceiros' => [
        'termos_versao'     => env('PARCEIROS_TERMOS_VERSAO', '2026-11'),
        'validade_meses'    => (int) env('PARCEIROS_VALIDADE_MESES', 12),
        'aviso_dias_antes'  => (int) env('PARCEIROS_AVISO_DIAS_ANTES', 30),
        'link_aceite_dias'  => (int) env('PARCEIROS_LINK_ACEITE_DIAS', 7),
        'limite_por_minuto' => (int) env('PARCEIROS_LIMITE_POR_MINUTO', 60),
        'horario_aviso'     => env('PARCEIROS_HORARIO_AVISO', '08:30'),
    ],

    'login_com_codigo' => (bool) env('LOGIN_COM_CODIGO', true),

    'alertas_email' => env('ALERTAS_EMAIL'),

    'alertas_intervalo_minutos' => (int) env('ALERTAS_INTERVALO_MINUTOS', 60),

    'admin_inicial' => [
        'nome'  => env('ADMIN_INICIAL_NOME'),
        'email' => env('ADMIN_INICIAL_EMAIL'),
        'senha' => env('ADMIN_INICIAL_SENHA'),
    ],

];
