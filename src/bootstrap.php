<?php

declare(strict_types=1);

require_once __DIR__ . '/polyfills.php';
require_once __DIR__ . '/UploadStorage.php';

$root = dirname(__DIR__);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (is_file($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k !== '') {
            // Always apply .env so a valid key in the file is not ignored when
            // PHP/the web server sets the same variable empty or to a stale value.
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

$config = require $root . '/config/config.php';

$storage = $config['storage_path'];
$uploadDir = $storage . '/uploads';
try {
    UploadStorage::ensureUploadDir($uploadDir);
} catch (Throwable $e) {
    // Non-fatal: upload handler will retry / show message
}

require_once $root . '/src/Database.php';
require_once $root . '/src/Http.php';
require_once $root . '/src/AudioDuration.php';
require_once $root . '/src/OpenAiClient.php';
require_once $root . '/src/CallRepository.php';
require_once $root . '/src/CallAnalyzerService.php';

$pdo = Database::connect($config);
Database::migrate($pdo);

return ['config' => $config, 'pdo' => $pdo, 'root' => $root];
