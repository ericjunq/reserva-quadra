<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [

    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'docs/api-v1.openapi.json',

    'cache' => [
        'key'   => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version'     => '1.0.0',
        'description' => 'API do sistema de reservas para sistemas parceiros autorizados. Guia completo em docs/API-PARCEIROS.md.',
    ],

    'ui' => [
        'title' => 'API de reservas para parceiros (v1)',
    ],

    'dev_tools' => [
        'enabled' => false,
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view'                   => 'scramble::docs',
            'theme'                  => 'light',
            'hideTryIt'              => true,
            'hideSchemas'            => false,
            'logo'                   => '',
            'tryItCredentialsPolicy' => 'omit',
            'layout'                 => 'responsive',
            'router'                 => 'hash',
        ],
    ],

    'servers' => [
        'Servidor da instituição' => 'https://{dominio}/api/v1',
    ],

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'enum_cases_auto_description_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    'security_strategy' => null,
];
