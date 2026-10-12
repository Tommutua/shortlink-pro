<?php
/**
 * =====================================================
 * ShortLink Pro — Home / Landing Page
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Renders: shorten form, success/error messages,
 *          recently shortened links, feature highlights.
 *
 * Also acts as a routing fallback for Railway/FrankenPHP:
 * short codes and special routes are delegated here when
 * .htaccess isn't available.
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// -----------------------------------------------------
// ROUTING FALLBACK — Handle short codes and special paths
// -----------------------------------------------------
// On Railway (FrankenPHP), .htaccess is ignored. Requests for
// /abc123 don't match a real file, so the server falls through
// to index.php. We detect short codes here and delegate.
//
// Skip the check when we're already inside a real PHP file
// (e.g. /dashboard.php, /go.php) so we don't loop.
// -----------------------------------------------------

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$isDirectCall = ($scriptName === '/index.php')
             || ($scriptName === '')
             || (substr($scriptName, -9) === '/index.php');

// Only do routing if this is a top-level request (not an internal require)
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentUri = '/' . trim(urldecode($currentUri), '/');

// Direct requests to real .php files (like /go.php?c=xxx, /dashboard.php)
// shouldn't be re-routed. Only intercept clean URLs.
$isPhpFile = preg_match('#\.php$#', $currentUri);

if (!$isPhpFile && $currentUri !== '/' && $currentUri !== '') {

    // /stats/{code}
    if (preg_match('#^/stats/([a-zA-Z0-9_-]+)$#', $currentUri, $m)) {
        $_GET['c'] = $m[1];
        require __DIR__ . '/stats.php';
        exit;
    }

    // /u/{username}  → bio page
    if (preg_match('#^/u/([a-zA-Z0-9_-]+)$#', $currentUri, $m)) {
        $_GET['u'] = $m[1];
        require __DIR__ . '/bio.php';
        exit;
    }

    // /{code}  → redirect
    if (preg_match('#^/([a-zA-Z0-9_-]+)$#', $currentUri, $m)) {
        $_GET['c'] = $m[1];
        require __DIR__ . '/go.php';
        exit;
    }
}

// -----------------------------------------------------
// Normal homepage rendering
// -----------------------------------------------------

$pageTitle = 'Shorten a URL';

// Handle success / error messages from previous requests
$success  = $_GET['success']  ?? '';
$error    = $_GET['error']    ?? '';
$newCode  = $_GET['code']     ?? '';

// Fetch the freshly created link (if just shortened)
$newLink = null;
if ($newCode !== '') {
    $newLink = find_link_by_code($newCode);
}

// Fetch recent links (for the demo section)
$recentLinks = get_all_links(6);

// Human-readable error messages
$errorMessages = [
    'invalid_url'      => 'That doesn\'t look like a valid URL. Make sure it starts with http:// or https://',
    'alias_taken'      => 'That custom alias is already in use. Try a different one.',
    'alias_invalid'    => 'That custom alias is invalid. Use 3–32 letters, numbers, hyphens, or underscores.',
    'alias_reserved'   => 'That alias is reserved by the system. Please choose another.',
    'rate_limit'       => 'You\'ve created too many links in a short time. Please wait a moment and try again.',
    'create_failed'    => 'Something went wrong while creating your link. Please try again.',
    'invalid_password' => 'Password must be at least 4 characters.',
];

$errorText = $errorMessages[$error] ?? ($error !== '' ? 'An error occurred.' : '');

require __DIR__ . '/includes/header.php';
?>

<!-- =====================================================
     HERO + SHORTEN FORM
     ===================================================== -->
<section class="hero fade-in">
    <div class="hero-text">
        <h1 class="hero-title">
            Short links, <span class="hero-accent">big intelligence.</span>
        </h1>
        <p class="hero-subtitle">
            Shorten any URL. Track every click. Analyze every visitor.
            Built for marketers, developers, and anyone who cares where their links go.
        </p>
    </div>

    <!-- Success message -->
    <?php if ($success === 'created' && $newLink): ?>
        <div class="alert alert-success alert-created">
            <strong>🎉 Your short link is ready!</strong>
            <div class="input-group mt-4">
                <input
                    id="new-short-url"
                    type="text"
                    class="form-input font-mono"
                    value="<?= e(short_url($newLink['short_code'])) ?>"
                    readonly
                >
                <button
                    class="btn btn-primary"
                    data-copy="true"
                    data-copy-target="#new-short-url"
                    data-copy-label="✓ Copied!"
                >
                    Copy
                </button>
            </div>
            <div class="mt-4 flex gap-2" style="flex-wrap: wrap;">
                <a href="<?= e(short_url($newLink['short_code'])) ?>"
                   class="btn btn-secondary btn-sm"
                   target="_blank" rel="noopener">
                    Open link →
                </a>
                <a href="<?= e(APP_URL) ?>/stats.php?c=<?= e($newLink['short_code']) ?>"
                   class="btn btn-secondary btn-sm">
                    View stats
                </a>
                <a href="<?= e(APP_URL) ?>/qr.php?c=<?= e($newLink['short_code']) ?>"
                   class="btn btn-secondary btn-sm"
                   target="_blank">
                    Download QR
                </a>
            </div>
            <p class="text-sm text-muted mt-4">
                Destination: <span class="truncate"><?= e($newLink['long_url']) ?></span>
            </p>
        </div>
    <?php endif; ?>

    <!-- Error message -->
    <?php if ($errorText !== ''): ?>
        <div class="alert alert-danger">
            <?= e($errorText) ?>
        </div>
    <?php endif; ?>

    <!-- The shorten form -->
    <div class="card card-form mt-6">
        <form method="POST" action="shorten.php" class="shorten-form" novalidate>
            <!-- Long URL (required) -->
            <div class="form-group">
                <label for="long_url" class="form-label">
                    Paste your long URL <span class="required">*</span>
                </label>
                <input
                    type="url"
                    id="long_url"
                    name="long_url"
                    class="form-input form-input-lg"
                    placeholder="https://example.com/very/long/path/that/needs/shortening"
                    required
                    data-autofocus
                    autocomplete="off"
                >
            </div>

            <!-- Advanced options (collapsible) -->
            <details class="advanced-options">
                <summary class="advanced-summary">
                    ⚙️ Advanced options <span class="text-muted">(custom alias, expiry, password)</span>
                </summary>

                <div class="advanced-grid">
                    <!-- Custom alias -->
                    <div class="form-group">
                        <label for="custom_alias" class="form-label">Custom alias</label>
                        <div class="input-prefix-group">
                            <span class="input-prefix"><?= e(preg_replace('#^https?://#', '', APP_URL)) ?>/</span>
                            <input
                                type="text"
                                id="custom_alias"
                                name="custom_alias"
                                class="form-input"
                                placeholder="my-link"
                                pattern="[a-zA-Z0-9_-]{3,32}"
                                maxlength="32"
                                autocomplete="off"
                            >
                        </div>
                        <span class="form-hint">3–32 chars: letters, numbers, - and _</span>
                    </div>

                    <!-- Expiry -->
                    <div class="form-group">
                        <label for="expiry" class="form-label">Expires</label>
                        <select id="expiry" name="expiry" class="form-select">
                            <option value="never">Never</option>
                            <option value="1h">In 1 hour</option>
                            <option value="24h">In 24 hours</option>
                            <option value="7d">In 7 days</option>
                            <option value="30d">In 30 days</option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password (optional)</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Leave empty for none"
                            minlength="4"
                            autocomplete="new-password"
                        >
                        <span class="form-hint">Visitors must enter this to reach the destination</span>
                    </div>

                    <!-- A/B Testing -->
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-checkbox-label">
                            <input
                                type="checkbox"
                                name="ab_enabled"
                                value="1"
                                id="ab_enabled"
                                onchange="document.getElementById('ab-fields').style.display = this.checked ? 'block' : 'none'"
                            >
                            <span>Enable A/B testing</span>
                        </label>
                        <span class="form-hint">
                            Split traffic 50/50 between two URLs to compare performance.
                        </span>
                    </div>

                    <!-- Variant B URL (hidden until A/B is enabled) -->
                    <div class="form-group" id="ab-fields" style="display: none; grid-column: 1 / -1;">
                        <label for="variant_b" class="form-label">Variant B URL</label>
                        <input
                            type="url"
                            id="variant_b"
                            name="variant_b"
                            class="form-input"
                            placeholder="https://example.com/alternative-landing-page"
                        >
                        <span class="form-hint">
                            Your original URL becomes Variant A. This URL becomes Variant B. Clicks are split 50/50.
                        </span>
                    </div>
                </div>
            </details>

            <!-- Submit -->
            <button type="submit" class="btn btn-primary btn-lg btn-block mt-6">
                Shorten URL →
            </button>
        </form>
    </div>
</section>

<!-- =====================================================
     FEATURES (small trust block)
     ===================================================== -->
<section class="features mt-12">
    <div class="grid grid-3">
        <div class="feature">
            <div class="feature-icon">🔗</div>
            <h3 class="feature-title">Base62 Codes</h3>
            <p class="feature-desc">Compact, non-guessable short codes. Nobody can enumerate your links.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">📊</div>
            <h3 class="feature-title">Deep Analytics</h3>
            <p class="feature-desc">Clicks, countries, devices, browsers, referrers — every metric that matters.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">🔒</div>
            <h3 class="feature-title">Private Links</h3>
            <p class="feature-desc">Password-protect any link. Set expiry dates. Auto-purge when done.</p>
        </div>
    </div>
</section>

<!-- =====================================================
     RECENT LINKS (proof it works + demo value)
     ===================================================== -->
<?php if (!empty($recentLinks)): ?>
<section class="recent mt-12">
    <div class="flex items-center justify-between mb-4">
        <h2>Recently shortened</h2>
        <a href="<?= e(APP_URL) ?>/dashboard.php" class="text-sm">View all →</a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Short URL</th>
                    <th>Destination</th>
                    <th>Clicks</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLinks as $link): ?>
                    <?php
                        $shortCode = $link['short_code'];
                        $shortUrl  = short_url($shortCode);
                        $createdAt = date('M j', strtotime($link['created_at']));
                    ?>
                    <tr>
                        <td>
                            <a href="<?= e($shortUrl) ?>" target="_blank" rel="noopener" class="font-mono">
                                <?= e($shortCode) ?>
                            </a>
                        </td>
                        <td class="cell-url">
                            <span class="truncate"><?= e($link['long_url']) ?></span>
                        </td>
                        <td>
                            <span class="badge badge-muted"><?= (int) $link['click_count'] ?></span>
                        </td>
                        <td class="text-muted text-sm"><?= e($createdAt) ?></td>
                        <td>
                            <button
                                class="btn btn-secondary btn-sm"
                                data-copy="<?= e($shortUrl) ?>"
                                data-copy-label="✓"
                            >
                                Copy
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>