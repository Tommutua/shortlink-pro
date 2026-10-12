-- =====================================================
-- ShortLink Pro — Database Schema
-- Built by Smart Holla Tech
-- =====================================================
-- Each table is created with its columns, indexes,
-- and foreign keys in a single CREATE statement.
-- No data. No phpMyAdmin export format.
-- =====================================================

CREATE TABLE links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_code VARCHAR(16) UNIQUE NOT NULL,
    long_url TEXT NOT NULL,
    custom_alias VARCHAR(32) UNIQUE NULL,
    password_hash VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    max_clicks INT UNSIGNED NULL,
    click_count INT UNSIGNED DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    mobile_url TEXT NULL,
    desktop_url TEXT NULL,
    ios_url TEXT NULL,
    android_url TEXT NULL,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_short_code (short_code),
    INDEX idx_alias (custom_alias),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    link_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NULL,
    country CHAR(2) NULL,
    city VARCHAR(64) NULL,
    user_agent TEXT NULL,
    device_type ENUM('desktop','mobile','tablet','bot','unknown') DEFAULT 'unknown',
    browser VARCHAR(32) NULL,
    os VARCHAR(32) NULL,
    referrer VARCHAR(255) NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_link (link_id),
    INDEX idx_clicked (clicked_at),
    INDEX idx_country (country),
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE geo_cache (
    ip_address VARCHAR(45) PRIMARY KEY,
    country CHAR(2) NOT NULL,
    city VARCHAR(64) NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cached (cached_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    identifier VARCHAR(64) PRIMARY KEY,
    hits INT UNSIGNED DEFAULT 0,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bio_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(32) UNIQUE NOT NULL,
    title VARCHAR(64) NULL,
    bio TEXT NULL,
    avatar_url VARCHAR(255) NULL,
    theme VARCHAR(32) DEFAULT 'default',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bio_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bio_page_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(64) NOT NULL,
    url TEXT NOT NULL,
    icon VARCHAR(32) NULL,
    position INT DEFAULT 0,
    click_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bio_page (bio_page_id),
    FOREIGN KEY (bio_page_id) REFERENCES bio_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ab_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    link_id BIGINT UNSIGNED NOT NULL,
    destination_url TEXT NOT NULL,
    label VARCHAR(32) NULL,
    weight INT UNSIGNED DEFAULT 50,
    click_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_link (link_id),
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;