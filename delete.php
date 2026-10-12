<?php
/**
 * =====================================================
 * ShortLink Pro — Delete Link
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Hard-deletes a link from the database.
 * Uses POST + CSRF token for safety.
 * Cascade delete handles clicks, variants, bio links.
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// -----------------------------------------------------
// 1. ONLY ACCEPT POST
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

// -----------------------------------------------------
// 2. VERIFY CSRF TOKEN
// -----------------------------------------------------
if (!verify_csrf()) {
    http_response_code(403);
    redirect('dashboard.php?flash=csrf_failed');
}

// -----------------------------------------------------
// 3. READ THE CODE
// -----------------------------------------------------
$code = trim((string) ($_POST['c'] ?? ''));

if ($code === '') {
    redirect('dashboard.php?flash=missing_code');
}

// -----------------------------------------------------
// 4. LOOK UP THE LINK
// -----------------------------------------------------
$link = find_link_by_code($code);

if (!$link) {
    redirect('dashboard.php?flash=not_found');
}

// -----------------------------------------------------
// 5. DELETE THE LINK
// -----------------------------------------------------
try {
    $deleted = delete_link((int) $link['id']);
} catch (Throwable $e) {
    error_log('[DELETE ERROR] ' . $e->getMessage());
    redirect('dashboard.php?flash=delete_failed');
}

if (!$deleted) {
    redirect('dashboard.php?flash=delete_failed');
}

// -----------------------------------------------------
// 6. REDIRECT BACK
// -----------------------------------------------------
redirect('dashboard.php?flash=deleted');