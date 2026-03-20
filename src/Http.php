<?php

declare(strict_types=1);

final class Http
{
    public static function basePath(array $config): string
    {
        return $config['app_base_path'];
    }

    public static function url(string $path, array $config): string
    {
        $base = self::basePath($config);
        $path = '/' . ltrim($path, '/');

        if ($base !== '') {
            $front = $config['app_front_script'] ?? 'index.php';
            if ($path === '/' || $path === '') {
                return $base . '/' . $front;
            }
            if (str_starts_with($path, '/?')) {
                return $base . '/' . $front . substr($path, 1);
            }
            return $base . $path;
        }

        return $path;
    }

    public static function redirect(string $path, array $config): void
    {
        header('Location: ' . self::url($path, $config), true, 302);
        exit;
    }

    public static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Format stored ISO-8601 (UTC) time for the dashboard — e.g. "20 Mar 2026, 11:30 AM IST".
     */
    public static function formatDisplayTime(?string $isoUtc, array $config): string
    {
        if ($isoUtc === null || $isoUtc === '') {
            return '—';
        }
        try {
            $tzName = $config['display_timezone'] ?? 'Asia/Kolkata';
            $tz = new \DateTimeZone($tzName);
            $dt = new \DateTimeImmutable($isoUtc);
            $dt = $dt->setTimezone($tz);
            $abbr = $dt->format('T');
            if ($abbr === '+0530') {
                $abbr = 'IST';
            }
            return $dt->format('d M Y, h:i A') . ' ' . $abbr;
        } catch (\Throwable $e) {
            return $isoUtc;
        }
    }

    /**
     * Human-readable length, e.g. "2:34" or "1:05:02". Unknown or non-positive → "—".
     */
    public static function formatDurationSeconds($seconds): string
    {
        if ($seconds === null || $seconds === '') {
            return '—';
        }
        $n = (float) $seconds;
        if ($n <= 0) {
            return '—';
        }
        $s = (int) round($n);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;
        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $sec);
        }

        return sprintf('%d:%02d', $m, $sec);
    }
}
