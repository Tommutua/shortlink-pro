<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== ShortLink Pro — Env Debug ===\n\n";

echo "1. Looking for .env file:\n";
$envPath = __DIR__ . '/.env';
echo "   Path: $envPath\n";
echo "   Exists: " . (file_exists($envPath) ? 'YES' : 'NO') . "\n\n";

echo "2. \$SERVER vars that look like env vars:\n";
$found = [];
foreach ($_SERVER as $key => $value) {
    if (is_string($value) && preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
        $display = strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value;
        echo "   $key = $display\n";
        $found[] = $key;
    }
}
echo "   → Total: " . count($found) . " matching variables\n\n";

echo "3. \$ENV keys:\n";
foreach ($_ENV as $key => $value) {
    $display = is_string($value) && strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value;
    echo "   $key = $display\n";
}
echo "   → Total: " . count($_ENV) . " keys\n\n";

echo "4. Test specific variables:\n";
echo "   APP_NAME:  " . ($_SERVER['APP_NAME'] ?? 'NOT SET') . "\n";
echo "   APP_URL:   " . ($_SERVER['APP_URL'] ?? 'NOT SET') . "\n";
echo "   DB_HOST:   " . ($_SERVER['DB_HOST'] ?? 'NOT SET') . "\n";
echo "   DB_PORT:   " . ($_SERVER['DB_PORT'] ?? 'NOT SET') . "\n";
echo "   DB_NAME:   " . ($_SERVER['DB_NAME'] ?? 'NOT SET') . "\n";
echo "   DB_USER:   " . ($_SERVER['DB_USER'] ?? 'NOT SET') . "\n";
echo "   DB_PASS:   " . (isset($_SERVER['DB_PASS']) ? '(set, length ' . strlen($_SERVER['DB_PASS']) . ')' : 'NOT SET') . "\n\n";

echo "5. PHP config:\n";
echo "   PHP version: " . phpversion() . "\n";
echo "   display_errors: " . ini_get('display_errors') . "\n";
echo "   error_reporting: " . error_reporting() . "\n";