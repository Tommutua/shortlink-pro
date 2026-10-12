<?php
/**
 * Router for PHP built-in server.
 * Mimics the .htaccess rewrite rules.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri = ltrim($uri, '/');

// Serve real files directly
if ($uri !== '' && file_exists(__DIR__ . '/' . $uri) && !is_dir(__DIR__ . '/' . $uri)) {
    return false; // let PHP's built-in server handle it
}

// Root
if ($uri === '' || $uri === 'index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// Special routes
if (preg_match('#^stats/([a-zA-Z0-9_-]+)/?$#', $uri, $m)) {
    $_GET['c'] = $m[1];
    require __DIR__ . '/stats.php';
    return true;
}

if (preg_match('#^u/([a-zA-Z0-9_-]+)/?$#', $uri, $m)) {
    $_GET['u'] = $m[1];
    require __DIR__ . '/bio.php';
    return true;
}

// Catch-all: treat as short code
if (preg_match('#^([a-zA-Z0-9_-]+)/?$#', $uri, $m)) {
    $_GET['c'] = $m[1];
    require __DIR__ . '/go.php';
    return true;
}

// Fallback
http_response_code(404);
require __DIR__ . '/go.php';
return true;