<?php
/**
 * =====================================================
 * ShortLink Pro — Geo Lookup
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Converts an IP address to country + city, using
 * ip-api.com (free, no key). Results are cached in the
 * geo_cache table so we hit the API once per unique IP.
 *
 * Failure mode: always returns an array with at least
 * 'country' and 'city' keys (may be null). Never throws.
 * =====================================================
 */

declare(strict_types=1);

/**
 * Look up geo data for an IP address. Cached in DB.
 *
 * @param  string $ip  IPv4 or IPv6 address.
 * @return array{country: ?string, city: ?string}
 */
function get_geo(string $ip): array
{
    global $pdo;

    $empty = ['country' => null, 'city' => null];

    // -----------------------------------------------------
    // DEV CONVENIENCE: fake a country for localhost testing
    // -----------------------------------------------------
    // Local IPs (127.0.0.1, ::1, 192.168.x.x, etc.) can't be
    // geolocated — but for a demo, we want to see data. This
    // maps every localhost request to a rotating set of
    // countries so the dashboard looks alive.
    //
    // In production, real visitor IPs bypass this entirely.
    // -----------------------------------------------------
    if (is_local_ip($ip)) {
        $demo = pick_demo_geo($ip);
        return [
            'country' => $demo['country'],
            'city'    => $demo['city'],
        ];
    }

    // -----------------------------------------------------
    // 1. Skip other non-public IPs (private ranges)
    // -----------------------------------------------------
    if (!is_public_ip($ip)) {
        return $empty;
    }

    // -----------------------------------------------------
    // 2. Check the cache first
    // -----------------------------------------------------
    try {
        $stmt = $pdo->prepare("
            SELECT country, city FROM geo_cache
            WHERE ip_address = :ip
            LIMIT 1
        ");
        $stmt->execute([':ip' => $ip]);
        $cached = $stmt->fetch();

        if ($cached) {
            return [
                'country' => $cached['country'] ?: null,
                'city'    => $cached['city']    ?: null,
            ];
        }
    } catch (Throwable $e) {
        error_log('[GEO CACHE READ ERROR] ' . $e->getMessage());
        return $empty;
    }

    // -----------------------------------------------------
    // 3. Not cached — call the API
    // -----------------------------------------------------
    $data = fetch_geo_from_api($ip);

    if ($data === null) {
        return $empty;
    }

    // -----------------------------------------------------
    // 4. Store in cache (even partial results)
    // -----------------------------------------------------
    try {
        $upsert = $pdo->prepare("
            REPLACE INTO geo_cache (ip_address, country, city)
            VALUES (:ip, :country, :city)
        ");
        $upsert->execute([
            ':ip'      => $ip,
            ':country' => $data['country'] ?? 'XX',
            ':city'    => $data['city']    ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('[GEO CACHE WRITE ERROR] ' . $e->getMessage());
    }

    return $data;
}

/**
 * Is this a local/loopback IP? (Used to fake geo in dev.)
 */
function is_local_ip(string $ip): bool
{
    if ($ip === '::1' || $ip === '127.0.0.1') {
        return true;
    }

    // LAN ranges: 192.168.x.x, 10.x.x.x, 172.16–31.x.x
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    return false;
}

/**
 * Return a plausible demo location for localhost clicks.
 * Uses a rotating list so multiple demo clicks don't all
 * come from the same country.
 */
function pick_demo_geo(string $ip): array
{
    // A rotating set of countries for demo purposes.
    // Uses microtime + IP to slightly randomize.
    $demoLocations = [
        ['country' => 'KE', 'city' => 'Nairobi'],
        ['country' => 'US', 'city' => 'San Francisco'],
        ['country' => 'GB', 'city' => 'London'],
        ['country' => 'KE', 'city' => 'Mombasa'],
        ['country' => 'NG', 'city' => 'Lagos'],
        ['country' => 'ZA', 'city' => 'Cape Town'],
        ['country' => 'US', 'city' => 'New York'],
        ['country' => 'IN', 'city' => 'Mumbai'],
        ['country' => 'DE', 'city' => 'Berlin'],
        ['country' => 'KE', 'city' => 'Nairobi'],
    ];

    // Pseudo-random but stable for the same IP + hour,
    // so repeated demo clicks land on the same country.
    $seed = crc32($ip . date('Y-m-d-H'));
    $idx  = $seed % count($demoLocations);

    return $demoLocations[$idx];
}

/**
 * Fetch geo data from ip-api.com for a given IP.
 * Returns null on any failure.
 *
 * @return array{country: ?string, city: ?string}|null
 */
function fetch_geo_from_api(string $ip): ?array
{
    $url = rtrim(GEO_API_URL, '/') . '/' . urlencode($ip);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 3,             // seconds — never block the user
            'method'  => 'GET',
            'ignore_errors' => true,
            'header' => "User-Agent: ShortLinkPro/1.0\r\n",
        ],
    ]);

    // @ silences warnings — we handle false return value
    $response = @file_get_contents($url, false, $ctx);

    if ($response === false || $response === '') {
        return null;
    }

    $json = json_decode($response, true);

    if (!is_array($json) || ($json['status'] ?? '') !== 'success') {
        return null;
    }

    return [
        'country' => isset($json['countryCode']) && strlen($json['countryCode']) === 2
            ? strtoupper($json['countryCode'])
            : null,
        'city'    => isset($json['city']) && $json['city'] !== ''
            ? substr($json['city'], 0, 64)
            : null,
    ];
}

/**
 * Is the given IP a public (routable) address?
 * Private/reserved/loopback IPs have no geo data.
 */
function is_public_ip(string $ip): bool
{
    // Not an IP at all
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    // Block private + reserved ranges
    return (bool) filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}