<?php
/**
 * =====================================================
 * ShortLink Pro — Helper Functions
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Organized by purpose:
 *   1. BASE62 ENCODING
 *   2. URL VALIDATION
 *   3. LINK OPERATIONS
 *   4. UTILITIES (escape, redirect, json)
 *   5. ALIAS CHECKS
 *
 * Requires: config.php + db.php (for functions that touch the DB)
 * =====================================================
 */

declare(strict_types=1);

// =====================================================
// 1. BASE62 ENCODING
// =====================================================

/**
 * The Base62 alphabet.
 * Order matters — encode() and decode() must use the same order.
 */
const BASE62_ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

/**
 * Encode a positive integer to a Base62 string.
 *
 * Example: 12345 → "3d7"
 */
function base62_encode(int $num): string
{
    if ($num < 0) {
        throw new InvalidArgumentException('Base62 cannot encode negative numbers.');
    }

    if ($num === 0) {
        return BASE62_ALPHABET[0]; // "0"
    }

    $result = '';
    $base = strlen(BASE62_ALPHABET);

    while ($num > 0) {
        $remainder = $num % $base;
        $result = BASE62_ALPHABET[$remainder] . $result;
        $num = intdiv($num, $base);
    }

    return $result;
}

/**
 * Decode a Base62 string back to an integer.
 *
 * Example: "3d7" → 12345
 */
function base62_decode(string $code): int
{
    $base = strlen(BASE62_ALPHABET);
    $num = 0;
    $len = strlen($code);

    for ($i = 0; $i < $len; $i++) {
        $pos = strpos(BASE62_ALPHABET, $code[$i]);

        if ($pos === false) {
            throw new InvalidArgumentException("Invalid Base62 character: {$code[$i]}");
        }

        $num = $num * $base + $pos;
    }

    return $num;
}

/**
 * Generate an obfuscated short code from a link ID.
 *
 * We multiply and offset the ID so that /1 doesn't produce "1".
 * The transformation is reversible so we can still look up by code.
 */
function generate_code(int $id): string
{
    $obfuscated = $id * BASE62_MULTIPLIER + BASE62_OFFSET;
    return base62_encode($obfuscated);
}

/**
 * Reverse of generate_code — turn a short code back into the original ID.
 * Useful for debugging or admin tools.
 */
function decode_code(string $code): int
{
    $obfuscated = base62_decode($code);
    return intdiv($obfuscated - BASE62_OFFSET, BASE62_MULTIPLIER);
}

// =====================================================
// 2. URL VALIDATION
// =====================================================

/**
 * Validate that a URL is well-formed AND safe to shorten.
 * Returns true if valid, false otherwise.
 */
function validate_url(string $url): bool
{
    $url = trim($url);

    if ($url === '') {
        return false;
    }

    // Must be a valid URL syntactically
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    // Must have http or https scheme
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    // Must pass safety checks (no localhost, no private IPs, etc.)
    if (!is_safe_url($url)) {
        return false;
    }

    return true;
}

/**
 * Reject URLs that point to internal/private resources.
 * This prevents SSRF (Server-Side Request Forgery) attacks.
 */
function is_safe_url(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if ($host === '') {
        return false;
    }

    // Block common local hostnames
    $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
    if (in_array($host, $blockedHosts, true)) {
        return false;
    }

    // Block .local and .internal TLDs
    if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
        return false;
    }

    // If host is an IP, block private ranges
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }

    return true;
}

// =====================================================
// 3. LINK OPERATIONS (database)
// =====================================================

/**
 * Create a new short link. Returns the inserted ID.
 *
 * Accepts an associative array with keys:
 *   - long_url      (required)
 *   - custom_alias  (optional)
 *   - password_hash (optional)
 *   - expires_at    (optional, DATETIME string)
 *   - max_clicks    (optional, int)
 */
