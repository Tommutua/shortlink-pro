<?php
/**
 * =====================================================
 * ShortLink Pro — Public Link Stats
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Public per-link analytics page.
 * Accessible to anyone with the code (aggregate data only,
 * no individual visitor information).
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Link Stats';

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
        <div style="font-size: 3rem; margin-bottom: var(--space-4);">🔍</div>
        <h1 style="margin-bottom: var(--space-2);">Link not found</h1>
        <p class="text-muted mb-6">
            The code <code><?= e($code) ?></code> doesn't exist or has been deleted.
        </p>
        <a href="<?= e(APP_URL) ?>/" class="btn btn-primary">Back to home</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// -----------------------------------------------------
// 3. GATHER ALL STATS
// -----------------------------------------------------
$linkId     = (int) $link['id'];
$shortUrl   = short_url($link['short_code']);
$totalClicks = (int) $link['click_count'];
$clicks7d   = clicks_in_last_days($linkId, 7);
$countries  = countries_for_link($linkId, 6);
$uniqueCountries = unique_countries_for_link($linkId);
$devices    = devices_for_link($linkId);
$referrers  = referrers_for_link($linkId, 8);
$recent     = recent_clicks_for_link($linkId, 15);
$dailyClicks = clicks_by_day_for_link($linkId, 30);
// A/B variants (if any)
$variants = get_variants($linkId);
$hasVariants = !empty($variants);

// Top referrer
$topReferrer = $referrers[0]['source'] ?? '—';
$topReferrerCount = $referrers[0]['total'] ?? 0;

// Mobile %
$mobileCount = 0;
$deviceTotal = 0;
foreach ($devices as $d) {
    $deviceTotal += (int) $d['total'];
    if ($d['device_type'] === 'mobile') {
        $mobileCount = (int) $d['total'];
    }
}
$mobilePercent = $deviceTotal > 0
    ? (int) round(($mobileCount / $deviceTotal) * 100)
    : 0;

// -----------------------------------------------------
// 4. PREPARE CHART DATA
// -----------------------------------------------------
$chartLabels = array_map(
    fn($row) => date('M j', strtotime($row['day'])),
    $dailyClicks
);
$chartValues = array_map(fn($row) => $row['total'], $dailyClicks);

$countryLabels = array_map(fn($r) => $r['country'], $countries);
$countryValues = array_map(fn($r) => (int) $r['total'], $countries);

$deviceLabels = array_map(fn($r) => ucfirst($r['device_type']), $devices);
$deviceValues = array_map(fn($r) => (int) $r['total'], $devices);

$needsCharts = true;

require __DIR__ . '/includes/header.php';
?>

<div class="stats-page">

    <!-- =====================================================
         Breadcrumb + actions
         ===================================================== -->
    <div class="flex items-center justify-between mb-6" style="flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <p class="text-sm text-muted mb-2" style="margin: 0;">
                <a href="<?= e(APP_URL) ?>/dashboard.php">Dashboard</a>
                <span aria-hidden="true"> / </span>
                <span>Stats</span>
            </p>
            <h1 style="margin: 0;">
                <span class="font-mono"><?= e($link['short_code']) ?></span>
            </h1>
            <p class="text-muted mt-2" style="margin: 0;">
                <span class="truncate" title="<?= e($link['long_url']) ?>">
                    → <?= e($link['long_url']) ?>
                </span>
            </p>
        </div>

        <div class="flex gap-2" style="flex-wrap: wrap;">
            <button
                class="btn btn-secondary btn-sm"
                data-copy="<?= e($shortUrl) ?>"
                data-copy-label="✓"
            >
                Copy link
            </button>
            <a href="<?= e(APP_URL) ?>/qr-page.php?c=<?= e($link['short_code']) ?>"
               class="btn btn-secondary btn-sm">
                QR code
            </a>
            <a href="<?= e($shortUrl) ?>"
               target="_blank" rel="noopener"
               class="btn btn-primary btn-sm">
                Open link →
            </a>
        </div>
    </div>

    <!-- Public notice -->
    <div class="alert alert-info mb-6" style="font-size: var(--text-sm);">
        ℹ️ This is a <strong>public stats page</strong>. Anyone with the link can view it.
        Only aggregate data is shown — no individual visitors are identified.
    </div>

    <!-- =====================================================
         Stat cards
         ===================================================== -->
    <section class="grid grid-4 mb-6">
        <div class="stat-card">
            <div class="stat-label">Total clicks</div>
            <div class="stat-value"><?= number_format($totalClicks) ?></div>
            <div class="stat-trend">All time</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Last 7 days</div>
            <div class="stat-value"><?= number_format($clicks7d) ?></div>
            <div class="stat-trend">Recent activity</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Countries</div>
            <div class="stat-value"><?= number_format($uniqueCountries) ?></div>
            <div class="stat-trend">Unique origins</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Mobile share</div>
            <div class="stat-value"><?= $mobilePercent ?>%</div>
            <div class="stat-trend">Of total clicks</div>
        </div>
    </section>

    <!-- =====================================================
         Main chart: 30-day timeline
         ===================================================== -->
    <section class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Clicks — last 30 days</h3>
            <span class="badge badge-muted">
                <?= number_format(array_sum($chartValues)) ?> total
            </span>
        </div>
        <div class="chart-wrap" style="height: 280px;">
            <canvas id="chart-clicks"></canvas>
        </div>
    </section>
    <!-- =====================================================
     A/B Test Results (only if variants exist)
     ===================================================== -->
<?php if ($hasVariants): ?>
    <?php
        // Compute variant stats
        $variantTotal = 0;
        foreach ($variants as $v) {
            $variantTotal += (int) $v['click_count'];
        }

        // Find winner
        $winnerId = null;
        $maxClicks = -1;
        foreach ($variants as $v) {
            if ((int) $v['click_count'] > $maxClicks) {
                $maxClicks = (int) $v['click_count'];
                $winnerId = (int) $v['id'];
            }
        }
    ?>
    <section class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">🧪 A/B Test Results</h3>
            <span class="badge badge-primary"><?= count($variants) ?> variants</span>
        </div>

        <p class="text-sm text-muted mb-4">
            Traffic split between <?= count($variants) ?> destinations.
            <?php if ($variantTotal === 0): ?>
                No clicks yet — share the link to start collecting data.
            <?php endif; ?>
        </p>

        <div class="variant-grid">
            <?php foreach ($variants as $v): ?>
                <?php
                    $clicks = (int) $v['click_count'];
                    $pct = $variantTotal > 0 ? round(($clicks / $variantTotal) * 100, 1) : 0;
                    $isWinner = $v['id'] === $winnerId && $variantTotal > 0;
                ?>
                <div class="variant-card <?= $isWinner ? 'variant-winner' : '' ?>">
                    <div class="variant-header">
                        <span class="variant-label">
                            <?= e($v['label'] ?: 'Variant') ?>
                        </span>
                        <?php if ($isWinner): ?>
                            <span class="badge badge-success">🏆 Winner</span>
                        <?php endif; ?>
                    </div>

                    <div class="variant-url truncate" title="<?= e($v['destination_url']) ?>">
                        <?= e($v['destination_url']) ?>
                    </div>

                    <div class="variant-stats">
                        <div class="variant-clicks"><?= number_format($clicks) ?></div>
                        <div class="variant-pct"><?= $pct ?>% of traffic</div>
                    </div>

                    <!-- Progress bar -->
                    <div class="variant-bar">
                        <div class="variant-bar-fill" style="width: <?= $pct ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($variantTotal > 0): ?>
            <p class="text-xs text-muted mt-4">
                Total variant clicks: <?= number_format($variantTotal) ?> ·
                A/B testing is currently live on this link.
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

    <!-- =====================================================
         Countries + Devices
         ===================================================== -->
    <section class="grid grid-2 mb-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top countries</h3>
            </div>
            <?php if (empty($countries)): ?>
                <div class="chart-wrap">
                    <p class="text-muted text-sm" style="display: grid; place-items: center; height: 100%; text-align: center;">
                        No country data yet.<br>
                        <span class="text-xs">Click the link to start collecting.</span>
                    </p>
                </div>
            <?php else: ?>
                <div class="chart-wrap">
                    <canvas id="chart-countries"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Devices</h3>
            </div>
            <?php if (empty($devices)): ?>
                <div class="chart-wrap">
                    <p class="text-muted text-sm" style="display: grid; place-items: center; height: 100%; text-align: center;">
                        No device data yet.
                    </p>
                </div>
            <?php else: ?>
                <div class="chart-wrap">
                    <canvas id="chart-devices"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- =====================================================
         Referrers
         ===================================================== -->
    <section class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Top referrers</h3>
            <span class="text-xs text-muted">Where the clicks came from</span>
        </div>

        <?php if (empty($referrers)): ?>
            <p class="text-muted text-sm text-center" style="padding: 2rem 0;">
                No referrer data yet.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th class="text-right">Clicks</th>
                            <th class="text-right">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrers as $r): ?>
                            <?php
                                $share = $totalClicks > 0
                                    ? round(($r['total'] / $totalClicks) * 100, 1)
                                    : 0;
                                $isDirect = $r['source'] === '(direct)';
                            ?>
                            <tr>
                                <td>
                                    <?php if ($isDirect): ?>
                                        <span class="text-muted">Direct / unknown</span>
                                    <?php else: ?>
                                        <span class="truncate" title="<?= e($r['source']) ?>">
                                            <?= e($r['source']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <span class="badge badge-primary"><?= (int) $r['total'] ?></span>
                                </td>
                                <td class="text-right text-muted text-sm"><?= $share ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- =====================================================
         Recent clicks feed
         ===================================================== -->
    <section class="card mb-8">
        <div class="card-header">
            <h3 class="card-title">Recent clicks</h3>
            <span class="text-xs text-muted">Privacy-safe: location + device + source only</span>
        </div>

        <?php if (empty($recent)): ?>
            <p class="text-muted text-sm text-center" style="padding: 2rem 0;">
                No clicks yet. Share the link to get started.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Location</th>
                            <th>Device</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $c): ?>
                            <?php
                                $when = date('M j, H:i', strtotime($c['clicked_at']));
                                $location = trim(($c['city'] ?? '') . ', ' . ($c['country'] ?? ''), ', ');
                                if ($location === '') $location = '—';
                                $device = $c['device_type'] ? ucfirst($c['device_type']) : 'Unknown';
                                $source = $c['source'] === '(direct)' ? 'Direct' : $c['source'];
                            ?>
                            <tr>
                                <td class="text-muted text-sm"><?= e($when) ?></td>
                                <td><?= e($location) ?></td>
                                <td>
                                    <span class="badge badge-muted"><?= e($device) ?></span>
                                </td>
                                <td class="truncate text-sm" title="<?= e($source) ?>">
                                    <?= e($source) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- =====================================================
         Meta info
         ===================================================== -->
    <section class="card">
        <div class="card-header">
            <h3 class="card-title">Link details</h3>
        </div>
        <ul class="detail-list">
            <li>
                <span class="text-muted">Short URL</span>
                <span class="font-mono"><?= e($shortUrl) ?></span>
            </li>
            <li>
                <span class="text-muted">Destination</span>
                <span class="truncate" title="<?= e($link['long_url']) ?>">
                    <?= e($link['long_url']) ?>
                </span>
            </li>
            <li>
                <span class="text-muted">Created</span>
                <span><?= e(date('M j, Y \a\t H:i', strtotime($link['created_at']))) ?></span>
            </li>
            <?php if (!empty($link['expires_at'])): ?>
                <li>
                    <span class="text-muted">Expires</span>
                    <span><?= e(date('M j, Y \a\t H:i', strtotime($link['expires_at']))) ?></span>
                </li>
            <?php endif; ?>
            <?php if (!empty($link['password_hash'])): ?>
                <li>
                    <span class="text-muted">Access</span>
                    <span><span class="badge badge-warning">🔒 Password</span></span>
                </li>
            <?php endif; ?>
        </ul>
    </section>

</div>

<!-- =====================================================
     Chart data (read by charts.js)
     ===================================================== -->
<script type="application/json" id="chart-data">
<?= json_encode([
    'clicks' => [
        'labels' => $chartLabels,
        'values' => $chartValues,
    ],
    'countries' => [
        'labels' => $countryLabels,
        'values' => $countryValues,
    ],
    'devices' => [
        'labels' => $deviceLabels,
        'values' => $deviceValues,
    ],
], JSON_UNESCAPED_SLASHES) ?>
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>