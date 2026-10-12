<?php
/**
 * =====================================================
 * ShortLink Pro — Shorten Handler
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Handles POST from index.php:
 *   - Validates URL
 *   - Validates custom alias (if provided)
 *   - Checks rate limit
 *   - Hashes password (if provided)
 *   - Computes expiry datetime
 *   - Creates the link
 *   - Redirects back with success or error
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// -----------------------------------------------------
// 1. ONLY ACCEPT POST REQUESTS
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

// -----------------------------------------------------
// 2. READ + SANITIZE FORM DATA
// -----------------------------------------------------
$longUrl      = trim((string) ($_POST['long_url']     ?? ''));
$customAlias  = trim((string) ($_POST['custom_alias'] ?? ''));
$expiryOption = trim((string) ($_POST['expiry']       ?? 'never'));
$password     = (string) ($_POST['password'] ?? '');
$abEnabled    = isset($_POST['ab_enabled']) && $_POST['ab_enabled'] === '1';
$variantB     = trim((string) ($_POST['variant_b'] ?? ''));
// -----------------------------------------------------
// 3. VALIDATE URL
// -----------------------------------------------------
if (!validate_url($longUrl)) {
    redirect('index.php?error=invalid_url');
}

// -----------------------------------------------------
// 4. RATE LIMIT — max N links per IP per hour
// -----------------------------------------------------
if (!rate_limit_links(client_ip())) {
    redirect('index.php?error=rate_limit');
}

// -----------------------------------------------------
// 5. VALIDATE CUSTOM ALIAS (if provided)
// -----------------------------------------------------
if ($customAlias !== '') {
    if (!is_valid_alias($customAlias)) {
        // Distinguish between "reserved" and "invalid pattern"
        if (is_reserved_word($customAlias)) {
            redirect('index.php?error=alias_reserved');
        }
        redirect('index.php?error=alias_invalid');
    }

    if (alias_exists($customAlias)) {
        redirect('index.php?error=alias_taken');
    }
}

// -----------------------------------------------------
// 6. VALIDATE PASSWORD (if provided)
// -----------------------------------------------------
if ($password !== '' && strlen($password) < 4) {
    redirect('index.php?error=invalid_password');
}

// -----------------------------------------------------
// 7. COMPUTE EXPIRY DATETIME
// -----------------------------------------------------
$expiresAt = match ($expiryOption) {
    '1h'  => date('Y-m-d H:i:s', strtotime('+1 hour')),
    '24h' => date('Y-m-d H:i:s', strtotime('+24 hours')),
    '7d'  => date('Y-m-d H:i:s', strtotime('+7 days')),
    '30d' => date('Y-m-d H:i:s', strtotime('+30 days')),
    default => null, // "never"
};

// -----------------------------------------------------
// 8. HASH PASSWORD (if provided)
// -----------------------------------------------------
$passwordHash = $password !== ''
    ? password_hash($password, PASSWORD_ARGON2ID)
    : null;

// -----------------------------------------------------
// 9. CREATE THE LINK
// -----------------------------------------------------
try {
    $id = create_link([
        'long_url'      => $longUrl,
        'custom_alias'  => $customAlias !== '' ? $customAlias : null,
        'password_hash' => $passwordHash,
        'expires_at'    => $expiresAt,
    ]);
} catch (Throwable $e) {
    error_log('[SHORTEN ERROR] ' . $e->getMessage());
    redirect('index.php?error=create_failed');
}
// -----------------------------------------------------
// 9b. SAVE A/B VARIANTS (if enabled)
// -----------------------------------------------------
if ($abEnabled) {
    // Variant A = the original long URL (with 50% weight)
    add_variant(
        $id,
        $longUrl,
        'A',
        50
    );

    // Variant B = the alternative URL (if valid)
    if ($variantB !== '' && validate_url($variantB) && $variantB !== $longUrl) {
        add_variant(
            $id,
            $variantB,
            'B',
            50
        );
    }
}

// -----------------------------------------------------
// 10. REDIRECT BACK WITH SUCCESS (PRG pattern)
// -----------------------------------------------------
$link = find_link_by_code(
    $customAlias !== '' ? $customAlias : generate_code($id)
);

if (!$link) {
    redirect('index.php?error=create_failed');
}

redirect('index.php?success=created&code=' . urlencode($link['short_code']));