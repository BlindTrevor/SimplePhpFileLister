<?php
// ==============================================================================
// SIMPLE PHP FILE LISTER
// ==============================================================================

// ------------------------------------------------------------------------------
// CONFIGURATION
// ------------------------------------------------------------------------------

// Set the number of files to show per page before pagination is enabled
// When the total number of files and folders exceeds this threshold, pagination controls will appear
$paginationThreshold = 25;

$title = "Simple PHP File Lister";
$subtitle = "The Easy Way To List Files In A Directory";
$footer = "Made with ❤️ by Blind Trevor";

// ------------------------------------------------------------------------------
// SECURITY SETUP
// ------------------------------------------------------------------------------

// Security: prevent directory traversal
$realRoot = rtrim(realpath('.'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Blocked file extensions to prevent code execution
define('BLOCKED_EXTENSIONS', [
    'php', 'phtml', 'phar', 'cgi', 'pl', 'sh', 'bat', 'exe',
    'jsp', 'asp', 'aspx', 'py', 'rb', 'ps1', 'vbs', 'htaccess',
    'scr', 'com', 'jar'
]);

// ------------------------------------------------------------------------------
// FAST PATH: PREVIEW HANDLER
// ------------------------------------------------------------------------------

// This is placed at the top for maximum performance - exits immediately without loading anything else
// NOTE: MIME types array is intentionally duplicated here (also in getPreviewMimeType())
//       to avoid loading any functions. This duplication is a performance optimization.
//       When updating supported file types, update BOTH locations.
if (isset($_GET['preview'])) {
    $rel = (string)$_GET['preview'];
    $full = realpath($realRoot . $rel);
    
    // Validate path is within root and file exists
    if ($full === false || strpos($full . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(404);
        exit('Not found');
    }
    
    // Ensure it's a file, not a directory
    if (!is_file($full)) {
        http_response_code(404);
        exit('Not found');
    }
    
    // Get file extension and determine MIME type (inlined for speed)
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $mimeTypes = [
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'bmp' => 'image/bmp',
        'ico' => 'image/x-icon',
        // Videos
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'oga' => 'audio/ogg',
        'flac' => 'audio/flac',
        'm4a' => 'audio/mp4',
        // Documents
        'pdf' => 'application/pdf',
    ];
    
    // Only allow previewable file types
    if (!isset($mimeTypes[$ext])) {
        http_response_code(403);
        exit('Preview not supported for this file type');
    }
    
    $mimeType = $mimeTypes[$ext];
    
    // Open file for reading before sending headers
    $fp = fopen($full, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit('Failed to read file');
    }
    
    // Set headers for inline display
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($full));
    header('Cache-Control: public, max-age=3600');
    
    // Disable output buffering for efficient streaming
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Stream file content
    fpassthru($fp);
    fclose($fp);
    exit;
}

// ------------------------------------------------------------------------------
// DOWNLOAD ALL AS ZIP HANDLER
// ------------------------------------------------------------------------------

if (isset($_GET['download_all_zip'])) {
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('ZIP support is not available');
    }
    
    $currentPath = isset($_GET['path']) ? rtrim((string)$_GET['path'], '/') : '';
    $basePath = $currentPath ? './' . str_replace('\\', '/', $currentPath) : '.';
    $realBase = realpath($basePath);
    
    // Validate path is within root (use DIRECTORY_SEPARATOR suffix to prevent edge case bypasses)
    if ($realBase === false || strpos($realBase . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(404);
        exit('Not found');
    }
    
    // Create a temporary zip file with random name
    $tempZip = tempnam(sys_get_temp_dir(), 'spfl_' . bin2hex(random_bytes(8)) . '_');
    
    // Ensure cleanup even if script terminates unexpectedly
    register_shutdown_function(function() use ($tempZip) {
        if (file_exists($tempZip)) {
            @unlink($tempZip);
        }
    });
    
    $zip = new ZipArchive();
    
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tempZip);
        http_response_code(500);
        exit('Failed to create ZIP file');
    }
    
    // Recursive function to add directory contents to zip
    $addToZip = function($dir, $zipPath, &$count) use (&$addToZip, $zip, $realRoot) {
        $handle = opendir($dir);
        if ($handle === false) {
            return;
        }
        
        while (($entry = readdir($handle)) !== false) {
            if (in_array($entry, ['.', '..', 'index.php'], true) || $entry[0] === '.') {
                continue;
            }
            
            $fullPath = $dir . '/' . $entry;
            $realPath = realpath($fullPath);
            
            // Skip invalid paths, symlinks
            if (is_link($fullPath) || $realPath === false ||
                strpos($realPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
                continue;
            }
            
            $zipEntryPath = $zipPath ? $zipPath . '/' . $entry : $entry;
            
            if (is_dir($fullPath)) {
                // Add directory to zip and recurse
                $zip->addEmptyDir($zipEntryPath);
                $addToZip($fullPath, $zipEntryPath, $count);
            } else {
                // Block dangerous extensions
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
                    continue;
                }
                
                // Add file to zip
                if ($zip->addFile($fullPath, $zipEntryPath)) {
                    $count++;
                }
            }
        }
        closedir($handle);
    };
    
    // Add files and directories to zip
    $fileCount = 0;
    $addToZip($basePath, '', $fileCount);
    
    $zip->close();
    
    // If no files were added, clean up and exit
    if ($fileCount === 0) {
        @unlink($tempZip);
        http_response_code(404);
        exit('No files available to download');
    }
    
    // Generate safe filename for the zip
    $zipFilename = 'files.zip';
    if ($currentPath) {
        $folderName = basename($currentPath);
        $safeFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folderName);
        $zipFilename = $safeFolderName . '.zip';
    }
    
    // Open file for reading before sending headers
    $fp = fopen($tempZip, 'rb');
    if ($fp === false) {
        @unlink($tempZip);
        http_response_code(500);
        exit('Failed to read ZIP file');
    }
    
    // Send the zip file
    $safeFilename = preg_replace('/[\x00-\x1F\x7F"\\\\]|[\r\n]/', '', $zipFilename);
    $encodedFilename = rawurlencode($zipFilename);
    
    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"{$safeFilename}\"; filename*=UTF-8''{$encodedFilename}");
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($tempZip));
    
    // Disable output buffering for efficient streaming of large files
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Stream file content and cleanup
    fpassthru($fp);
    fclose($fp);
    @unlink($tempZip);
    exit;
}

