<?php
/**
 * =====================================================
 * ShortLink Pro — Password Unlock Page
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Shows a password form for password-protected links.
 * On correct password: unlocks the code in session, redirects onward.
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Protected link';

// -----------------------------------------------------
// 1. READ THE CODE FROM QUERY STRING
// -----------------------------------------------------
$code = trim((string) ($_GET['c'] ?? $_POST['c'] ?? ''));

if ($code === '') {
    redirect('index.php');
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
            The short link <code><?= e($code) ?></code> doesn't exist.
        </p>
        <a href="<?= e(APP_URL) ?>/" class="btn btn-primary mt-6">Back to home</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// -----------------------------------------------------
// 3. IF THE LINK ISN'T PROTECTED, JUST GO
// -----------------------------------------------------
if (empty($link['password_hash'])) {
    redirect('go.php?c=' . urlencode($code));
}

// -----------------------------------------------------
// 4. IF ALREADY UNLOCKED IN SESSION, SKIP THE FORM
// -----------------------------------------------------
$unlockedAt = $_SESSION['unlocked'][$code] ?? null;
$unlockTtl  = 3600; // 1 hour

if ($unlockedAt !== null && (time() - (int) $unlockedAt) < $unlockTtl) {
    redirect('go.php?c=' . urlencode($code));
}

// -----------------------------------------------------
// 5. HANDLE POST — VERIFY PASSWORD
// -----------------------------------------------------
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPassword = (string) ($_POST['password'] ?? '');

    // -- Rate limit password attempts --
    if (!rate_limit_password($code, client_ip())) {
        $error = 'Too many incorrect attempts. Please try again in 15 minutes.';
    } elseif ($submittedPassword === '') {
        $error = 'Please enter a password.';
    } elseif (password_verify($submittedPassword, $link['password_hash'])) {
        // ✅ Correct — unlock in session and redirect
        $_SESSION['unlocked'][$code] = time();
        redirect('go.php?c=' . urlencode($code));
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

// -----------------------------------------------------
// 6. RENDER THE FORM
// -----------------------------------------------------
require __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 440px; margin: 4rem auto;">
    <div style="text-align: center; margin-bottom: var(--space-6);">
        <div style="font-size: 2.5rem; line-height: 1; margin-bottom: var(--space-3);">🔒</div>
        <h1 style="font-size: var(--text-2xl); margin-bottom: var(--space-2);">
            Password protected
        </h1>
        <p class="text-muted text-sm" style="margin: 0;">
            Enter the password to continue to the destination.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="c" value="<?= e($code) ?>">

        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-input"
                placeholder="Enter password"
                required
                autocomplete="off"
                data-autofocus
            >
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            Unlock →
        </button>
    </form>

    <p class="text-center text-xs text-muted mt-6" style="margin-bottom: 0;">
        Short code: <span class="font-mono"><?= e($code) ?></span>
    </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>