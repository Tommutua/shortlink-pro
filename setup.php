<?php
/**
 * ONE-TIME DATABASE SETUP
 * DELETE THIS FILE IMMEDIATELY AFTER RUNNING.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ShortLink Pro — Database Setup ===\n\n";

// Config check
echo "Config:\n";
echo "  APP_URL:  " . (defined('APP_URL') ? APP_URL : 'NOT DEFINED') . "\n";
echo "  DB_HOST:  " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
echo "  DB_PORT:  " . (defined('DB_PORT') ? DB_PORT : 'NOT DEFINED') . "\n";
echo "  DB_NAME:  " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
echo "  DB_USER:  " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
echo "  DB_PASS:  " . (DB_PASS !== '' ? '(set, ' . strlen(DB_PASS) . ' chars)' : '(empty)') . "\n\n";

// Connect
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "✅ Connected to database.\n\n";
} catch (Throwable $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Load schema
$schemaPath = __DIR__ . '/migrations/schema.sql';
if (!file_exists($schemaPath)) {
    exit("❌ Schema not found: $schemaPath\n");
}

$sql = file_get_contents($schemaPath);
$statements = array_filter(
    array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)),
    fn($s) => $s !== '' && !str_starts_with($s, '--')
);

$ok = 0; $fail = 0;
foreach ($statements as $i => $stmt) {
    $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 60);
    try {
        $pdo->exec($stmt);
        echo "✅ [$i] $preview...\n";
        $ok++;
    } catch (Throwable $e) {
        echo "❌ [$i] $preview...\n   → " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\nSucceeded: $ok · Failed: $fail\n\n";
echo "Tables:\n";
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    echo "  • $t\n";
}
echo "\n⚠️  DELETE setup.php NOW.\n";