// ------------------------------------------------------------------------------
// SINGLE FILE DOWNLOAD HANDLER
// ------------------------------------------------------------------------------

if (isset($_GET['download'])) {
    $rel = (string)$_GET['download'];
    $full = realpath($realRoot . $rel);
    
    // Validate path is within root and file exists
    // Use strpos with DIRECTORY_SEPARATOR suffix to handle edge cases
    if ($full === false || strpos($full . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(404);
        exit('Not found');
    }
    
    // Ensure it's a file, not a directory
    if (!is_file($full)) {
        http_response_code(404);
        exit('Not found');
    }
    
    // Block dangerous extensions to prevent code execution
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    
    // Open file for reading before sending headers
    $fp = fopen($full, 'rb');
    if ($fp === false) {
        // No cleanup needed for regular files (unlike temp ZIP files)
        http_response_code(500);
        exit('Failed to read file');
    }
    
    // Set secure download headers with properly escaped filename
    $filename = basename($full);
    // Remove control characters and dangerous chars for header injection prevention
    $safeFilename = preg_replace('/[\x00-\x1F\x7F"\\\\]|[\r\n]/', '', $filename);
    // Use RFC 2231 encoding for Unicode filename support
    $encodedFilename = rawurlencode($filename);
    
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"{$safeFilename}\"; filename*=UTF-8''{$encodedFilename}");
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($full));
    
    // Disable output buffering for efficient streaming of large files
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Stream file content
    fpassthru($fp);
    fclose($fp);
    exit;
}

// ------------------------------------------------------------------------------
// PATH HANDLING
// ------------------------------------------------------------------------------

