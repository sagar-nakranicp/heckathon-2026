-- Call Analyzer — MySQL schema for phpMyAdmin
-- 1. Create database (or use SQL tab): CREATE DATABASE call_analyzer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- 2. Select that database, then Import this file, OR paste and run.

CREATE TABLE IF NOT EXISTS `calls` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `stored_filename` varchar(255) NOT NULL,
  `original_name` varchar(512) NOT NULL,
  `mime` varchar(128) NOT NULL,
  `size_bytes` int unsigned NOT NULL,
  `duration_seconds` decimal(10,3) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `transcript` longtext,
  `analysis_json` longtext,
  `error_message` text,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_calls_status` (`status`),
  KEY `idx_calls_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
