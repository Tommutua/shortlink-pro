<?php
/**
 * =====================================================
 * ShortLink Pro — Bulk CSV Upload
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Upload a CSV of URLs. Each becomes a short link.
 *
 * CSV format (header row optional):
 *   url,custom_alias,expiry_days
 *   https://example.com/a,,30
 *   https://example.com/b,promo-b,7
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Bulk Shorten';

// -----------------------------------------------------
// Constants
// -----------------------------------------------------
const BULK_MAX_ROWS = 50;      // max rows per upload
const BULK_MAX_BYTES = 1048576; // 1 MB max file size

// -----------------------------------------------------
// State
// -----------------------------------------------------
$results = [];   // array of ['line' => int, 'url' => string, 'status' => 'ok'|'error', 'message' => string, 'short' => ?string]
$summary = null; // ['ok' => int, 'errors' => int, 'total' => int]
$error   = '';

// -----------------------------------------------------
// Handle POST — process uploaded CSV
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verify_csrf()) {
        $error = 'Session expired. Please try again.';
    }
    // File present?
    elseif (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = $_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_NO_FILE                       => 'Please choose a CSV file.',
            default                                  => 'Upload failed. Please try again.',
        };
    }
    // File size check
    elseif ((int) $_FILES['csv']['size'] > BULK_MAX_BYTES) {
        $error = 'File is too large. Maximum size is 1 MB.';
    }
    // Extension check
    elseif (strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'Only .csv files are accepted.';
    }
    else {
        $rows = parse_csv_file($_FILES['csv']['tmp_name']);

        if (empty($rows)) {
            $error = 'The CSV file is empty or has no valid rows.';
        } elseif (count($rows) > BULK_MAX_ROWS) {
            $error = 'Too many rows. Maximum is ' . BULK_MAX_ROWS . ' rows per upload.';
        } else {
            $results = process_bulk_rows($rows);
            $summary = [
                'ok'     => count(array_filter($results, fn($r) => $r['status'] === 'ok')),
                'errors' => count(array_filter($results, fn($r) => $r['status'] !== 'ok')),
                'total'  => count($results),
            ];
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="bulk-page">

    <!-- =====================================================
         Header
         ===================================================== -->
    <div class="flex items-center justify-between mb-6" style="flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <p class="text-sm text-muted" style="margin: 0;">
                <a href="<?= e(APP_URL) ?>/dashboard.php">Dashboard</a>
                <span aria-hidden="true"> / </span>
                <span>Bulk upload</span>
            </p>
            <h1 style="margin: var(--space-2) 0 0;">Bulk shorten URLs</h1>
            <p class="text-muted" style="margin: var(--space-2) 0 0;">
                Upload a CSV to shorten up to <?= BULK_MAX_ROWS ?> URLs at once.
            </p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- =====================================================
         Results (shown after successful processing)
         ===================================================== -->
    <?php if ($summary !== null): ?>
        <section class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Results</h3>
                <div class="flex gap-2">
                    <span class="badge badge-success"><?= (int) $summary['ok'] ?> created</span>
                    <?php if ($summary['errors'] > 0): ?>
                        <span class="badge badge-danger"><?= (int) $summary['errors'] ?> failed</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Original URL</th>
                            <th>Short URL</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td class="text-muted text-sm"><?= (int) $r['line'] ?></td>
                                <td class="cell-url">
                                    <span class="truncate" title="<?= e($r['url']) ?>">
                                        <?= e($r['url']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($r['short'] !== null): ?>
                                        <div class="input-group" style="max-width: 320px;">
                                            <input
                                                type="text"
                                                class="form-input font-mono"
                                                value="<?= e($r['short']) ?>"
                                                readonly
                                                id="short-<?= (int) $r['line'] ?>"
                                            >
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm"
                                                data-copy="true"
                                                data-copy-target="#short-<?= (int) $r['line'] ?>"
                                                data-copy-label="✓"
                                            >
                                                Copy
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted text-sm">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'ok'): ?>
                                        <span class="badge badge-success">Created</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" title="<?= e($r['message']) ?>">
                                            <?= e($r['message']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex gap-2 mt-6" style="flex-wrap: wrap;">
                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="downloadResultsCsv()"
                >
                    ⬇ Download results as CSV
                </button>
                <a href="<?= e(APP_URL) ?>/dashboard.php" class="btn btn-secondary">
                    Go to dashboard
                </a>
                <a href="<?= e(APP_URL) ?>/bulk.php" class="btn btn-secondary">
                    Upload another file
                </a>
            </div>
        </section>

        <!-- Hidden JSON for CSV download -->
        <script type="application/json" id="bulk-results">
        <?= json_encode(array_map(fn($r) => [
            'line'   => $r['line'],
            'url'    => $r['url'],
            'short'  => $r['short'],
            'status' => $r['status'],
            'message'=> $r['message'],
        ], $results), JSON_UNESCAPED_SLASHES) ?>
        </script>
    <?php endif; ?>

    <!-- =====================================================
         Upload form
         ===================================================== -->
    <section class="grid grid-2">
        <!-- Form card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Upload CSV</h3>
            </div>

            <form method="POST" enctype="multipart/form-data" id="bulk-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="csv" class="form-label">CSV file</label>
                    <input
                        type="file"
                        id="csv"
                        name="csv"
                        class="form-input"
                        accept=".csv,text/csv"
                        required
                    >
                    <span class="form-hint">
                        Max <?= BULK_MAX_ROWS ?> rows, max 1 MB.
                    </span>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Process file →
                </button>
            </form>
        </div>

        <!-- Instructions card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">CSV format</h3>
                <button
                    type="button"
                    class="btn btn-secondary btn-sm"
                    onclick="downloadTemplate()"
                >
                    ⬇ Template
                </button>
            </div>

            <p class="text-sm text-muted">
                The file must have a header row. Column 2 and 3 are optional.
            </p>

            <div class="code-block">
<pre>url,custom_alias,expiry_days
https://example.com/a,,30
https://example.com/b,promo-b,7
https://example.com/c,,</pre>
            </div>

            <ul class="detail-list mt-4">
                <li>
                    <span class="text-muted">Column 1</span>
                    <span>URL <span class="text-danger">*</span></span>
                </li>
                <li>
                    <span class="text-muted">Column 2</span>
                    <span>Custom alias (optional)</span>
                </li>
                <li>
                    <span class="text-muted">Column 3</span>
                    <span>Expiry in days (optional)</span>
                </li>
            </ul>
        </div>
    </section>

</div>

<!-- =====================================================
     Client-side CSV download logic
     ===================================================== -->
<script>
function downloadResultsCsv() {
    const el = document.getElementById('bulk-results');
    if (!el) return;

    let data;
    try {
        data = JSON.parse(el.textContent.trim());
    } catch (err) {
        alert('Could not prepare download.');
        return;
    }

    const header = ['line', 'original_url', 'short_url', 'status', 'message'];
    const rows = [header];

    data.forEach(r => {
        rows.push([
            r.line,
            r.url,
            r.short || '',
            r.status,
            r.message || '',
        ]);
    });

    const csv = rows.map(row =>
        row.map(cell => {
            const s = String(cell ?? '');
            // Escape double quotes and wrap if needed
            return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        }).join(',')
    ).join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'shortlink-bulk-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);

    if (window.showToast) window.showToast('CSV downloaded', 'success');
}

function downloadTemplate() {
    const csv = 'url,custom_alias,expiry_days\nhttps://example.com/a,,30\nhttps://example.com/b,promo-b,7\nhttps://example.com/c,,\n';
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'shortlink-template.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);

    if (window.showToast) window.showToast('Template downloaded', 'success');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>