// Redirect if path=.
if (isset($_GET['path']) && $_GET['path'] === '.') {
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Generate a cryptographically secure nonce for CSP
$cspNonce = base64_encode(random_bytes(16));

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdnjs.cloudflare.com 'nonce-{$cspNonce}'; script-src 'self' 'nonce-{$cspNonce}'; img-src 'self' https://img.shields.io data: blob:; font-src https://cdnjs.cloudflare.com; media-src 'self' blob:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

$currentPath = isset($_GET['path']) ? rtrim((string)$_GET['path'], '/') : '';
$basePath = $currentPath ? './' . str_replace('\\', '/', $currentPath) : '.';
$realBase = realpath($basePath);

$isValidPath = $realBase !== false && strpos($realBase . DIRECTORY_SEPARATOR, $realRoot) === 0;

// Pagination: Get current page from query string, default to 1
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Create breadcrumbs array
$breadcrumbs = [];
if ($isValidPath) {
    $pathParts = explode('/', $currentPath);
    $accumulatedPath = '';
    foreach ($pathParts as $part) {
        if ($part !== '') {
            $accumulatedPath .= ($accumulatedPath ? '/' : '') . $part;
            $breadcrumbs[] = [
                'name' => htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'path' => $accumulatedPath
            ];
        }
    }
}

// ------------------------------------------------------------------------------
// HELPER FUNCTIONS
// ------------------------------------------------------------------------------

function getPreviewableFileTypes(): array
{
    return [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'],
        'video' => ['mp4', 'webm', 'ogv'],
        'audio' => ['mp3', 'wav', 'oga', 'flac', 'm4a'],
        // PDF preview is limited in tooltips, shown as placeholder message
        'pdf' => ['pdf'],
    ];
}

function getPreviewMimeType(string $ext): ?string
{
    $mimeTypes = [
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'bmp' => 'image/bmp',
        'ico' => 'image/x-icon',
        // Videos
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'oga' => 'audio/ogg',
        'flac' => 'audio/flac',
        'm4a' => 'audio/mp4',
        // Documents
        'pdf' => 'application/pdf',
    ];
    
    return $mimeTypes[$ext] ?? null;
}

function formatFileSize(int $bytes): string
{
    if ($bytes === 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    $size = $bytes / pow(1024, $i);
    
    if ($i === 0) {
        return sprintf('%d B', $size);
    }
    return sprintf('%.2f %s', $size, $units[$i]);
}

function getFileIcon(string $path): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $extMap = [
        'pdf' => ['fa-regular fa-file-pdf', 'icon-pdf'],
        'doc' => ['fa-regular fa-file-word', 'icon-word'],
        'docx' => ['fa-regular fa-file-word', 'icon-word'],
        'xlsx' => ['fa-regular fa-file-excel', 'icon-excel'],
        'pptx' => ['fa-regular fa-file-powerpoint', 'icon-powerpoint'],
        'zip' => ['fa-solid fa-file-zipper', 'icon-archive'],
        'jpg' => ['fa-regular fa-file-image', 'icon-image'],
        'png' => ['fa-regular fa-file-image', 'icon-image'],
        'mp3' => ['fa-regular fa-file-audio', 'icon-audio'],
        'mp4' => ['fa-regular fa-file-video', 'icon-video'],
        'html' => ['fa-regular fa-file-code', 'icon-html'],
        'css' => ['fa-regular fa-file-code', 'icon-css'],
        'js' => ['fa-regular fa-file-code', 'icon-js'],
        'php' => ['fa-regular fa-file-code', 'icon-php'],
        'md' => ['fa-regular fa-file-lines', 'icon-markdown'],
    ];

    return $extMap[$ext] ?? ['fa-regular fa-file', 'icon-default'];
}

function renderItem(string $entry, bool $isDir, string $currentPath, int $fileSize = 0): void
{
    if ($isDir) {
        $href = '?path=' . rawurlencode($currentPath ? $currentPath . '/' . $entry : $entry);
        $iconClass = 'fa-solid fa-folder';
        $colorClass = 'icon-folder';
        $linkAttributes = 'class="dir-link"';
        $sizeHtml = '';
        $dataAttributes = '';
    } else {
        // Use secure download handler for files
        $filePath = $currentPath ? $currentPath . '/' . $entry : $entry;
        $href = '?download=' . rawurlencode($filePath);
        [$iconClass, $colorClass] = getFileIcon($entry);
        // Open downloads in new tab to prevent loading overlay on main page
        $linkAttributes = 'target="_blank" rel="noopener noreferrer"';
        $sizeHtml = '<span class="file-size">' . htmlspecialchars(formatFileSize($fileSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
        
        // Add data attributes for preview functionality
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $previewTypes = getPreviewableFileTypes();
        
        $dataAttributes = '';
        if (in_array($ext, $previewTypes['image'])) {
            $dataAttributes = ' data-preview="image" data-file-path="' . htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        } elseif (in_array($ext, $previewTypes['video'])) {
            $dataAttributes = ' data-preview="video" data-file-path="' . htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        } elseif (in_array($ext, $previewTypes['audio'])) {
            $dataAttributes = ' data-preview="audio" data-file-path="' . htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        } elseif (in_array($ext, $previewTypes['pdf'])) {
            $dataAttributes = ' data-preview="pdf" data-file-path="' . htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
    }

    $label = htmlspecialchars($entry, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    printf(
        '<li><a href="%s" %s%s><span class="file-icon %s"><i class="%s"></i></span><span class="file-name">%s</span>%s</a></li>' . PHP_EOL,
        htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $linkAttributes,
        $dataAttributes,
        htmlspecialchars($colorClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars($iconClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $label,
        $sizeHtml
    );
}

function hasDownloadableContent(string $dir, string $realRoot): bool
{
    $hasContent = false;
    
    $checkContent = function($checkDir) use (&$checkContent, $realRoot, &$hasContent) {
        $handle = opendir($checkDir);
        if ($handle === false) {
            return;
        }
        
        while (($entry = readdir($handle)) !== false) {
            if ($hasContent) break; // Early exit if we found content
            
            if (in_array($entry, ['.', '..', 'index.php'], true) || $entry[0] === '.') {
                continue;
            }
            
            $fullPath = $checkDir . '/' . $entry;
            $realPath = realpath($fullPath);
            
            // Skip invalid paths, symlinks
            if (is_link($fullPath) || $realPath === false ||
                strpos($realPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
                continue;
            }
            
            if (is_dir($fullPath)) {
                // Recurse into directory (early exit in loop handles found content)
                $checkContent($fullPath);
            } else {
                // Skip dangerous extensions
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
                    continue;
                }
                
                $hasContent = true;
                break;
            }
        }
        closedir($handle);
    };
    
    $checkContent($dir);
    return $hasContent;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous">
    <style nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
        /* ========================================
           CSS VARIABLES
           ======================================== */
        :root {
            --bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card: #ffffff;
            --accent: #667eea;
            --accent-hover: #5568d3;
            --text: #1a202c;
            --muted: #718096;
            --hover: #f7fafc;
            --border: #e2e8f0;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 50px rgba(0, 0, 0, 0.15);
        }
        
        /* ========================================
           GLOBAL STYLES
           ======================================== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 20px 16px;
            min-height: 100vh;
            line-height: 1.6;
        }
        
        /* ========================================
           LAYOUT
           ======================================== */
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .card {
            background: var(--card);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 32px;
            margin-bottom: 24px;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ========================================
           TYPOGRAPHY
           ======================================== */
        h1 {
            margin: 0 0 8px 0;
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        
        .subtitle {
            margin-bottom: 28px;
            color: var(--muted);
            font-size: clamp(0.875rem, 2vw, 1rem);
            font-weight: 400;
        }
        
        /* ========================================
           BREADCRUMBS
           ======================================== */
        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: clamp(0.813rem, 2vw, 0.9rem);
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .breadcrumbs a {
            color: var(--accent);
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .breadcrumbs a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        .breadcrumbs > a:first-child {
            font-weight: 600;
        }
        
        /* ========================================
           FILE LIST
           ======================================== */
        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .file-list li + li {
            margin-top: 12px;
        }
        
        .file-list a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text);
            background: var(--card);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-height: 60px;
        }
        
        .file-list a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--accent);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .file-list a:hover {
            background: var(--hover);
            border-color: var(--accent);
            transform: translateX(8px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .file-list a:hover::before {
            transform: scaleY(1);
        }
        
        .file-list a:active {
            transform: translateX(4px) scale(0.98);
        }
        
        .file-icon {
            font-size: clamp(1.25rem, 4vw, 1.5rem);
            color: var(--accent);
            flex-shrink: 0;
            width: 32px;
            text-align: center;
        }
        
        .file-name {
            font-weight: 500;
            word-break: break-word;
            flex: 1;
            font-size: clamp(0.875rem, 2vw, 1rem);
            line-height: 1.5;
        }
        
        .file-size {
            font-size: clamp(0.75rem, 2vw, 0.875rem);
            color: var(--muted);
            white-space: nowrap;
            margin-left: auto;
            padding-left: 12px;
            font-weight: 500;
        }
        
        /* ========================================
           STATS CONTAINER
           ======================================== */
        .stats-container {
            margin-top: 24px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .folder-file-count {
            color: var(--muted);
            font-size: clamp(0.813rem, 2vw, 0.9rem);
            font-weight: 500;
            flex: 1;
        }
        
        /* ========================================
           DOWNLOAD BUTTON
           ======================================== */
        .download-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: clamp(0.875rem, 2vw, 1rem);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            white-space: nowrap;
        }
        
        .download-all-btn:hover {
            background: linear-gradient(135deg, var(--accent-hover) 0%, var(--accent) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .download-all-btn:active {
            transform: translateY(0);
        }
        
        .download-all-btn i {
            font-size: 1.1rem;
        }
        
        /* ========================================
           FOOTER
           ======================================== */
        footer {
            margin-top: 20px;
            text-align: center;
            font-size: clamp(0.75rem, 2vw, 0.875rem);
            color: rgba(255, 255, 255, 0.95);
            font-weight: 400;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        footer a {
            display: inline-block;
            transition: transform 0.2s ease;
        }
        
        footer a:hover {
            transform: scale(1.05);
        }
        
        /* ========================================
           LOADING OVERLAY
           ======================================== */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 56px;
            height: 56px;
            border: 5px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        .loading-text {
            margin-top: 16px;
            color: var(--muted);
            font-size: clamp(0.875rem, 2vw, 1rem);
            text-align: center;
            font-weight: 500;
        }
        
        .loading-overlay.is-active {
            display: flex;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        /* ========================================
           FILE TYPE ICON COLORS
           ======================================== */
        .icon-pdf { color: #e74c3c; }
        .icon-word { color: #2980b9; }
        .icon-text { color: #7f8c8d; }
        .icon-excel { color: #27ae60; }
        .icon-powerpoint { color: #e67e22; }
        .icon-archive { color: #8e44ad; }
        .icon-image { color: #f39c12; }
        .icon-audio { color: #c0392b; }
        .icon-video { color: #16a085; }
        .icon-code { color: #34495e; }
        .icon-html { color: #e74c3c; }
        .icon-css { color: #2980b9; }
        .icon-js { color: #f1c40f; }
        .icon-ts { color: #2b5bae; }
        .icon-python { color: #3776ab; }
        .icon-php { color: #777bb4; }
        .icon-powershell { color: #0078d4; }
        .icon-sql { color: #336791; }
        .icon-yaml { color: #cb171e; }
        .icon-markdown { color: #083fa1; }
        .icon-default { color: #95a5a6; }
        .icon-folder { color: #f6a623; }
        
        /* ========================================
           PAGINATION
           ======================================== */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 24px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: clamp(0.875rem, 2vw, 0.95rem);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
            white-space: nowrap;
        }
        
        .pagination-btn:hover:not(.pagination-disabled) {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .pagination-btn:active:not(.pagination-disabled) {
            transform: translateY(0);
        }
        
        .pagination-btn.pagination-disabled {
            background: var(--border);
            color: var(--muted);
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .pagination-numbers {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .pagination-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 8px 12px;
            background: white;
            color: var(--text);
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: clamp(0.875rem, 2vw, 0.95rem);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .pagination-number:hover:not(.pagination-current) {
            background: var(--hover);
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-1px);
        }
        
        .pagination-number.pagination-current {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            cursor: default;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            color: var(--muted);
            font-weight: 700;
            font-size: 1.2rem;
            user-select: none;
        }
        
        /* ========================================
           PREVIEW TOOLTIP
           ======================================== */
        .preview-tooltip {
            position: fixed;
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            background: white;
            border: 2px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 8px;
            max-width: 400px;
            max-height: 400px;
        }
        
        .preview-tooltip.visible {
            opacity: 1;
        }
        
        .preview-tooltip img,
        .preview-tooltip video,
        .preview-tooltip audio {
            max-width: 100%;
            max-height: 380px;
            display: block;
            border-radius: 4px;
        }
        
        .preview-tooltip video {
            background: #000;
        }
        
        .preview-tooltip audio {
            width: 100%;
        }
        
        .preview-tooltip .preview-error {
            padding: 20px;
            color: var(--muted);
            text-align: center;
            font-size: 0.875rem;
        }
        
        .preview-tooltip .preview-loading {
            padding: 30px 40px;
            color: var(--accent);
            text-align: center;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 4px;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }
        
        /* ========================================
           ACCESSIBILITY
           ======================================== */
        @media (prefers-reduced-motion: reduce) {
            .loading-spinner {
                animation: none;
                border-top-color: var(--border);
            }
            .file-list a {
                transition: none;
            }
            .card {
                animation: none;
            }
        }
        
        /* ========================================
           RESPONSIVE: MOBILE PHONES (PORTRAIT)
           ======================================== */
        @media (max-width: 480px) {
            body {
                padding: 12px 12px;
            }
            
            .card {
                padding: 20px 16px;
                border-radius: 12px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .subtitle {
                font-size: 0.875rem;
                margin-bottom: 20px;
            }
            
            .breadcrumbs {
                padding: 12px 14px;
                gap: 6px;
                font-size: 0.813rem;
            }
            
            .file-list a {
                padding: 14px 12px;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .file-icon {
                font-size: 1.25rem;
                width: 28px;
            }
            
            .file-name {
                font-size: 0.875rem;
                flex: 1 1 100%;
                order: 2;
            }
            
            .file-size {
                font-size: 0.75rem;
                padding-left: 0;
                margin-left: 0;
                flex: 1;
                text-align: right;
                order: 1;
            }
            
            .file-list a:hover {
                transform: translateX(4px);
            }
            
            .download-all-btn {
                width: 100%;
                justify-content: center;
                padding: 14px 20px;
                font-size: 0.875rem;
            }
            
            .stats-container {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 14px 16px;
            }
            
            .folder-file-count {
                font-size: 0.813rem;
                text-align: center;
            }
            
            .pagination {
                padding: 16px 12px;
                gap: 8px;
            }
            
            .pagination-btn {
                padding: 10px 16px;
                font-size: 0.813rem;
            }
            
            .pagination-number {
                min-width: 36px;
                height: 36px;
                padding: 6px 10px;
                font-size: 0.813rem;
            }
            
            .pagination-ellipsis {
                min-width: 24px;
                font-size: 1rem;
            }
        }
        
        /* ========================================
           RESPONSIVE: MOBILE LANDSCAPE & SMALL TABLETS
           ======================================== */
        @media (min-width: 481px) and (max-width: 768px) {
            body {
                padding: 16px;
            }
            
            .card {
                padding: 24px 20px;
            }
            
            .file-list a {
                padding: 15px 16px;
            }
            
            .download-all-btn {
                width: 100%;
                justify-content: center;
            }
            
            .pagination {
                padding: 18px 16px;
            }
        }
        
        /* ========================================
           RESPONSIVE: TABLETS (PORTRAIT)
           ======================================== */
        @media (min-width: 769px) and (max-width: 1024px) {
            body {
                padding: 24px 20px;
            }
            
            .card {
                padding: 28px 24px;
            }
            
            .container {
                max-width: 85%;
            }
        }
        
        /* ========================================
           RESPONSIVE: LARGE SCREENS
           ======================================== */
        @media (min-width: 1025px) {
            body {
                padding: 40px 24px;
            }
            
            .card {
                padding: 36px 40px;
            }
            
            .file-list a:hover {
                transform: translateX(12px);
            }
        }
        
        /* ========================================
           TOUCH DEVICE OPTIMIZATIONS
           ======================================== */
        @media (hover: none) and (pointer: coarse) {
            .file-list a {
                min-height: 64px;
                padding: 18px 16px;
            }
            
            .download-all-btn {
                min-height: 48px;
                padding: 16px 24px;
            }
            
            .breadcrumbs a {
                padding: 4px 0;
                min-height: 32px;
                display: inline-flex;
                align-items: center;
            }
            
            .preview-tooltip {
                display: none;
            }
        }
        
        /* ========================================
           PRINT STYLES
           ======================================== */
        @media print {
            body {
                background: white;
                padding: 20px;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .download-all-btn,
            .loading-overlay,
            .pagination,
            footer a img {
                display: none;
            }
            
            .file-list a {
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <!-- Header -->
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <?php if (!empty($subtitle)): ?>
                <div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div>
            <?php endif; ?>

            <!-- Breadcrumbs -->
            <?php if (!empty($breadcrumbs)): ?>
                <div class="breadcrumbs">
                    <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>">Home</a>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        &gt;
                        <a href="?path=<?php echo rawurlencode($breadcrumb['path']); ?>" class="dir-link">
                            <?php echo $breadcrumb['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- File List -->
            <ul class="file-list">
                <?php
                if (!$isValidPath) {
                    echo '<li>Invalid path</li>';
                } else {
                    // Show parent directory link if not at root
                    if ($currentPath) {
                        $parentPath = dirname($currentPath);
                        printf(
                            '<li><a href="?path=%s" class="dir-link"><span class="file-icon icon-folder"><i class="fa-solid fa-arrow-up"></i></span><span class="file-name">..</span></a></li>' . PHP_EOL,
                            $parentPath ? rawurlencode($parentPath) : ''
                        );
                    }

                    $dirs = [];
                    $files = [];
                    $totalSize = 0;

                    if ($handle = opendir($basePath)) {
                        while (($entry = readdir($handle)) !== false) {
                            if (in_array($entry, ['.', '..', 'index.php'], true) || $entry[0] === '.') {
                                continue;
                            }

                            $fullPath = $basePath . '/' . $entry;
                            $realPath = realpath($fullPath);

                            if (is_link($fullPath) || $realPath === false || strpos($realPath, $realRoot) !== 0) {
                                continue;
                            }

                            if (is_dir($fullPath)) {
                                $dirs[] = $entry;
                            } else {
                                // Block dangerous extensions from being listed
                                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                                if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
                                    continue;
                                }
                                
                                $fileSize = @filesize($fullPath);
                                if ($fileSize !== false) {
                                    $files[] = ['name' => $entry, 'size' => $fileSize];
                                    $totalSize += $fileSize;
                                } else {
                                    // If filesize fails, still list the file but with size 0
                                    $files[] = ['name' => $entry, 'size' => 0];
                                }
                            }
                        }
                        closedir($handle);
                    }

                    natcasesort($dirs);
                    usort($files, function($a, $b) {
                        return strnatcasecmp($a['name'], $b['name']);
                    });

                    // Pagination logic
                    $totalItems = count($dirs) + count($files);
                    $totalPages = ($totalItems > $paginationThreshold) ? (int)ceil($totalItems / $paginationThreshold) : 1;
                    $currentPage = max(1, min($currentPage, $totalPages));
                    $itemsPerPage = $paginationThreshold;
                    $offset = ($currentPage - 1) * $itemsPerPage;
                    
                    // Merge dirs and files into a single array for pagination
                    $allItems = [];
                    foreach ($dirs as $dir) {
                        $allItems[] = ['type' => 'dir', 'name' => $dir, 'size' => 0];
                    }
                    foreach ($files as $file) {
                        $allItems[] = ['type' => 'file', 'name' => $file['name'], 'size' => $file['size']];
                    }
                    
                    // Get items for current page
                    $itemsToDisplay = array_slice($allItems, $offset, $itemsPerPage);

                    foreach ($itemsToDisplay as $item) {
                        if ($item['type'] === 'dir') {
                            renderItem($item['name'], true, $currentPath);
                        } else {
                            renderItem($item['name'], false, $currentPath, $item['size']);
                        }
                    }
                }
                ?>
            </ul>

            <!-- Pagination -->
            <?php if ($isValidPath && $totalPages > 1): ?>
                <?php
                $baseUrl = '?';
                if ($currentPath) {
                    $baseUrl .= 'path=' . rawurlencode($currentPath) . '&';
                }
                ?>
                <div class="pagination" role="navigation" aria-label="Pagination">
                    <!-- Previous Button -->
                    <?php if ($currentPage > 1): ?>
                        <a href="<?php echo htmlspecialchars($baseUrl . 'page=' . ($currentPage - 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" 
                           class="pagination-btn pagination-prev" 
                           aria-label="Previous page">
                            <i class="fa-solid fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn pagination-prev pagination-disabled" aria-disabled="true">
                            <i class="fa-solid fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <div class="pagination-numbers">
                        <?php
                        $maxPagesToShow = 7;
                        $halfRange = floor($maxPagesToShow / 2);
                        $startPage = max(1, $currentPage - $halfRange);
                        $endPage = min($totalPages, $currentPage + $halfRange);
                        
                        if ($currentPage <= $halfRange) {
                            $endPage = min($totalPages, $maxPagesToShow);
                        } elseif ($currentPage >= $totalPages - $halfRange) {
                            $startPage = max(1, $totalPages - $maxPagesToShow + 1);
                        }
                        
                        // First page
                        if ($startPage > 1) {
                            $pageUrl = $baseUrl . 'page=1';
                            echo '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-number" aria-label="Go to page 1">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="pagination-ellipsis" aria-hidden="true">...</span>';
                            }
                        }
                        
                        // Page range
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i === $currentPage) {
                                echo '<span class="pagination-number pagination-current" aria-current="page" aria-label="Current page, page ' . $i . '">' . $i . '</span>';
                            } else {
                                $pageUrl = $baseUrl . 'page=' . $i;
                                echo '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-number" aria-label="Go to page ' . $i . '">' . $i . '</a>';
                            }
                        }
                        
                        // Last page
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="pagination-ellipsis" aria-hidden="true">...</span>';
                            }
                            $pageUrl = $baseUrl . 'page=' . $totalPages;
                            echo '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-number" aria-label="Go to page ' . $totalPages . '">' . $totalPages . '</a>';
                        }
                        ?>
                    </div>
                    
                    <!-- Next Button -->
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?php echo htmlspecialchars($baseUrl . 'page=' . ($currentPage + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" 
                           class="pagination-btn pagination-next" 
                           aria-label="Next page">
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn pagination-next pagination-disabled" aria-disabled="true">
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Stats and Download Button -->
            <?php if ($isValidPath): ?>
                <?php
                $statsHtml = '';
                $statsHtml .= count($dirs) . ' folder' . (count($dirs) !== 1 ? 's' : '') . ', ';
                $statsHtml .= count($files) . ' file' . (count($files) !== 1 ? 's' : '');
                if ($totalSize > 0) {
                    $statsHtml .= ' (' . htmlspecialchars(formatFileSize($totalSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' total)';
                }
                ?>
                <div class="stats-container">
                    <div class="folder-file-count"><?php echo $statsHtml; ?></div>
                    
                    <?php if (hasDownloadableContent($basePath, $realRoot)): ?>
                        <?php
                        $downloadAllUrl = '?download_all_zip=1';
                        if ($currentPath) {
                            $downloadAllUrl .= '&path=' . rawurlencode($currentPath);
                        }
                        ?>
                        <a href="<?php echo htmlspecialchars($downloadAllUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" 
                           class="download-all-btn" 
                           target="_blank" 
                           rel="noopener noreferrer">
                            <i class="fa-solid fa-download"></i>
                            Download All as ZIP
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <?php if (!empty($footer)): ?>
            <footer><?php echo htmlspecialchars($footer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></footer>
        <?php endif; ?>

        <footer>
            <a href="https://github.com/BlindTrevor/SimplePhpFileLister/" target="_blank">
                <img src="https://img.shields.io/badge/Created_by_Blind_Trevor-Simple_PHP_File_Lister-magenta" alt="GitHub"/>
            </a>
        </footer>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" aria-hidden="true">
        <div role="status" aria-live="polite" aria-label="Loading">
            <div class="loading-spinner" aria-hidden="true"></div>
            <div class="loading-text">Loading directory…</div>
        </div>
    </div>

    <!-- JavaScript -->
    <script nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
        (function() {
            'use strict';
            
            const overlay = document.querySelector('.loading-overlay');

            // Loading overlay functions
            function showOverlay() {
                if (!overlay) return;
                overlay.classList.add('is-active');
                overlay.setAttribute('aria-hidden', 'false');
            }

            function hideOverlay() {
                if (!overlay) return;
                overlay.classList.remove('is-active');
                overlay.setAttribute('aria-hidden', 'true');
            }

            document.addEventListener('DOMContentLoaded', hideOverlay);

            document.addEventListener('click', function(e) {
                const a = e.target.closest('a');
                if (!a) return;

                const isModified = e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1;
                if (isModified) return;

                const isDirLink = a.classList.contains('dir-link') && !a.hasAttribute('download');
                const isPaginationLink = a.classList.contains('pagination-btn') || a.classList.contains('pagination-number');
                
                if (isDirLink || isPaginationLink) showOverlay();
            }, { capture: true });

            window.addEventListener('beforeunload', showOverlay);
            
            // ========================================
            // PREVIEW TOOLTIP FUNCTIONALITY
            // ========================================
            (function() {
                // Skip on touch-only devices
                const isTouchOnly = ('ontouchstart' in window || navigator.maxTouchPoints > 0) && 
                                   !window.matchMedia('(hover: hover) and (pointer: fine)').matches;
                
                if (isTouchOnly) {
                    console.log('[Preview] Disabled: Touch-only device detected');
                    return;
                }
                
                console.log('[Preview] Initialized: Preview functionality enabled');
                
                let tooltip = null;
                let currentPreview = null;
                let showTimeout = null;
                let hideTimeout = null;
                
                function createTooltip() {
                    if (tooltip) return tooltip;
                    tooltip = document.createElement('div');
                    tooltip.className = 'preview-tooltip';
                    tooltip.setAttribute('role', 'tooltip');
                    tooltip.setAttribute('aria-live', 'polite');
                    document.body.appendChild(tooltip);
                    return tooltip;
                }
                
                function positionTooltip(e) {
                    if (!tooltip) return;
                    
                    const padding = 15;
                    const tooltipRect = tooltip.getBoundingClientRect();
                    let x = e.clientX + padding;
                    let y = e.clientY + padding;
                    
                    // Adjust if tooltip goes off right edge
                    if (x + tooltipRect.width > window.innerWidth) {
                        x = e.clientX - tooltipRect.width - padding;
                    }
                    
                    // Adjust if tooltip goes off bottom edge
                    if (y + tooltipRect.height > window.innerHeight) {
                        y = e.clientY - tooltipRect.height - padding;
                    }
