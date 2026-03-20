<?php

declare(strict_types=1);

final class UploadError
{
    public static function message(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File is larger than PHP allows (upload_max_filesize). '
                    . 'Raise upload_max_filesize and post_max_size in php.ini, or use the included .htaccess / .user.ini in the call-analyzer folder.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds the HTML form size limit.';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server missing a temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the upload.';
            default:
                return 'Upload failed (error code ' . $code . ').';
        }
    }
}