function create_link(array $data): int
{
    global $pdo;

    if (empty($data['long_url'])) {
        throw new InvalidArgumentException('long_url is required.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO links (short_code, long_url, custom_alias, password_hash, expires_at, max_clicks)
        VALUES (:short_code, :long_url, :custom_alias, :password_hash, :expires_at, :max_clicks)
    ");

    $stmt->execute([
        ':short_code'    => '', // placeholder, filled in below
        ':long_url'      => $data['long_url'],
        ':custom_alias'  => $data['custom_alias']  ?? null,
        ':password_hash' => $data['password_hash'] ?? null,
        ':expires_at'    => $data['expires_at']    ?? null,
        ':max_clicks'    => $data['max_clicks']    ?? null,
    ]);

    $id = (int) $pdo->lastInsertId();

    // Determine the final short code:
    //   - Use custom alias if provided
    //   - Otherwise generate obfuscated Base62 from ID
    $code = !empty($data['custom_alias'])
        ? $data['custom_alias']
        : generate_code($id);

    $update = $pdo->prepare("UPDATE links SET short_code = :code WHERE id = :id");
    $update->execute([':code' => $code, ':id' => $id]);

    return $id;
}

/**
 * Find a link by its short code or custom alias. Returns array or null.
 *
 * NOTE: We use two distinct placeholders (:code1 and :code2) because
 * PDO with native prepares (EMULATE_PREPARES = false) does not allow
 * the same named placeholder to appear twice in a query.
 */
function find_link_by_code(string $code): ?array
{
    global $pdo;

    if ($code === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM links
        WHERE short_code = :code1 OR custom_alias = :code2
        LIMIT 1
    ");
    $stmt->execute([
        ':code1' => $code,
        ':code2' => $code,
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Increment click counter on a link.
 */
function increment_clicks(int $linkId): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE links SET click_count = click_count + 1 WHERE id = :id
    ");
    $stmt->execute([':id' => $linkId]);
}

/**
 * Fetch all links, newest first (for the dashboard).
 */
function get_all_links(int $limit = 100): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM links ORDER BY created_at DESC LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

// =====================================================
// 4. UTILITIES
// =====================================================

/**
 * Escape a string for safe HTML output. Prevents XSS.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect the browser and stop execution.
 * CRITICAL: always exit after sending the Location header.
 */
function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get the client's IP address (with proxy awareness).
 */
function client_ip(): string
{
    // If behind a trusted proxy, this header may be set
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get the full short URL for a given code.
 */
function short_url(string $code): string
{
    return APP_URL . '/' . $code;
}

// =====================================================
// 5. ALIAS CHECKS
// =====================================================

/**
 * Is the given string a reserved word (used by our own routes)?
 */
function is_reserved_word(string $alias): bool
{
    return in_array(strtolower($alias), RESERVED_WORDS, true);
}

/**
 * Validate a custom alias. Returns true if acceptable.
 */
function is_valid_alias(string $alias): bool
{
    $len = strlen($alias);

    if ($len < ALIAS_MIN_LENGTH || $len > ALIAS_MAX_LENGTH) {
        return false;
    }

    if (!preg_match(ALIAS_PATTERN, $alias)) {
        return false;
    }

    if (is_reserved_word($alias)) {
        return false;
    }

    return true;
}

/**
 * Is a short code or alias already taken? (case-insensitive)
 *
 * NOTE: Same reason as find_link_by_code — two distinct placeholders
 * because we run with native prepared statements.
 */
function alias_exists(string $alias): bool
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 1 FROM links
        WHERE LOWER(short_code) = LOWER(:a1)
           OR LOWER(custom_alias) = LOWER(:a2)
        LIMIT 1
    ");
    $stmt->execute([
        ':a1' => $alias,
        ':a2' => $alias,
    ]);

    return (bool) $stmt->fetchColumn();
}
// =====================================================
// 6. RATE LIMITING
// =====================================================

/**
 * Rate-limit link creation per IP per hour.
 * Returns true if allowed, false if the limit is exceeded.
 */
function rate_limit_links(string $ip): bool
{
    global $pdo;

    $max    = RATE_LIMIT_LINKS_PER_HOUR;
    $window = 3600; // 1 hour in seconds
    $key    = 'link:' . $ip;
    $now    = time();

    // Find existing window
    $stmt = $pdo->prepare("
        SELECT hits, UNIX_TIMESTAMP(window_start) AS started
        FROM rate_limits
        WHERE identifier = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $key]);
    $row = $stmt->fetch();

    // First hit, or window expired → start a new window
    if (!$row || ($now - (int) $row['started']) > $window) {
        $upsert = $pdo->prepare("
            REPLACE INTO rate_limits (identifier, hits, window_start)
            VALUES (:id, 1, NOW())
        ");
        $upsert->execute([':id' => $key]);
        return true;
    }

    // Over the limit?
    if ((int) $row['hits'] >= $max) {
        return false;
    }

    // Increment
    $inc = $pdo->prepare("
        UPDATE rate_limits SET hits = hits + 1 WHERE identifier = :id
    ");
    $inc->execute([':id' => $key]);

    return true;
}
/**
 * Log a click on a link, including geo data if available.
 *
 * @param int   $linkId  The link's DB id.
 * @param array $meta    Keys: ip, user_agent, referrer, country, city.
 */
function log_click(int $linkId, array $meta): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO clicks (link_id, ip_address, country, city, user_agent, referrer)
        VALUES (:link_id, :ip, :country, :city, :ua, :ref)
    ");

    $stmt->execute([
        ':link_id' => $linkId,
        ':ip'      => $meta['ip']         ?? null,
        ':country' => $meta['country']    ?? null,
        ':city'    => $meta['city']       ?? null,
        ':ua'      => $meta['user_agent'] ?? null,
        ':ref'     => $meta['referrer']   ?? null,
    ]);
}
/**
 * Rate-limit password attempts per code per IP.
 * Returns true if allowed, false if the limit is exceeded.
 */
function rate_limit_password(string $code, string $ip): bool
{
    global $pdo;

    $max    = RATE_LIMIT_PASSWORD_ATTEMPTS; // from config.php, default 5
    $window = 900;                           // 15 minutes
    $key    = 'pw:' . $code . ':' . $ip;
    $now    = time();

    $stmt = $pdo->prepare("
        SELECT hits, UNIX_TIMESTAMP(window_start) AS started
        FROM rate_limits
        WHERE identifier = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $key]);
    $row = $stmt->fetch();

    if (!$row || ($now - (int) $row['started']) > $window) {
        $upsert = $pdo->prepare("
            REPLACE INTO rate_limits (identifier, hits, window_start)
            VALUES (:id, 1, NOW())
        ");
        $upsert->execute([':id' => $key]);
        return true;
    }

    if ((int) $row['hits'] >= $max) {
        return false;
    }

    $inc = $pdo->prepare("
        UPDATE rate_limits SET hits = hits + 1 WHERE identifier = :id
    ");
    $inc->execute([':id' => $key]);

    return true;
}
// =====================================================
// 3b. ANALYTICS HELPERS
// =====================================================

/**
 * Total number of links in the system.
 */
function count_links(): int
{
    global $pdo;
    return (int) $pdo->query("SELECT COUNT(*) FROM links")->fetchColumn();
}

/**
 * Total number of clicks logged.
 */
function count_clicks(): int
{
    global $pdo;
    return (int) $pdo->query("SELECT COUNT(*) FROM clicks")->fetchColumn();
}

/**
 * Clicks per day for the last N days.
 * Returns an array of ['day' => 'YYYY-MM-DD', 'total' => int]
 * in ascending date order, with zero-filled days.
 */
function clicks_by_day(int $days = 7): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT DATE(clicked_at) AS day, COUNT(*) AS total
        FROM clicks
        WHERE clicked_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(clicked_at)
        ORDER BY day ASC
    ");
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Index by day for quick lookup
    $byDay = [];
    foreach ($rows as $row) {
        $byDay[$row['day']] = (int) $row['total'];
    }

    // Zero-fill missing days
    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $result[] = [
            'day'   => $date,
            'total' => $byDay[$date] ?? 0,
        ];
    }

    return $result;
}

/**
 * Top N links by click count.
 */
function top_links(int $limit = 5): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT id, short_code, long_url, click_count
        FROM links
        ORDER BY click_count DESC, created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Top N countries by click count.
 */
function top_countries(int $limit = 5): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT country, COUNT(*) AS total
        FROM clicks
        WHERE country IS NOT NULL AND country <> ''
        GROUP BY country
        ORDER BY total DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Device breakdown (mobile / desktop / tablet / bot).
 */
function device_breakdown(): array
{
    global $pdo;

    return $pdo->query("
        SELECT device_type, COUNT(*) AS total
        FROM clicks
        WHERE device_type IS NOT NULL
        GROUP BY device_type
        ORDER BY total DESC
    ")->fetchAll();
}

/**
 * Delete a link and all its related data.
 * The foreign key ON DELETE CASCADE handles clicks and variants automatically.
 */
function delete_link(int $id): bool
{
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM links WHERE id = :id");
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}
// =====================================================
// 7. CSRF PROTECTION
// =====================================================

/**
 * Get (or generate) the current session's CSRF token.
 * Safe to call multiple times — same token is reused per session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token from a POSTed form.
 * Uses timing-safe comparison.
 */
function verify_csrf(): bool
{
    $submitted = $_POST['csrf'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if ($submitted === '' || $expected === '') {
        return false;
    }

    return hash_equals($expected, (string) $submitted);
}

/**
 * Output a hidden CSRF input field for forms.
 * Usage:  <?= csrf_field() ?>
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="'
         . e(csrf_token())
         . '">';
}
/**
 * Clicks per day for a specific link, zero-filled for the last N days.
 */
function clicks_by_day_for_link(int $linkId, int $days = 30): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT DATE(clicked_at) AS day, COUNT(*) AS total
        FROM clicks
        WHERE link_id = :link_id
          AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(clicked_at)
        ORDER BY day ASC
    ");
    $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $byDay = [];
    foreach ($rows as $row) {
        $byDay[$row['day']] = (int) $row['total'];
    }

    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $result[] = [
            'day'   => $date,
            'total' => $byDay[$date] ?? 0,
        ];
    }

    return $result;
}

/**
 * Top countries for a specific link.
 */
function countries_for_link(int $linkId, int $limit = 6): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT country, COUNT(*) AS total
        FROM clicks
        WHERE link_id = :link_id
          AND country IS NOT NULL
          AND country <> ''
        GROUP BY country
        ORDER BY total DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Device breakdown for a specific link.
 */
function devices_for_link(int $linkId): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN device_type IS NULL OR device_type = '' THEN 'unknown'
                ELSE device_type
            END AS device_type,
            COUNT(*) AS total
        FROM clicks
        WHERE link_id = :link_id
        GROUP BY device_type
        ORDER BY total DESC
    ");
    $stmt->execute([':link_id' => $linkId]);

    return $stmt->fetchAll();
}

/**
 * Top referrers for a specific link.
 * Empty referrers show as "(direct)".
 */
function referrers_for_link(int $linkId, int $limit = 8): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN referrer IS NULL OR referrer = '' THEN '(direct)'
                ELSE referrer
            END AS source,
            COUNT(*) AS total
        FROM clicks
        WHERE link_id = :link_id
        GROUP BY source
        ORDER BY total DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Recent clicks for a link (privacy-safe: no IP, no user-agent).
 */
function recent_clicks_for_link(int $linkId, int $limit = 15): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            country,
            city,
            device_type,
            CASE
                WHEN referrer IS NULL OR referrer = '' THEN '(direct)'
                ELSE referrer
            END AS source,
            clicked_at
        FROM clicks
        WHERE link_id = :link_id
        ORDER BY clicked_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Count unique countries for a link.
 */
function unique_countries_for_link(int $linkId): int
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT country)
        FROM clicks
        WHERE link_id = :link_id
          AND country IS NOT NULL
          AND country <> ''
    ");
    $stmt->execute([':link_id' => $linkId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Clicks in the last N days (default 7).
 */
function clicks_in_last_days(int $linkId, int $days = 7): int
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM clicks
        WHERE link_id = :link_id
          AND clicked_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}
// =====================================================
// 8. BULK CSV PROCESSING
// =====================================================

/**
 * Parse an uploaded CSV file into an array of rows.
 * Returns rows as indexed arrays. Skips empty lines.
 */
function parse_csv_file(string $path): array
{
    $rows = [];

    if (!is_readable($path)) {
        return $rows;
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return $rows;
    }

    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        // Skip completely empty lines
        if (count($row) === 1 && trim((string) $row[0]) === '') {
            continue;
        }
        $rows[] = array_map('trim', $row);
    }

    fclose($handle);

    // Drop header row if it looks like one (first cell is "url" case-insensitive)
    if (!empty($rows) && strtolower($rows[0][0] ?? '') === 'url') {
        array_shift($rows);
    }

    return $rows;
}

/**
 * Process bulk rows: validate + create a link for each.
 * Returns an array of result records:
 *   ['line' => int, 'url' => string, 'status' => 'ok'|'error',
 *    'message' => string, 'short' => ?string]
 */
function process_bulk_rows(array $rows): array
{
    $results = [];
    $lineNo  = 0;

    foreach ($rows as $row) {
        $lineNo++;

        $url        = trim((string) ($row[0] ?? ''));
        $alias      = trim((string) ($row[1] ?? ''));
        $expiryDays = trim((string) ($row[2] ?? ''));

        // Guard: URL required
        if ($url === '') {
            $results[] = [
                'line'    => $lineNo,
                'url'     => '(empty)',
                'status'  => 'error',
                'message' => 'Missing URL',
                'short'   => null,
            ];
            continue;
        }

        // Validate URL
        if (!validate_url($url)) {
            $results[] = [
                'line'    => $lineNo,
                'url'     => $url,
                'status'  => 'error',
                'message' => 'Invalid URL',
                'short'   => null,
            ];
            continue;
        }

        // Rate limit — one slot per row
        if (!rate_limit_links(client_ip())) {
            $results[] = [
                'line'    => $lineNo,
                'url'     => $url,
                'status'  => 'error',
                'message' => 'Rate limit reached',
                'short'   => null,
            ];
            continue;
        }

        // Validate custom alias (if provided)
        $customAlias = null;
        if ($alias !== '') {
            if (!is_valid_alias($alias)) {
                $results[] = [
                    'line'    => $lineNo,
                    'url'     => $url,
                    'status'  => 'error',
                    'message' => is_reserved_word($alias) ? 'Reserved alias' : 'Invalid alias',
                    'short'   => null,
                ];
                continue;
            }
            if (alias_exists($alias)) {
                $results[] = [
                    'line'    => $lineNo,
                    'url'     => $url,
                    'status'  => 'error',
                    'message' => 'Alias taken',
                    'short'   => null,
                ];
                continue;
            }
            $customAlias = $alias;
        }

        // Compute expiry
        $expiresAt = null;
        if ($expiryDays !== '' && is_numeric($expiryDays)) {
            $days = max(1, min(3650, (int) $expiryDays)); // clamp 1–3650 days
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        }

        // Create the link
        try {
            $id = create_link([
                'long_url'     => $url,
                'custom_alias' => $customAlias,
                'expires_at'   => $expiresAt,
            ]);

            $code = $customAlias !== null ? $customAlias : generate_code($id);

            $results[] = [
                'line'    => $lineNo,
                'url'     => $url,
                'status'  => 'ok',
                'message' => 'Created',
                'short'   => short_url($code),
            ];
        } catch (Throwable $e) {
            error_log('[BULK ERROR] Line ' . $lineNo . ': ' . $e->getMessage());
            $results[] = [
                'line'    => $lineNo,
                'url'     => $url,
                'status'  => 'error',
                'message' => 'Creation failed',
                'short'   => null,
            ];
        }
    }

    return $results;
}
// =====================================================
// 9. BIO PAGES
// =====================================================

/**
 * Get the bio page by slug. Returns array or null.
 */
function get_bio_page(string $slug): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM bio_pages WHERE slug = :slug LIMIT 1
    ");
    $stmt->execute([':slug' => $slug]);

    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get the bio page by ID.
 */
function get_bio_page_by_id(int $id): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM bio_pages WHERE id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get all links for a bio page, ordered by position.
 */
function get_bio_links(int $bioPageId): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM bio_links
        WHERE bio_page_id = :id
        ORDER BY position ASC, id ASC
    ");
    $stmt->execute([':id' => $bioPageId]);

    return $stmt->fetchAll();
}

/**
 * Get or create the default bio page. Returns the row.
 * Single-tenant model: there's one bio page per installation.
 */
function get_or_create_default_bio_page(): array
{
    global $pdo;

    $slug = 'me';
    $existing = get_bio_page($slug);

    if ($existing) {
        return $existing;
    }

    $stmt = $pdo->prepare("
        INSERT INTO bio_pages (slug, title, bio, theme)
        VALUES (:slug, :title, :bio, :theme)
    ");
    $stmt->execute([
        ':slug'  => $slug,
        ':title' => 'My Links',
        ':bio'   => 'Welcome! Here are my links.',
        ':theme' => 'default',
    ]);

    $id = (int) $pdo->lastInsertId();
    return get_bio_page_by_id($id);
}

/**
 * Save (update) a bio page.
 */
function update_bio_page(int $id, array $data): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE bio_pages
        SET title = :title, bio = :bio, theme = :theme, avatar_url = :avatar
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'     => $id,
        ':title'  => $data['title']      ?? null,
        ':bio'    => $data['bio']        ?? null,
        ':theme'  => $data['theme']      ?? 'default',
        ':avatar' => $data['avatar_url'] ?? null,
    ]);
}

