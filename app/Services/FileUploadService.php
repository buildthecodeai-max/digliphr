<?php

declare(strict_types=1);

namespace App\Services;

class FileUploadService
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{success: bool, message?: string, filename?: string, path?: string, relative_path?: string, mime_type?: string, file_size?: int, width?: int, height?: int}
     */
    public function saveBase64Image(
        string $base64,
        ?string $directory = null,
        ?string $originalFilename = null
    ): array {
        $directory = $directory ?? config('app.paths.attendance_images');

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                return ['success' => false, 'message' => 'Unable to create upload directory.'];
            }
        }

        $parsed = $this->parseBase64Image($base64);
        if (!$parsed['success']) {
            return $parsed;
        }

        $binary = $parsed['binary'];
        $mime = $parsed['mime_type'];

        $validation = $this->validateImageBinary($binary, $mime);
        if (!$validation['success']) {
            return $validation;
        }

        $subdir = date('Y/m');
        $targetDir = rtrim($directory, '/') . '/' . $subdir;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return ['success' => false, 'message' => 'Unable to create upload subdirectory.'];
            }
        }

        $extension = self::ALLOWED_MIMES[$mime];
        $filename = $this->randomFilename($extension);
        $fullPath = $targetDir . '/' . $filename;
        $relativePath = $subdir . '/' . $filename;

        if (file_put_contents($fullPath, $binary) === false) {
            return ['success' => false, 'message' => 'Failed to save image file.'];
        }

        chmod($fullPath, 0640);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $fullPath,
            'relative_path' => $relativePath,
            'mime_type' => $mime,
            'file_size' => strlen($binary),
            'width' => $validation['width'],
            'height' => $validation['height'],
            'original_filename' => $originalFilename,
        ];
    }

    /**
     * @return array{success: bool, message?: string, binary?: string, mime_type?: string}
     */
    private function parseBase64Image(string $base64): array
    {
        $base64 = trim($base64);
        if ($base64 === '') {
            return ['success' => false, 'message' => 'Image data is required.'];
        }

        $mime = null;
        $data = $base64;

        if (preg_match('#^data:(image/(?:jpeg|png|webp));base64,(.+)$#is', $base64, $matches)) {
            $mime = strtolower($matches[1]);
            $data = $matches[2];
        }

        $binary = base64_decode(str_replace(' ', '+', $data), true);
        if ($binary === false || $binary === '') {
            return ['success' => false, 'message' => 'Invalid base64 image data.'];
        }

        if ($mime === null) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($binary) ?: '';
            if (!isset(self::ALLOWED_MIMES[$detected])) {
                return ['success' => false, 'message' => 'Unsupported image type. Allowed: JPEG, PNG, WebP.'];
            }
            $mime = $detected;
        } elseif (!isset(self::ALLOWED_MIMES[$mime])) {
            return ['success' => false, 'message' => 'Unsupported image type. Allowed: JPEG, PNG, WebP.'];
        }

        return [
            'success' => true,
            'binary' => $binary,
            'mime_type' => $mime,
        ];
    }

    /**
     * @return array{success: bool, message?: string, width?: int, height?: int}
     */
    public function validateImageBinary(string $binary, string $mime): array
    {
        $maxSize = (int) config('app.upload_max_size', 5242880);
        if (strlen($binary) > $maxSize) {
            $mb = round($maxSize / 1048576, 1);
            return ['success' => false, 'message' => "Image exceeds maximum size of {$mb}MB."];
        }

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            return ['success' => false, 'message' => 'Invalid image MIME type.'];
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            return ['success' => false, 'message' => 'Invalid or corrupted image file.'];
        }

        [$width, $height] = $info;
        if ($width < 200 || $height < 200) {
            return ['success' => false, 'message' => 'Image must be at least 200x200 pixels.'];
        }

        if ($width > 4096 || $height > 4096) {
            return ['success' => false, 'message' => 'Image dimensions exceed maximum allowed (4096x4096).'];
        }

        return [
            'success' => true,
            'width' => $width,
            'height' => $height,
        ];
    }

    public function randomFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . ltrim($extension, '.');
    }

    /**
     * Store a standard multipart upload outside the public directory.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     * @param array{allowed_mimes?:list<string>,max_size?:int} $options
     * @return array{success: bool, message?: string, filename?: string, path?: string, original_filename?: string, mime_type?: string, size?: int}
     */
    public function storeUpload(array $file, string $directory, array $options = []): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed.'];
        }

        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['success' => false, 'message' => 'Invalid uploaded file.'];
        }

        $maxSize = (int) ($options['max_size'] ?? config('app.upload_max_size', 5242880));
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            return ['success' => false, 'message' => 'File size is invalid or exceeds the limit.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: ($file['type'] ?? 'application/octet-stream');
        $allowed = $options['allowed_mimes'] ?? [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'text/plain',
        ];

        if (!in_array($mime, $allowed, true)) {
            return ['success' => false, 'message' => 'File type is not allowed.'];
        }

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ['success' => false, 'message' => 'Unable to create upload directory.'];
        }

        $extMap = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/zip' => 'zip',
        ];
        $extension = $extMap[$mime] ?? pathinfo((string) ($file['name'] ?? 'file.bin'), PATHINFO_EXTENSION) ?: 'bin';
        $filename = $this->randomFilename($extension);
        $fullPath = rtrim($directory, '/') . '/' . $filename;

        if (!move_uploaded_file($tmp, $fullPath)) {
            return ['success' => false, 'message' => 'Failed to store uploaded file.'];
        }

        chmod($fullPath, 0640);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $fullPath,
            'original_filename' => $file['name'] ?? $filename,
            'mime_type' => $mime,
            'size' => $size,
        ];
    }

    public function resolveFullPath(string $relativePath): string
    {
        $base = rtrim((string) config('app.paths.attendance_images'), '/');
        $relativePath = ltrim(str_replace(['..', '\\'], '', $relativePath), '/');

        return $base . '/' . $relativePath;
    }

    public function deleteFile(string $relativePath): bool
    {
        $fullPath = $this->resolveFullPath($relativePath);
        if (!is_file($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }
}
