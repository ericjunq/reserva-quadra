<?php

use App\Models\User;

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', User::class),
        ],

    ],

    'passwords' => [
        'admins' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => (int) env('AUTH_REDEFINICAO_EXPIRA_MINUTOS', 60),
            'throttle' => 60,
        ],
        'convites' => [
            'provider' => 'users',
            'table'    => 'convites_admin_tokens',
            'expire'   => (int) env('AUTH_CONVITE_EXPIRA_MINUTOS', 2880),
            'throttle' => 0,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
