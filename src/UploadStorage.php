<?php

declare(strict_types=1);

final class UploadStorage
{
    /**
     * Create dir and make it writable by the web server (typical fix for move_uploaded_file failures).
     */
    public static function ensureUploadDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create directory: ' . $dir);
            }
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0777);
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
    }

    /**
     * @return true|string error message
     */
    public static function saveFromTmp(string $tmpPath, string $destinationPath): bool
    {
        self::ensureUploadDir(dirname($destinationPath));

        if (!is_uploaded_file($tmpPath)) {
            return 'Invalid temp upload path (not an uploaded file).';
        }

        if (@move_uploaded_file($tmpPath, $destinationPath)) {
            return true;
        }

        // Same filesystem issues / SELinux: try copy
        if (@copy($tmpPath, $destinationPath)) {
            @unlink($tmpPath);
            return true;
        }

        $dir = dirname($destinationPath);
        if (!is_writable($dir)) {
            return 'Folder not writable by web server: ' . $dir
                . '. Fix: sudo chown -R www-data:www-data ' . dirname($dir)
                . ' && sudo chmod -R ug+rwX ' . dirname($dir);
        }

        return 'Could not move file to storage. Check disk space and permissions on: ' . $dir;
    }
}
