<?php

return [

    'antecedencia_maxima_dias_uteis' => (int) env('RESERVA_ANTECEDENCIA_MAXIMA_DIAS_UTEIS', 10),

    'bloco_minutos' => (int) env('RESERVA_BLOCO_MINUTOS', 15),

    'minutos_antes_do_fim' => (int) env('RESERVA_MINUTOS_ANTES_DO_FIM', 20),

    'fim_dos_semestres' => array_map('trim', explode(',', (string) env('RESERVA_FIM_DOS_SEMESTRES', '06-30,12-31'))),

    'calendario_minimo_semanas' => (int) env('RESERVA_CALENDARIO_MINIMO_SEMANAS', 4),

    'confirmacao_dias_uteis_antes' => (int) env('RESERVA_CONFIRMACAO_DIAS_UTEIS_ANTES', 2),

    'horario_pedido_confirmacao' => env('RESERVA_HORARIO_PEDIDO_CONFIRMACAO', '08:00'),

    'horario_revogacao_nao_confirmadas' => env('RESERVA_HORARIO_REVOGACAO_NAO_CONFIRMADAS', '00:05'),

    'feriados_facultativos' => (bool) env('RESERVA_FERIADOS_FACULTATIVOS', false),

    'tokens_retencao_dias' => (int) env('RESERVA_TOKENS_RETENCAO_DIAS', 30),

    'jobs_falhos_retencao_horas' => (int) env('RESERVA_JOBS_FALHOS_RETENCAO_HORAS', 168),

    'alertas_email' => env('ALERTAS_EMAIL'),

    'alertas_intervalo_minutos' => (int) env('ALERTAS_INTERVALO_MINUTOS', 60),

    'admin_inicial' => [
        'nome'  => env('ADMIN_INICIAL_NOME'),
        'email' => env('ADMIN_INICIAL_EMAIL'),
        'senha' => env('ADMIN_INICIAL_SENHA'),
    ],

];