/**
 * Add a link to a bio page. Returns the new ID.
 */
function add_bio_link(int $bioPageId, string $label, string $url, ?string $icon = null): int
{
    global $pdo;

    // Get next position
    $posStmt = $pdo->prepare("
        SELECT COALESCE(MAX(position), -1) + 1 AS next_pos
        FROM bio_links WHERE bio_page_id = :id
    ");
    $posStmt->execute([':id' => $bioPageId]);
    $nextPos = (int) $posStmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO bio_links (bio_page_id, label, url, icon, position)
        VALUES (:page_id, :label, :url, :icon, :pos)
    ");
    $stmt->execute([
        ':page_id' => $bioPageId,
        ':label'   => $label,
        ':url'     => $url,
        ':icon'    => $icon,
        ':pos'     => $nextPos,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Delete a bio link.
 */
function delete_bio_link(int $id, int $bioPageId): bool
{
    global $pdo;

    $stmt = $pdo->prepare("
        DELETE FROM bio_links
        WHERE id = :id AND bio_page_id = :page_id
    ");
    $stmt->execute([':id' => $id, ':page_id' => $bioPageId]);

    return $stmt->rowCount() > 0;
}

/**
 * Is a bio link ID valid and owned by this page?
 */
function get_bio_link(int $id, int $bioPageId): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM bio_links
        WHERE id = :id AND bio_page_id = :page_id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id, ':page_id' => $bioPageId]);

    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Toggle a bio link's visibility (we'll add this later if time).
 * For now, skip.
 */
/**
 * Move a bio link up or down by swapping positions with its neighbor.
 */
function move_bio_link(int $linkId, int $bioPageId, string $direction): bool
{
    global $pdo;

    $link = get_bio_link($linkId, $bioPageId);
    if (!$link) {
        return false;
    }

    $currentPos = (int) $link['position'];

    if ($direction === 'up') {
        $neighbor = $pdo->prepare("
            SELECT * FROM bio_links
            WHERE bio_page_id = :page_id AND position < :pos
            ORDER BY position DESC LIMIT 1
        ");
    } else {
        $neighbor = $pdo->prepare("
            SELECT * FROM bio_links
            WHERE bio_page_id = :page_id AND position > :pos
            ORDER BY position ASC LIMIT 1
        ");
    }

    $neighbor->execute([
        ':page_id' => $bioPageId,
        ':pos'     => $currentPos,
    ]);

    $other = $neighbor->fetch();
    if (!$other) {
        return false; // nothing to swap with
    }

    // Swap positions
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("
            UPDATE bio_links SET position = :pos WHERE id = :id
        ");
        $upd->execute([
            ':pos' => (int) $other['position'],
            ':id'  => $linkId,
        ]);
        $upd->execute([
            ':pos' => $currentPos,
            ':id'  => (int) $other['id'],
        ]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[BIO MOVE ERROR] ' . $e->getMessage());
        return false;
    }
}
// =====================================================
// 10. A/B TESTING
// =====================================================

/**
 * Get all variants for a link.
 */
function get_variants(int $linkId): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM ab_variants
        WHERE link_id = :link_id
        ORDER BY id ASC
    ");
    $stmt->execute([':link_id' => $linkId]);

    return $stmt->fetchAll();
}

/**
 * Add a variant to a link.
 */
function add_variant(int $linkId, string $destinationUrl, string $label = '', int $weight = 50): int
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO ab_variants (link_id, destination_url, label, weight)
        VALUES (:link_id, :url, :label, :weight)
    ");
    $stmt->execute([
        ':link_id' => $linkId,
        ':url'     => $destinationUrl,
        ':label'   => $label,
        ':weight'  => max(1, $weight), // weight must be >= 1
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Pick a variant based on weight. Returns a variant row or null.
 * Uses weighted random selection.
 */
function pick_variant(array $variants): ?array
{
    if (empty($variants)) {
        return null;
    }

    // Total weight (default 50 if not set)
    $totalWeight = 0;
    foreach ($variants as $v) {
        $totalWeight += max(1, (int) ($v['weight'] ?? 50));
    }

    // Roll a random number between 1 and totalWeight
    $roll = random_int(1, $totalWeight);

    // Walk through variants, subtracting weight until we hit the roll
    $cumulative = 0;
    foreach ($variants as $v) {
        $cumulative += max(1, (int) ($v['weight'] ?? 50));
        if ($roll <= $cumulative) {
            return $v;
        }
    }

    // Fallback (shouldn't happen)
    return $variants[0];
}

/**
 * Increment a variant's click counter atomically.
 */
function increment_variant(int $variantId): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE ab_variants
        SET click_count = click_count + 1
        WHERE id = :id
    ");
    $stmt->execute([':id' => $variantId]);
}