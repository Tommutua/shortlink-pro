<?php
/**
 * =====================================================
 * ShortLink Pro — Application Configuration
 * =====================================================
 * Loads .env, defines constants, and sets up app-wide defaults.
 *
 * This file is required FIRST by every page.
 * It does not connect to the database — that's db.php's job.
 * =====================================================
 */

declare(strict_types=1);

// -----------------------------------------------------
// 1. LOAD .env FILE (local) OR ENV VARS (production)
// -----------------------------------------------------
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (file_exists($envPath)) {
    // LOCAL: parse the .env file
    $env = parse_ini_file($envPath, false, INI_SCANNER_TYPED);

    if ($env === false) {
        http_response_code(500);
        exit('.env file has a syntax error.');
    }

    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
} else {
    // PRODUCTION: use environment variables
    foreach ($_SERVER as $key => $value) {
        if (is_string($value) && preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            $_ENV[$key] = $value;
        }
    }
}

// -----------------------------------------------------
// 2. HELPER — Read an env value with a default
// -----------------------------------------------------
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

// -----------------------------------------------------
// 3. APPLICATION CONSTANTS
// -----------------------------------------------------
define('APP_NAME',  (string) env('APP_NAME',  'ShortLink Pro'));
define('APP_URL',   rtrim((string) env('APP_URL', 'http://localhost/shortlink-pro'), '/'));
define('APP_ENV',   (string) env('APP_ENV',   'local'));
define('APP_DEBUG', (bool)   env('APP_DEBUG', false));

// -----------------------------------------------------
// 4. DATABASE CONSTANTS
// -----------------------------------------------------
define('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int)    env('DB_PORT', 3306));
define('DB_NAME', (string) env('DB_NAME', 'shortlink'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));

// -----------------------------------------------------
// 5. SECURITY CONSTANTS
// -----------------------------------------------------
define('APP_SECRET', (string) env('APP_SECRET', 'insecure-dev-secret-change-me'));

// -----------------------------------------------------
// 6. RATE LIMITING
// -----------------------------------------------------
define('RATE_LIMIT_LINKS_PER_HOUR',    (int) env('RATE_LIMIT_LINKS_PER_HOUR', 20));
define('RATE_LIMIT_PASSWORD_ATTEMPTS', (int) env('RATE_LIMIT_PASSWORD_ATTEMPTS', 5));

// -----------------------------------------------------
// 7. GEOLOCATION
// -----------------------------------------------------
define('GEO_API_URL', (string) env('GEO_API_URL', 'http://ip-api.com/json'));

// -----------------------------------------------------
// 8. TIMEZONE
// -----------------------------------------------------
date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

// -----------------------------------------------------
// 9. ERROR REPORTING
// -----------------------------------------------------
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// -----------------------------------------------------
// 10. APP-WIDE SETTINGS
// -----------------------------------------------------

// Reserved words that cannot be used as custom aliases
// (they clash with real routes/pages)
define('RESERVED_WORDS', [
    'api', 'admin', 'login', 'logout', 'register', 'signup',
    'dashboard', 'stats', 'qr', 'u', 'bio', 'bulk', 'go',
    'shorten', 'delete', 'password', 'index', 'includes',
    'assets', 'migrations', 'scripts', 'uploads',
]);

// Short code length for generated (non-custom) links
define('SHORT_CODE_MIN_LENGTH', 4);
define('SHORT_CODE_MAX_LENGTH', 12);

// Custom alias rules
define('ALIAS_MIN_LENGTH', 3);
define('ALIAS_MAX_LENGTH', 32);
define('ALIAS_PATTERN', '/^[a-zA-Z0-9_-]+$/');

// Base62 obfuscation constants (explained later in Base62 function)
define('BASE62_OFFSET',     45678901);
define('BASE62_MULTIPLIER', 100003);

// -----------------------------------------------------
// 11. START SESSION (once, safely)
// -----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,        // set true in production (HTTPS)
        'httponly' => true,         // JS cannot read the session cookie
        'samesite' => 'Lax',        // basic CSRF mitigation
    ]);
    session_start();
}