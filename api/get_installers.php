<?php
// api/get_installers.php
// API para obtener información de instaladores disponibles

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Configuración
$downloadBaseUrl = 'https://tevsko.com.ar/downloads/';
$currentVersion = '1.0.0';

// Información de instaladores
$installers = [
    'offline' => [
        'name' => 'Instalador Offline',
        'description' => 'Instalación completa sin necesidad de internet',
        'version' => $currentVersion,
        'size_mb' => 125,
        'filename' => "SpaceParkInstaller-{$currentVersion}-Offline.exe",
        'url' => $downloadBaseUrl . "SpaceParkInstaller-{$currentVersion}-Offline.exe",
        'recommended' => true,
        'requires_internet' => false,
        'features' => [
            'No requiere internet',
            'Instalación rápida',
            'Ideal para múltiples PCs'
        ]
    ],
    'online' => [
        'name' => 'Instalador Online',
        'description' => 'Descarga rápida, archivos se descargan durante instalación',
        'version' => $currentVersion,
        'size_mb' => 5,
        'filename' => "SpaceParkInstaller-{$currentVersion}-Online.exe",
        'url' => $downloadBaseUrl . "SpaceParkInstaller-{$currentVersion}-Online.exe",
        'recommended' => false,
        'requires_internet' => true,
        'features' => [
            'Descarga rápida (~5 MB)',
            'Siempre actualizado',
            'Requiere internet estable'
        ]
    ]
];

// Información del sistema
$response = [
    'success' => true,
    'version' => $currentVersion,
    'release_date' => '2026-02-05',
    'installers' => $installers,
    'system_requirements' => [
        'os' => 'Windows 10 o superior (64 bits)',
        'ram' => '4 GB mínimo',
        'disk_space' => '500 MB',
        'internet' => 'Requerido para sincronización'
    ],
    'support' => [
        'phone' => '1135508224',
        'email' => 'tevsko@gmail.com'
    ]
];

// Devolver JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
