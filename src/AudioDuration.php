<?php

declare(strict_types=1);

/**
 * Best-effort audio duration via ffprobe when available on the server.
 */
final class AudioDuration
{
    /** @return positive-float|null */
    public static function guess(string $absolutePath): ?float
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $real = realpath($absolutePath);
        if ($real === false) {
            return null;
        }
        if (!function_exists('shell_exec')) {
            return null;
        }
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($real)
        );
        $out = shell_exec($cmd);
        if ($out === null || $out === '') {
            return null;
        }
        $v = (float) trim($out);
        return $v > 0 ? $v : null;
    }
}
