<?php
/**
 * =====================================================
 * ShortLink Pro — QR Code Generator (Raw PNG endpoint)
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Generates a branded QR code PNG for a short link.
 * Uses endroid/qr-code library when available.
 * Falls back to api.qrserver.com if vendor/ is missing.
 *
 * Usage:
 *   /qr.php?c=abc123            → 300px PNG
 *   /qr.php?c=abc123&size=500   → 500px PNG
 *   /qr.php?c=abc123&download=1 → forces download
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// -----------------------------------------------------
// 1. READ THE CODE
// -----------------------------------------------------
$code = trim((string) ($_GET['c'] ?? ''));

if ($code === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Missing code parameter.');
}

// -----------------------------------------------------
// 2. LOOK UP THE LINK
// -----------------------------------------------------
$link = find_link_by_code($code);

if (!$link) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Link not found.');
}

// -----------------------------------------------------
// 3. OPTIONS
// -----------------------------------------------------
$size     = max(150, min(1000, (int) ($_GET['size'] ?? 300)));
$download = isset($_GET['download']) && $_GET['download'] === '1';
$shortUrl = short_url($link['short_code']);

// -----------------------------------------------------
// 4. GENERATE THE QR CODE
// -----------------------------------------------------
$autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload)) {
    // ---------------------------------------------
    // PRIMARY PATH: local library (branded, cached, fast)
    // ---------------------------------------------
    require_once $autoload;

    try {
        $qr = new \Endroid\QrCode\QrCode($shortUrl);
        $qr->setSize($size);
        $qr->setMargin(10);
        $qr->setForegroundColor(new \Endroid\QrCode\Color\Color(99, 102, 241));
        $qr->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));

        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qr);

        header('Content-Type: ' . $result->getMimeType());
        header('Content-Length: ' . strlen($result->getString()));
        header('Cache-Control: public, max-age=86400');

        if ($download) {
            header('Content-Disposition: attachment; filename="shortlink-' . $link['short_code'] . '.png"');
        }

        echo $result->getString();
        exit;

    } catch (Throwable $e) {
        error_log('[QR LIBRARY ERROR] ' . $e->getMessage());
        // Fall through to external API fallback
    }
}

// -----------------------------------------------------
// 5. FALLBACK: external API
// -----------------------------------------------------
$apiUrl = 'https://api.qrserver.com/v1/create-qr-code/'
        . '?size=' . $size . 'x' . $size
        . '&data=' . urlencode($shortUrl)
        . '&color=6366f1'
        . '&bgcolor=ffffff';

header('Location: ' . $apiUrl, true, 302);
exit;