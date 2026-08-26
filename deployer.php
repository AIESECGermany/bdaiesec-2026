<?php
header('Content-Type: text/plain; charset=utf-8');

// Simple PHP deployer for this static site.
// It keeps the PHP backend logic and serves pages without .html in the URL via clean routing.

$base = __DIR__;
$current = $_SERVER['REQUEST_URI'] ?? '/';
$current = parse_url($current, PHP_URL_PATH) ?: '/';
$current = rawurldecode($current);

if ($current === '' || $current === '/') {
    $file = $base . '/index.html';
    if (is_file($file)) {
        readfile($file);
        exit;
    }
}

$clean = rtrim($current, '/');
$map = [
    '/anmeldung' => '/anmeldung.html',
    '/global-talent' => '/global-talent/index.html',
    '/hochschulmarketing' => '/hochschulmarketing/index.html',
    '/talentbindung' => '/talentbindung/index.html',
    '/coming-soon' => '/coming-soon.html',
];

if (isset($map[$clean])) {
    $file = $base . $map[$clean];
    if (is_file($file)) {
        readfile($file);
        exit;
    }
}

$paths = [
    $base . $current,
    $base . $current . '.html',
    $base . $current . '/index.html'
];

foreach ($paths as $path) {
    if (is_file($path)) {
        readfile($path);
        exit;
    }
}

http_response_code(404);
echo "Not found\n";
