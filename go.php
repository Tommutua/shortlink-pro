<?php
/**
 * =====================================================
 * ShortLink Pro — Redirect Handler
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Handles short link visits:
 *   shortlink-pro/abc123  →  go.php?c=abc123
 *
 * Flow:
 *   1. Read the code from ?c=
 *   2. Look up the link
 *   3. Check: exists? active? expired? password-protected?
 *   4. Log the click (IP, referrer, user agent)
 *   5. Increment click counter
 *   6. Redirect to the destination (302)
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/geo.php';

// -----------------------------------------------------
// 1. READ THE CODE
// -----------------------------------------------------
$code = trim((string) ($_GET['c'] ?? ''));

if ($code === '') {
    http_response_code(400);
    exit('Missing code.');
}

// -----------------------------------------------------
// 2. LOOK UP THE LINK
// -----------------------------------------------------
$link = find_link_by_code($code);

if (!$link) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="text-align: center; max-width: 480px; margin: 4rem auto;">
        <h1 style="font-size: 3rem; margin-bottom: 0.5rem;">404</h1>
        <h2>Link not found</h2>
        <p class="text-muted mt-4">
            The short link <code><?= e($code) ?></code> doesn't exist
            or has been deleted.
        </p>
        <a href="<?= e(APP_URL) ?>/" class="btn btn-primary mt-6">Back to home</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// -----------------------------------------------------
// 3. CHECK IF ACTIVE
// -----------------------------------------------------
if ((int) $link['is_active'] !== 1) {
    http_response_code(410);
    exit('This link has been disabled.');
}

// -----------------------------------------------------
// 4. CHECK EXPIRY
// -----------------------------------------------------
if (!empty($link['expires_at'])) {
    $expiresAt = strtotime((string) $link['expires_at']);
    if ($expiresAt !== false && $expiresAt < time()) {
        http_response_code(410);
        require __DIR__ . '/includes/header.php';
        ?>
        <div class="card" style="text-align: center; max-width: 480px; margin: 4rem auto;">
            <h1 style="font-size: 3rem; margin-bottom: 0.5rem;">410</h1>
            <h2>Link expired</h2>
            <p class="text-muted mt-4">
                This short link expired on
                <strong><?= e(date('M j, Y \a\t H:i', $expiresAt)) ?></strong>.
            </p>
            <a href="<?= e(APP_URL) ?>/" class="btn btn-primary mt-6">Back to home</a>
        </div>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }
}

// -----------------------------------------------------
// 5. CHECK MAX CLICKS
// -----------------------------------------------------
if (!empty($link['max_clicks']) && (int) $link['click_count'] >= (int) $link['max_clicks']) {
    http_response_code(410);
    exit('This link has reached its click limit.');
}
// -----------------------------------------------------
// 5b. A/B TESTING — pick a variant if any exist
// -----------------------------------------------------
$destinationUrl = $link['long_url'];
$variantId = null;

$variants = get_variants((int) $link['id']);
if (!empty($variants)) {
    $picked = pick_variant($variants);
    if ($picked !== null) {
        $destinationUrl = $picked['destination_url'];
        $variantId = (int) $picked['id'];
    }
}

// -----------------------------------------------------
// 6. PASSWORD-PROTECTED LINKS
// -----------------------------------------------------
// If a password_hash exists and the session doesn't have an unlock
// timestamp for this code, redirect to password.php.
if (!empty($link['password_hash'])) {
    $unlockedAt = $_SESSION['unlocked'][$code] ?? null;
    $unlockTtl  = 3600; // 1 hour

    $isUnlocked = $unlockedAt !== null && (time() - (int) $unlockedAt) < $unlockTtl;

    if (!$isUnlocked) {
        redirect('password.php?c=' . urlencode($code));
    }
}

// -----------------------------------------------------
// 7. LOG THE CLICK
// -----------------------------------------------------
try {
    $ip  = client_ip();
    $geo = get_geo($ip);

    log_click((int) $link['id'], [
        'ip'         => $ip,
        'country'    => $geo['country'],
        'city'       => $geo['city'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer'   => $_SERVER['HTTP_REFERER']    ?? '',
    ]);
} catch (Throwable $e) {
    // Never let analytics block the redirect
    error_log('[CLICK LOG ERROR] ' . $e->getMessage());
}

// -----------------------------------------------------
// 8. INCREMENT COUNTER
// -----------------------------------------------------
try {
    increment_clicks((int) $link['id']);
} catch (Throwable $e) {
    error_log('[CLICK COUNT ERROR] ' . $e->getMessage());
}

// -----------------------------------------------------
// 9. REDIRECT TO DESTINATION
// -----------------------------------------------------
// If a variant was picked, increment its counter
if ($variantId !== null) {
    try {
        increment_variant($variantId);
    } catch (Throwable $e) {
        error_log('[VARIANT INCREMENT ERROR] ' . $e->getMessage());
    }
}

header('Location: ' . $destinationUrl, true, 302);
exit;