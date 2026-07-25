<?php
// Router for /admin - redirects to barbershop-system login
// TEMPORARY TESTING: Redirects to /barbershop-system/login.php

if (strpos($_SERVER['REQUEST_URI'], '/admin') === 0) {
    // Handle /admin or /admin/
    $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($uri === 'admin' || $uri === '') {
        header('Location: /barbershop-system/login.php');
        exit;
    }
    // Handle /admin/something
    $parts = explode('/', $uri);
    array_shift($parts); // Remove 'admin'
    $target = '/barbershop-system/' . implode('/', $parts);
    if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
        $target .= '?' . $_SERVER['QUERY_STRING'];
    }
    header('Location: ' . $target);
    exit;
} else {
    header('Location: /barbershop-system/login.php');
    exit;
}