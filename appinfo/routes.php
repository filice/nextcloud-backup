<?php

return [
    'routes' => [
        ['name' => 'settings#index', 'url' => '/settings/admin', 'verb' => 'GET'],
        ['name' => 'settings#save', 'url' => '/settings/admin/save', 'verb' => 'POST'],
        ['name' => 'settings#saveInterval', 'url' => '/settings/interval', 'verb' => 'POST'],
        ['name' => 'backup#doBackupNow', 'url' => '/backup/now', 'verb' => 'POST']
    ]
];
