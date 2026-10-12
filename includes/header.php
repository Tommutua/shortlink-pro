<?php
/**
 * =====================================================
 * ShortLink Pro — Shared Page Header
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Renders: <!DOCTYPE>, <head>, navigation bar, opens <main>
 *
 * Usage in any page:
 *   $pageTitle = 'My Page';          // optional
 *   require __DIR__ . '/includes/header.php';
 * =====================================================
 */

// Safety: this file should never be loaded directly
if (!defined('APP_NAME')) {
    http_response_code(500);
    exit('header.php must be included after config.php.');
}

// Default page title if not set by the calling page
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ShortLink Pro — a fast, modern URL shortener with analytics, QR codes, and link intelligence.">
    <meta name="author" content="Smart Holla Tech">

    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">

    <!-- Favicon (optional, using emoji fallback) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔗</text></svg>">
</head>
<body>

<!-- =====================================================
     Navigation Bar
     ===================================================== -->
<header class="site-header">
    <nav class="nav container">
        <a href="<?= e(APP_URL) ?>/" class="nav-logo">
            <span class="logo-icon">🔗</span>
            <span class="logo-text"><?= e(APP_NAME) ?></span>
        </a>

        <ul class="nav-links">
            <li><a href="<?= e(APP_URL) ?>/">Shorten</a></li>
            <li><a href="<?= e(APP_URL) ?>/dashboard.php">Dashboard</a></li>
            <li><a href="<?= e(APP_URL) ?>/bulk.php">Bulk</a></li>
            <li><a href="<?= e(APP_URL) ?>/bio.php?edit=1">Bio</a></li>
        </ul>

        <button
            type="button"
            class="theme-toggle"
            id="theme-toggle"
            aria-label="Toggle dark mode"
            title="Toggle dark mode"
        >
            <span class="theme-icon-light">🌙</span>
            <span class="theme-icon-dark">☀️</span>
        </button>
    </nav>
</header>

<!-- =====================================================
     Main Content (each page fills this in)
     ===================================================== -->
<main class="main container">