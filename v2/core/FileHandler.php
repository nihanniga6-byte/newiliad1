<?php
/**
 * File Handler Class
 * 
 * Handles secure file uploads with validation.
 * Stores files OUTSIDE public directory for security.
 * 
 * @package Core
 */

class FileHandler
{
    private array $errors = [];
    private array $uploadedFile = [];

    /**
     * Process file upload
     * 
     * @param string $fieldName Form field name
     * @param string $subDir Subdirectory within uploads
     * @return array|null Uploaded file info or null
     */
    public function upload(string $fieldName, string $subDir = ''): ?array
    {
        $this->errors = [];

        // Check if file was uploaded
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$fieldName];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getErrorMessage($file['error']);
            return null;
        }

        // Validate file size
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $this->errors[] = 'File size exceeds maximum limit (' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB)';
            return null;
        }

        // Validate MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
            $this->errors[] = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_MIME_TYPES);
            return null;
        }

        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            $this->errors[] = 'Invalid file extension. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
            return null;
        }

        // Generate safe filename with UUID + timestamp
        $safeFilename = $this->generateFilename($file['name']);

        // Determine destination directory
        $uploadDir = UPLOAD_PATH;
        if ($subDir) {
            $uploadDir .= '/' . ltrim($subDir, '/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
        }

        $destination = $uploadDir . '/' . $safeFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->errors[] = 'Failed to save uploaded file.';
            return null;
        }

        // Set secure permissions
        chmod($destination, 0644);

        $relativePath = '/' . ltrim($subDir . '/' . $safeFilename, '/');

        $this->uploadedFile = [
            'original_name' => $file['name'],
            'filename' => $safeFilename,
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'path' => $destination,
            'relative_path' => $relativePath,
            'extension' => $extension,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];

        Logger::info("File uploaded: {$safeFilename} ({$mimeType}, {$file['size']} bytes)");
        return $this->uploadedFile;
    }

    /**
     * Delete uploaded file
     * 
     * @param string $filePath File path to delete
     * @return bool True on success
     */
    public function delete(string $filePath): bool
    {
        // Ensure file is within uploads directory
        $realPath = realpath(UPLOAD_PATH . $filePath);
        $uploadPath = realpath(UPLOAD_PATH);
        
        if ($realPath === false || strpos($realPath, $uploadPath) !== 0) {
            Logger::error("Attempted to delete file outside uploads: {$filePath}");
            return false;
        }

        if (file_exists($realPath)) {
            return unlink($realPath);
        }
        return false;
    }

    /**
     * Get upload errors
     * 
     * @return array Error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get uploaded file info
     * 
     * @return array File information
     */
    public function getUploadedFile(): array
    {
        return $this->uploadedFile;
    }

    /**
     * Generate unique safe filename
     * 
     * @param string $originalName Original filename
     * @return string Safe filename
     */
    private function generateFilename(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $uuid = bin2hex(random_bytes(8)); // 16 chars
        $timestamp = date('Ymd_His');
        return "{$timestamp}_{$uuid}.{$extension}";
    }

    /**
     * Get human-readable error message
     * 
     * @param int $errorCode PHP upload error code
     * @return string Error message
     */
    private function getErrorMessage(int $errorCode): string
    {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown upload error'
        };
    }

    /**
     * Resize image
     * 
     * @param string $source Source image path
     * @param string $destination Destination path
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @return bool True on success
     */
    public static function resizeImage(string $source, string $destination, int $maxWidth, int $maxHeight): bool
    {
        $imageInfo = getimagesize($source);
        if (!$imageInfo) {
            return false;
        }

        list($originalWidth, $originalHeight) = $imageInfo;
        $mimeType = $imageInfo['mime'];

        // Calculate new dimensions
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        if ($ratio >= 1) {
            // Don't upscale
            copy($source, $destination);
            return true;
        }

        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        // Create image based on type
        $sourceImage = match($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($source),
            'image/png' => imagecreatefrompng($source),
            'image/gif' => imagecreatefromgif($source),
            'image/webp' => imagecreatefromwebp($source),
            default => false
        };

        if (!$sourceImage) {
            return false;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Save based on type
        $result = match($mimeType) {
            'image/jpeg' => imagejpeg($newImage, $destination, 85),
            'image/png' => imagepng($newImage, $destination, 6),
            'image/gif' => imagegif($newImage, $destination),
            'image/webp' => imagewebp($newImage, $destination, 85),
            default => false
        };

        imagedestroy($sourceImage);
        imagedestroy($newImage);

        return $result;
    }

    /**
     * Get file info
     * 
     * @param string $filePath File path
     * @return array File information
     */
    public static function getFileInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return [
            'size' => filesize($filePath),
            'mime_type' => $mimeType,
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'modified' => date('Y-m-d H:i:s', filemtime($filePath))
        ];
    }
}
