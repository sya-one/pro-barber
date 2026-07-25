<?php
// Admin routing - handles /admin and /admin/* requests
// Routes internally without exposing /barbershop-system in URL

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$query = '';

if (strpos($path, '?') !== false) {
    $parts = explode('?', $path, 2);
    $path = $parts[0];
    $query = isset($parts[1]) ? '?' . $parts[1] : '';
}

// Normalize path - remove trailing slash
$path = rtrim($path, '/');

// Map /admin to login
if ($path === '/admin' || $path === '/admin/login.php') {
    include __DIR__ . '/barbershop-system/login.php';
    exit;
}

// Map /admin/dashboard.php to barbershop-system/admin/dashboard.php
if (preg_match('#^/admin/([^/]+)\.php$#', $path, $m)) {
    $targetFile = __DIR__ . '/barbershop-system/admin/' . $m[1] . '.php';
    if (file_exists($targetFile)) {
        include $targetFile;
        exit;
    }
}

// Map /admin/something to barbershop-system/admin/something.php for redirects from login
if (preg_match('#^/admin/([^/]+)$#', $path, $m)) {
    $targetFile = __DIR__ . '/barbershop-system/admin/' . $m[1] . '.php';
    if (file_exists($targetFile)) {
        include $targetFile;
        exit;
    }
}

// Default fallback
http_response_code(404);
include __DIR__ . '/404.php';
exit;