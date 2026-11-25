<?php

return [
    // Token compartido con los scripts de monitoreo. Vacío = la API rechaza todo.
    'token' => env('MONITOREO_TOKEN'),
    'api_url' => env('MONITOREO_API_URL', rtrim((string) env('APP_URL'), '/').'/api/monitoring/lecturas'),
    'snmp_community_get' => env('SNMP_COMMUNITY_GET'),
    'powershell_binary' => env('MONITOREO_POWERSHELL_BINARY', 'powershell.exe'),
    'snmp_config' => env(
        'MONITOREO_SNMP_CONFIG',
        base_path('scripts'.DIRECTORY_SEPARATOR.'printers.snmp.local.json')
    ),
    'umbrales_nivel' => [
        'critico_hasta' => 10,
        'bajo_hasta' => 25,
    ],
];