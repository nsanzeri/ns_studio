<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$siteBase = str_contains($currentPath, '/ns_studio/') ? '/ns_studio' : '';

$manifest = [
    'name' => 'Ready Set Shows',
    'short_name' => 'RSS',
    'description' => 'Calendar, SetMaxx, finance, publishing, and booking tools for working musicians.',
    'start_url' => $siteBase . '/studio/tools/index.php',
    'scope' => $siteBase . '/',
    'display' => 'standalone',
    'background_color' => '#090814',
    'theme_color' => '#090814',
    'icons' => [
        [
            'src' => $siteBase . '/assets/favicons/rss-favicon-192.png?v=20260826',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => $siteBase . '/assets/favicons/rss-favicon-512.png?v=20260826',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
