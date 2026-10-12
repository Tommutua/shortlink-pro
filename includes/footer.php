<?php
/**
 * =====================================================
 * ShortLink Pro — Shared Page Footer
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Closes <main>, renders footer, includes JS, closes <html>.
 *
 * Optional variables the calling page can set BEFORE including:
 *   $needsCharts = true;   // loads Chart.js (for dashboard/stats)
 * =====================================================
 */

if (!defined('APP_NAME')) {
    http_response_code(500);
    exit('footer.php must be included after config.php.');
}

$needsCharts = $needsCharts ?? false;
?>
</main>

<!-- =====================================================
     Footer
     ===================================================== -->
<footer class="site-footer">
    <div class="container footer-inner">
        <p>
            &copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong>.
            Built by <a href="#" rel="noopener">Smart Holla Tech</a>.
        </p>
        <p class="footer-links">
            <a href="<?= e(APP_URL) ?>/">Home</a>
            <span aria-hidden="true">·</span>
            <a href="<?= e(APP_URL) ?>/dashboard.php">Dashboard</a>
            <span aria-hidden="true">·</span>
            <a href="<?= e(APP_URL) ?>/bulk.php">Bulk</a>
        </p>
    </div>
</footer>

<!-- =====================================================
     Scripts
     ===================================================== -->
<script src="<?= e(APP_URL) ?>/assets/js/app.js" defer></script>

<?php if ($needsCharts): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="<?= e(APP_URL) ?>/assets/js/charts.js" defer></script>
<?php endif; ?>

</body>
</html>