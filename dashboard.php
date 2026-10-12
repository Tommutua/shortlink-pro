<?php
/**
 * =====================================================
 * ShortLink Pro — Dashboard
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Overview page showing:
 *   - Summary stat cards
 *   - Clicks over time (chart)
 *   - Top links, top countries
 *   - All links table with actions
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Dashboard';

// -----------------------------------------------------
// Flash messages
// -----------------------------------------------------
$flash = $_GET['flash'] ?? '';

// -----------------------------------------------------
// Fetch data
// -----------------------------------------------------
$totalLinks   = count_links();
$totalClicks  = count_clicks();
$links        = get_all_links(200);
$topLinks     = top_links(5);
$topCountries = top_countries(5);
$devices      = device_breakdown();
$clicksByDay  = clicks_by_day(7);

// -----------------------------------------------------
// Derived stats
// -----------------------------------------------------
$avgClicksPerLink = $totalLinks > 0
    ? round($totalClicks / $totalLinks, 1)
    : 0;

// Mobile percentage
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
// Chart data prep
// -----------------------------------------------------
$chartLabels = array_map(
    fn($row) => date('D', strtotime($row['day'])),
    $clicksByDay
);
$chartValues = array_map(fn($row) => $row['total'], $clicksByDay);

$countryLabels = array_map(fn($r) => $r['country'], $topCountries);
$countryValues = array_map(fn($r) => (int) $r['total'], $topCountries);

$deviceLabels = array_map(fn($r) => ucfirst($r['device_type']), $devices);
$deviceValues = array_map(fn($r) => (int) $r['total'], $devices);

$needsCharts = true;

require __DIR__ . '/includes/header.php';
?>

<!-- =====================================================
     Page header
     ===================================================== -->
<div class="flex items-center justify-between mb-6" style="flex-wrap: wrap; gap: var(--space-4);">
    <div>
        <h1 style="margin-bottom: var(--space-1);">Dashboard</h1>
        <p class="text-muted" style="margin: 0;">Overview of your shortened links and clicks.</p>
    </div>
    <a href="<?= e(APP_URL) ?>/" class="btn btn-primary">+ New link</a>
</div>

<?php if ($flash === 'deleted'): ?>
    <div class="alert alert-success" data-auto-dismiss="3000">
        ✅ Link deleted successfully.
    </div>
<?php endif; ?>

<!-- =====================================================
     Stat cards
     ===================================================== -->
<section class="grid grid-4 mb-6">
    <div class="stat-card">
        <div class="stat-label">Total links</div>
        <div class="stat-value"><?= number_format($totalLinks) ?></div>
        <div class="stat-trend">Across all time</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Total clicks</div>
        <div class="stat-value"><?= number_format($totalClicks) ?></div>
        <div class="stat-trend">Every redirect logged</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Avg clicks / link</div>
        <div class="stat-value"><?= e((string) $avgClicksPerLink) ?></div>
        <div class="stat-trend">Performance indicator</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Mobile share</div>
        <div class="stat-value"><?= $mobilePercent ?>%</div>
        <div class="stat-trend">Of all clicks</div>
    </div>
</section>

<!-- =====================================================
     Charts row: clicks over time + countries
     ===================================================== -->
<section class="grid grid-2 mb-6">
    <!-- Clicks over time -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Clicks — last 7 days</h3>
            <span class="badge badge-muted"><?= number_format(array_sum($chartValues)) ?> total</span>
        </div>
        <div class="chart-wrap">
            <canvas id="chart-clicks"></canvas>
        </div>
    </div>

    <!-- Top countries -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top countries</h3>
        </div>
        <?php if (empty($topCountries)): ?>
            <div class="chart-wrap">
                <p class="text-muted text-sm" style="display: grid; place-items: center; height: 100%; text-align: center;">
                    No country data yet.<br>
                    <span class="text-xs">Geo lookup will be added soon.</span>
                </p>
            </div>
        <?php else: ?>
            <div class="chart-wrap">
                <canvas id="chart-countries"></canvas>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- =====================================================
     Top links + Devices
     ===================================================== -->
<section class="grid grid-2 mb-8">
    <!-- Top links -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top performing links</h3>
        </div>
        <?php if (empty($topLinks)): ?>
            <div class="chart-wrap">
                <p class="text-muted text-sm" style="display: grid; place-items: center; height: 100%; text-align: center;">
                    No links yet.<br>
                    <a href="<?= e(APP_URL) ?>/" class="text-xs">Create your first one →</a>
                </p>
            </div>
        <?php else: ?>
            <ul class="top-list">
                <?php foreach ($topLinks as $i => $row): ?>
                    <li class="top-item">
                        <span class="top-rank"><?= $i + 1 ?></span>
                        <div class="top-body">
                            <a href="<?= e(short_url($row['short_code'])) ?>"
                               target="_blank" rel="noopener"
                               class="font-mono"><?= e($row['short_code']) ?></a>
                            <div class="text-xs text-muted truncate">
                                <?= e($row['long_url']) ?>
                            </div>
                        </div>
                        <span class="badge badge-primary"><?= (int) $row['click_count'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Devices -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Devices</h3>
        </div>
        <?php if (empty($devices)): ?>
            <div class="chart-wrap">
                <p class="text-muted text-sm" style="display: grid; place-items: center; height: 100%; text-align: center;">
                    Device data appears after first clicks.<br>
                    <span class="text-xs">User-Agent parsing coming soon.</span>
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
     All links
     ===================================================== -->
<section>
    <div class="flex items-center justify-between mb-4" style="flex-wrap: wrap; gap: var(--space-3);">
        <h2 style="margin: 0;">All links</h2>
        <input
            type="search"
            id="links-search"
            class="form-input"
            placeholder="Search code or URL..."
            style="max-width: 280px;"
        >
    </div>

    <?php if (empty($links)): ?>
        <div class="card text-center" style="padding: 3rem;">
            <div style="font-size: 3rem; margin-bottom: var(--space-4);">🔗</div>
            <h3>No links yet</h3>
            <p class="text-muted mb-6">Shorten your first URL to get started.</p>
            <a href="<?= e(APP_URL) ?>/" class="btn btn-primary">Create your first link</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table" id="links-table">
                <thead>
                    <tr>
                        <th>Short URL</th>
                        <th>Destination</th>
                        <th>Clicks</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link): ?>
                        <?php
                            $code     = $link['short_code'];
                            $shortUrl = short_url($code);
                            $hasPw    = !empty($link['password_hash']);
                            $hasExp   = !empty($link['expires_at']);
                            $created  = date('M j, Y', strtotime($link['created_at']));
                            $searchable = strtolower($code . ' ' . $link['long_url']);
                        ?>
                        <tr data-search="<?= e($searchable) ?>">
                            <td>
                                <a href="<?= e($shortUrl) ?>" target="_blank" rel="noopener"
                                   class="font-mono"><?= e($code) ?></a>
                                <?php if ($hasPw): ?>
                                    <span class="badge badge-warning" title="Password protected">🔒</span>
                                <?php endif; ?>
                                <?php if ($hasExp): ?>
                                    <span class="badge badge-muted" title="Has expiry">⏱</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-url">
                                <span class="truncate"><?= e($link['long_url']) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?= (int) $link['click_count'] ?></span>
                            </td>
                            <td class="text-muted text-sm"><?= e($created) ?></td>
                            <td class="text-right">
                                <div class="flex gap-2" style="justify-content: flex-end; flex-wrap: nowrap;">
                                    <button
                                        class="btn btn-secondary btn-sm"
                                        data-copy="<?= e($shortUrl) ?>"
                                        data-copy-label="✓"
                                        title="Copy short URL">
                                        Copy
                                    </button>
                                    <a
                                        href="<?= e(APP_URL) ?>/stats.php?c=<?= e($code) ?>"
                                        class="btn btn-secondary btn-sm"
                                        title="View analytics">
                                        Stats
                                    </a>
                                    <a
    href="<?= e(APP_URL) ?>/qr-page.php?c=<?= e($code) ?>"
    class="btn btn-secondary btn-sm"
    title="View & download QR code">
    QR
</a>
                                    <form
    method="POST"
    action="<?= e(APP_URL) ?>/delete.php"
    style="display: inline;"
    data-confirm="Delete this link and all its click data? This can't be undone."
>
    <?= csrf_field() ?>
    <input type="hidden" name="c" value="<?= e($code) ?>">
    <button type="submit" class="btn btn-danger btn-sm" title="Delete link">
        Delete
    </button>
</form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

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