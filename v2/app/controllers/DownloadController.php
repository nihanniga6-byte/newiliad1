<?php
/**
 * Download Controller
 * 
 * Handles secure file downloads from uploads directory.
 * 
 * @package App\Controllers
 */

class DownloadController extends Controller
{
    /**
     * Serve a file for download
     * 
     * @param string $path Relative path to file in uploads/
     */
    public function file(string $path): void
    {
        // Ensure user is authenticated
        if (!Auth::check()) {
            Session::flash('error', 'Please log in to download files');
            redirect('/login');
            return;
        }

        // Sanitize path - prevent directory traversal
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#\.{2,}#', '', $path);
        $path = ltrim($path, '/');

        // Full path to file
        $filePath = UPLOAD_PATH . '/' . $path;

        // Verify file exists
        if (!file_exists($filePath) || !is_file($filePath)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        // Verify file is within uploads directory (security check)
        $realPath = realpath($filePath);
        $uploadPath = realpath(UPLOAD_PATH);
        
        if ($realPath === false || strpos($realPath, $uploadPath) !== 0) {
            Logger::error("Directory traversal attempt detected: {$path}");
            http_response_code(403);
            echo 'Access denied';
            return;
        }

        // Get file info
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $filePath);
        finfo_close($fileInfo);

        $fileName = basename($filePath);
        $fileSize = filesize($filePath);

        // Set headers
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        // Clear any output buffer
        ob_clean();
        
        // Read and output file
        readfile($filePath);
        exit;
    }
}
