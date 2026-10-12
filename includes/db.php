<?php
/**
 * =====================================================
 * ShortLink Pro — Database Connection build by smart holla tech
 * =====================================================
 * Creates a PDO connection using constants from config.php.
 * Exposes a global $pdo object for the rest of the app.
 *
 * Requires: includes/config.php must be loaded first.
 * =====================================================
 */

declare(strict_types=1);

// -----------------------------------------------------
// Ensure config.php was loaded first
// -----------------------------------------------------
if (!defined('DB_HOST')) {
    http_response_code(500);
    exit('db.php requires config.php to be loaded first.');
}

// -----------------------------------------------------
// Build the DSN (Data Source Name)
// -----------------------------------------------------
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

// -----------------------------------------------------
// PDO options
// -----------------------------------------------------
$options = [
    // Throw exceptions on errors (instead of silent failures)
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

    // Return rows as associative arrays by default
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Use real prepared statements (not emulated)
    PDO::ATTR_EMULATE_PREPARES => false,

    // Never convert NULL/number to string silently
    PDO::ATTR_STRINGIFY_FETCHES => false,

    // Keep the connection alive for the request duration
    PDO::ATTR_PERSISTENT => false,
];

// -----------------------------------------------------
// Create the connection
// -----------------------------------------------------
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log the real error for us
    error_log('[DB ERROR] ' . $e->getMessage());

    // Show a friendly message (dev shows details, prod hides them)
    http_response_code(500);

    if (APP_DEBUG) {
        exit('Database connection failed: ' . $e->getMessage());
    } else {
        exit('Service temporarily unavailable. Please try again later.');
    }
}