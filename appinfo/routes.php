<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'config#listRoots', 'url' => '/api/v1/roots', 'verb' => 'GET'],
        ['name' => 'config#addUserRoots', 'url' => '/api/v1/roots', 'verb' => 'POST'],
        ['name' => 'config#removeUserRoot', 'url' => '/api/v1/roots/{rootId}', 'verb' => 'DELETE'],
        ['name' => 'config#browseUserFolders', 'url' => '/api/v1/picker', 'verb' => 'GET'],
        ['name' => 'catalog#listFolder', 'url' => '/api/v1/catalog', 'verb' => 'GET'],
        ['name' => 'catalog#search', 'url' => '/api/v1/search', 'verb' => 'GET'],
        ['name' => 'catalog#serveSubtitle', 'url' => '/api/v1/subtitles/{rootId}', 'verb' => 'GET'],
        ['name' => 'session#start', 'url' => '/api/v1/sessions', 'verb' => 'POST'],
        ['name' => 'session#listActive', 'url' => '/api/v1/sessions', 'verb' => 'GET'],
        ['name' => 'session#heartbeat', 'url' => '/api/v1/sessions/{token}/heartbeat', 'verb' => 'PUT'],
        ['name' => 'session#close', 'url' => '/api/v1/sessions/{token}', 'verb' => 'DELETE'],
        ['name' => 'session#closeBeacon', 'url' => '/api/v1/sessions/{token}/close', 'verb' => 'POST'],
        ['name' => 'preference#get', 'url' => '/api/v1/preferences', 'verb' => 'GET'],
        ['name' => 'preference#update', 'url' => '/api/v1/preferences', 'verb' => 'PUT'],
        ['name' => 'stream#stream', 'url' => '/api/v1/stream/{rootId}', 'verb' => 'GET'],
        ['name' => 'stream#probe', 'url' => '/api/v1/probe/{rootId}', 'verb' => 'GET'],
        ['name' => 'stream#thumbnail', 'url' => '/api/v1/thumb/{rootId}', 'verb' => 'GET'],
        ['name' => 'settings#getSettings', 'url' => '/api/v1/admin/settings', 'verb' => 'GET'],
        ['name' => 'settings#saveSettings', 'url' => '/api/v1/admin/settings', 'verb' => 'PUT'],
    ],
];
