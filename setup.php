<?php
/**
 * =====================================================
 * ShortLink Pro — One-Time Database Setup
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Creates all database tables. DELETE this file after use.
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Starting database setup...\n\n";

$schemaPath = __DIR__ . '/migrations/schema.sql';

if (!file_exists($schemaPath)) {
    exit("❌ Schema file not found at: $schemaPath\n");
}

$sql = file_get_contents($schemaPath);

// Split on semicolons at the end of a line
$statements = array_filter(
    array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)),
    fn($s) => $s !== '' && !str_starts_with($s, '--') && !str_starts_with($s, '/*')
);

$ok = 0;
$fail = 0;

foreach ($statements as $i => $stmt) {
    if ($stmt === '') continue;

    // Skip the entire "SET FOREIGN_KEY_CHECKS" block if split badly
    $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 70);

    try {
        $pdo->exec($stmt);
        echo "✅ [{$i}] OK: {$preview}...\n";
        $ok++;
    } catch (Throwable $e) {
        echo "❌ [{$i}] FAIL: {$preview}...\n";
        echo "    → " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\n──────────────────────────────────────\n";
echo "Total: " . ($ok + $fail) . " statements\n";
echo "✅ Succeeded: $ok\n";
echo "❌ Failed: $fail\n";
echo "──────────────────────────────────────\n\n";

// Show tables
echo "Tables now in database:\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "  • $t\n";
}

echo "\n⚠️  DELETE this file now: setup.php\n";