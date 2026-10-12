<?php
/**
 * =====================================================
 * ShortLink Pro — Router for PHP Built-in Server
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Mimics .htaccess rewrite rules for Railway/FrankenPHP.
 * =====================================================
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = '/' . ltrim(urldecode($uri), '/');
$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/';

$projectRoot = __DIR__;

// Let real files (CSS, JS, images, PHP) serve as-is
$requestedFile = $projectRoot . $uri;
if ($uri !== '/' && file_exists($requestedFile) && !is_dir($requestedFile)) {
    if (substr($requestedFile, -4) === '.php') {
        require $requestedFile;
        return true;
    }
    return false;
}

// Root → index.php
if ($uri === '/' || $uri === '/index.php') {
    require $projectRoot . '/index.php';
    return true;
}

// /stats/{code} → stats.php
if (preg_match('#^/stats/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['c'] = $m[1];
    require $projectRoot . '/stats.php';
    return true;
}

// /u/{username} → bio.php
if (preg_match('#^/u/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['u'] = $m[1];
    require $projectRoot . '/bio.php';
    return true;
}

// /{code} → go.php
if (preg_match('#^/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['c'] = $m[1];
    require $projectRoot . '/go.php';
    return true;
}

// Fallback → 404
http_response_code(404);
require $projectRoot . '/index.php';
return true;