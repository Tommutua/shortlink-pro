-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 12, 2026 at 01:57 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shortlink`
--

-- --------------------------------------------------------

--
-- Table structure for table `ab_variants`
--

CREATE TABLE `ab_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `link_id` bigint(20) UNSIGNED NOT NULL,
  `destination_url` text NOT NULL,
  `label` varchar(32) DEFAULT NULL,
  `weight` int(10) UNSIGNED DEFAULT 50,
  `click_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bio_links`
--

CREATE TABLE `bio_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bio_page_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(64) NOT NULL,
  `url` text NOT NULL,
  `icon` varchar(32) DEFAULT NULL,
  `position` int(11) DEFAULT 0,
  `click_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bio_pages`
--

CREATE TABLE `bio_pages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(32) NOT NULL,
  `title` varchar(64) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `theme` varchar(32) DEFAULT 'default',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bio_pages`
--

INSERT INTO `bio_pages` (`id`, `slug`, `title`, `bio`, `avatar_url`, `theme`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'me', 'My Links', 'Welcome! Here are my links.', 'https://l2proafrica.com/index.php#item1', 'default', 1, '2026-10-11 21:55:57', '2026-10-11 21:58:08');

-- --------------------------------------------------------

--
-- Table structure for table `clicks`
--

CREATE TABLE `clicks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `link_id` bigint(20) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `country` char(2) DEFAULT NULL,
  `city` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` enum('desktop','mobile','tablet','bot','unknown') DEFAULT 'unknown',
  `browser` varchar(32) DEFAULT NULL,
  `os` varchar(32) DEFAULT NULL,
  `referrer` varchar(255) DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clicks`
--

INSERT INTO `clicks` (`id`, `link_id`, `ip_address`, `country`, `city`, `user_agent`, `device_type`, `browser`, `os`, `referrer`, `clicked_at`) VALUES
(8, 9, '::1', 'NG', 'Lagos', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'unknown', NULL, NULL, 'http://localhost/shortlink-pro/stats.php?c=39rj2', '2026-10-11 21:44:47'),
(9, 10, '::1', 'NG', 'Lagos', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'unknown', NULL, NULL, '', '2026-10-11 21:50:48'),
(10, 11, '::1', 'DE', 'Berlin', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'unknown', NULL, NULL, 'http://localhost/shortlink-pro/password.php?c=essy', '2026-10-11 22:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `geo_cache`
--

CREATE TABLE `geo_cache` (
  `ip_address` varchar(45) NOT NULL,
  `country` char(2) NOT NULL,
  `city` varchar(64) DEFAULT NULL,
  `cached_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `links`
--

CREATE TABLE `links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `short_code` varchar(16) NOT NULL,
  `long_url` text NOT NULL,
  `custom_alias` varchar(32) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `max_clicks` int(10) UNSIGNED DEFAULT NULL,
  `click_count` int(10) UNSIGNED DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `mobile_url` text DEFAULT NULL,
  `desktop_url` text DEFAULT NULL,
  `ios_url` text DEFAULT NULL,
  `android_url` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `links`
--

INSERT INTO `links` (`id`, `short_code`, `long_url`, `custom_alias`, `password_hash`, `expires_at`, `max_clicks`, `click_count`, `is_active`, `mobile_url`, `desktop_url`, `ios_url`, `android_url`, `user_id`, `created_at`, `updated_at`) VALUES
(9, '39rj2', 'https://www.1flex.org/play?id=2288&type=tv&season=2&episode=19&token=LX9Xsru0BO6D4rCF', NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, '2026-10-11 21:36:46', '2026-10-11 21:44:47'),
(10, '39RjZ', 'https://www.1flex.org/play?id=2288&type=tv&season=2&episode=19&token=LX9Xsru0BO6D4rCF', NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, '2026-10-11 21:50:32', '2026-10-11 21:50:48'),
(11, 'essy', 'https://www.1flex.org/play?id=2288&type=tv&season=2&episode=19&token=LX9Xsru0BO6D4rCF', 'essy', '$argon2id$v=19$m=65536,t=4,p=1$d25kZ2VJa3RKeDlNSUg5Lg$R7Lc0+vfKK5jN6uxF+5I+RW4VWwAr1JLrmH367uMWss', '2026-10-12 02:02:53', NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, '2026-10-11 22:02:53', '2026-10-11 22:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `identifier` varchar(64) NOT NULL,
  `hits` int(10) UNSIGNED DEFAULT 0,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`identifier`, `hits`, `window_start`) VALUES
('link:::1', 4, '2026-10-11 21:36:46'),
('pw:essy:::1', 1, '2026-10-11 22:03:15'),
('pw:secret:::1', 1, '2026-10-11 20:26:45'),
('pw:tom:::1', 3, '2026-10-11 20:32:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ab_variants`
--
ALTER TABLE `ab_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_link` (`link_id`);

--
-- Indexes for table `bio_links`
--
ALTER TABLE `bio_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bio_page` (`bio_page_id`);

--
-- Indexes for table `bio_pages`
--
ALTER TABLE `bio_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `clicks`
--
ALTER TABLE `clicks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_link` (`link_id`),
  ADD KEY `idx_clicked` (`clicked_at`),
  ADD KEY `idx_country` (`country`);

--
-- Indexes for table `geo_cache`
--
ALTER TABLE `geo_cache`
  ADD PRIMARY KEY (`ip_address`),
  ADD KEY `idx_cached` (`cached_at`);

--
-- Indexes for table `links`
--
ALTER TABLE `links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `short_code` (`short_code`),
  ADD UNIQUE KEY `custom_alias` (`custom_alias`),
  ADD KEY `idx_short_code` (`short_code`),
  ADD KEY `idx_alias` (`custom_alias`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`identifier`),
  ADD KEY `idx_window` (`window_start`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ab_variants`
--
ALTER TABLE `ab_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bio_links`
--
ALTER TABLE `bio_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bio_pages`
--
ALTER TABLE `bio_pages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clicks`
--
ALTER TABLE `clicks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `links`
--
ALTER TABLE `links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ab_variants`
--
ALTER TABLE `ab_variants`
  ADD CONSTRAINT `ab_variants_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bio_links`
--
ALTER TABLE `bio_links`
  ADD CONSTRAINT `bio_links_ibfk_1` FOREIGN KEY (`bio_page_id`) REFERENCES `bio_pages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clicks`
--
ALTER TABLE `clicks`
  ADD CONSTRAINT `clicks_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
