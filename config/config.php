<?php

declare(strict_types=1);

$base = rtrim((string) (getenv('APP_BASE_PATH') ?: ''), '/');
if ($base === '' && PHP_SAPI !== 'cli') {
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($sn !== '' && str_ends_with($sn, '.php')) {
        $dir = str_replace('\\', '/', dirname($sn));
        if ($dir !== '/' && $dir !== '.' && $dir !== '') {
            $base = rtrim($dir, '/');
        }
    }
}

return [
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'openai_chat_model' => getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini',
    'openai_whisper_model' => getenv('OPENAI_WHISPER_MODEL') ?: 'whisper-1',
    'app_base_path' => $base,
    'app_front_script' => 'index.php',
    'storage_path' => dirname(__DIR__) . '/storage',

    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port' => (int) (getenv('DB_PORT') ?: '3306'),
    'db_name' => getenv('DB_NAME') ?: 'call_analyzer',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_password' => (string) (getenv('DB_PASSWORD') ?: 'toor1'),
    'db_charset' => getenv('DB_CHARSET') ?: 'utf8mb4',

    /** IANA timezone for displaying stored UTC timestamps (default India Standard Time) */
    'display_timezone' => (string) (getenv('APP_TIMEZONE') ?: 'Asia/Kolkata'),

    'upload_max_bytes' => 25 * 1024 * 1024,
    'allowed_mime' => [
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/x-m4a',
        'audio/mp4',
        'audio/ogg',
        'audio/webm',
        'audio/flac',
        'audio/x-flac',
    ],
];
