<?php

return [

    'antecedencia_maxima_dias' => (int) env('RESERVA_ANTECEDENCIA_MAXIMA_DIAS', 7),

    'minutos_antes_do_fim' => (int) env('RESERVA_MINUTOS_ANTES_DO_FIM', 20),

    'confirmacao_dias_antes' => (int) env('RESERVA_CONFIRMACAO_DIAS_ANTES', 2),

];
