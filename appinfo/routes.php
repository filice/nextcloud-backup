<?php

return [
    'routes' => [
        [
            'name' => 'settings#index', // Nome della rotta, usa Controller#metodo
            'url' => '/settings/admin', // URL relativo all'app
            'verb' => 'GET' // Metodo HTTP (GET)
        ],
        [
            'name' => 'settings#save',
            'url' => '/settings/admin/save',
            'verb' => 'POST'
        ],
        [
            'name' => 'settings#saveInterval',
            'url' => '/settings/interval',
            'verb' => 'POST'
        ],
        [
            'name' => 'backup#doBackupNow',
            'url' => '/settings/backup/now',
            'verb' => 'POST'
        ]
    ],
    'resources' => [
        // Definizione delle risorse (opzionale, per endpoint REST)
    ],
    'routes-prefix' => '/apps/nextcloud_backup' // Prefisso globale per tutte le rotte
];
