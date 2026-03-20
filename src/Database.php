<?php

declare(strict_types=1);

final class Database
{
    public static function connect(array $config): PDO
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException(
                'PHP extension pdo_mysql is not loaded. Install it (e.g. sudo apt install php'
                . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-mysql) and restart Apache/PHP-FPM.'
            );
        }

        $charset = $config['db_charset'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['db_host'],
            $config['db_port'],
            $config['db_name'],
            $charset
        );

        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . self::quoteCharset($charset),
        ]);

        return $pdo;
    }

    private static function quoteCharset(string $charset): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $charset) ?: 'utf8mb4';
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
