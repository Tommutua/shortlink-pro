<?php
/**
 * =====================================================
 * ShortLink Pro — QR Code Preview Page
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Beautiful page showing a link's QR code with
 * download options and copy-to-clipboard.
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'QR Code';

// -----------------------------------------------------
// 1. READ THE CODE
// -----------------------------------------------------
$code = trim((string) ($_GET['c'] ?? ''));

if ($code === '') {
    redirect('dashboard.php');
}

// -----------------------------------------------------
// 2. LOOK UP THE LINK
// -----------------------------------------------------
$link = find_link_by_code($code);

if (!$link) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card text-center" style="max-width: 480px; margin: 4rem auto;">
        <h1 style="font-size: 3rem;">404</h1>
        <h2>Link not found</h2>
        <a href="<?= e(APP_URL) ?>/dashboard.php" class="btn btn-primary mt-6">Back to dashboard</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$shortUrl = short_url($link['short_code']);

require __DIR__ . '/includes/header.php';
?>

<div class="qr-page">
    <p class="text-sm text-muted mb-4">
        <a href="<?= e(APP_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true"> / </span>
        <span>QR Code</span>
    </p>

    <div class="qr-layout">
        <!-- LEFT: QR Preview -->
        <div class="card qr-preview-card">
            <div class="card-header">
                <h2 class="card-title">Scan to open</h2>
                <span class="badge badge-success">Ready</span>
            </div>

            <div class="qr-image-wrap">
                <img
                    src="<?= e(APP_URL) ?>/qr.php?c=<?= e($link['short_code']) ?>&size=500"
                    alt="QR code for <?= e($shortUrl) ?>"
                    class="qr-image"
                >
            </div>

            <p class="qr-url text-center font-mono text-sm mt-4">
                <?= e($shortUrl) ?>
            </p>

            <div class="flex gap-2 mt-4" style="justify-content: center; flex-wrap: wrap;">
                <button
                    class="btn btn-primary"
                    data-copy="<?= e($shortUrl) ?>"
                    data-copy-label="✓ Copied!"
                >
                    Copy link
                </button>
                <a
                    href="<?= e($shortUrl) ?>"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-secondary"
                >
                    Open link
                </a>
            </div>
        </div>

        <!-- RIGHT: Info + Downloads -->
        <div class="qr-side">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Download</h3>
                </div>
                <p class="text-sm text-muted mb-4">
                    Choose a size. Larger sizes are ideal for print.
                </p>

                <div class="download-list">
                    <a
                        href="<?= e(APP_URL) ?>/qr.php?c=<?= e($link['short_code']) ?>&size=200&download=1"
                        class="download-item"
                    >
                        <div>
                            <strong>Small</strong>
                            <div class="text-xs text-muted">200 × 200 px — web &amp; social</div>
                        </div>
                        <span class="download-icon">⬇</span>
                    </a>

                    <a
                        href="<?= e(APP_URL) ?>/qr.php?c=<?= e($link['short_code']) ?>&size=500&download=1"
                        class="download-item"
                    >
                        <div>
                            <strong>Medium</strong>
                            <div class="text-xs text-muted">500 × 500 px — posters</div>
                        </div>
                        <span class="download-icon">⬇</span>
                    </a>

                    <a
                        href="<?= e(APP_URL) ?>/qr.php?c=<?= e($link['short_code']) ?>&size=1000&download=1"
                        class="download-item"
                    >
                        <div>
                            <strong>Large</strong>
                            <div class="text-xs text-muted">1000 × 1000 px — print</div>
                        </div>
                        <span class="download-icon">⬇</span>
                    </a>
                </div>
            </div>

            <div class="card mt-6">
                <div class="card-header">
                    <h3 class="card-title">Details</h3>
                </div>
                <ul class="detail-list">
                    <li>
                        <span class="text-muted">Short code</span>
                        <span class="font-mono"><?= e($link['short_code']) ?></span>
                    </li>
                    <li>
                        <span class="text-muted">Destination</span>
                        <span class="truncate" title="<?= e($link['long_url']) ?>">
                            <?= e($link['long_url']) ?>
                        </span>
                    </li>
                    <li>
                        <span class="text-muted">Clicks</span>
                        <span><?= (int) $link['click_count'] ?></span>
                    </li>
                    <li>
                        <span class="text-muted">Created</span>
                        <span><?= e(date('M j, Y', strtotime($link['created_at']))) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>