<?php
/**
 * Simple PHP File Lister
 * A lightweight, secure directory listing application
 * 
 * @author Blind Trevor
 * @link https://github.com/BlindTrevor/SimplePhpFileLister
 * @version 1.0.4
 */

// ============================================================================
// VERSION INFORMATION
// ============================================================================
// Version is automatically updated by GitHub Actions on merge to main branch
define('APP_VERSION', '1.0.4');

// ============================================================================
// CONFIGURATION
// ============================================================================

// Pagination Settings
$paginationThreshold = 25; // Number of items per page before pagination appears

// Display Customization
$title = "Simple PHP File Lister";
$subtitle = "The Easy Way To List Files In A Directory";
$footer = "Made with ❤️ by Blind Trevor";

// Feature Configuration
$enableRename = false; // Set to false to disable rename functionality
$enableDelete = false; // Set to false to disable delete functionality

// Download & Export Configuration
$enableDownloadAll = true; // Enable/disable "Download All as ZIP" button
$enableBatchDownload = true; // Enable/disable batch download of selected items as ZIP
$enableIndividualDownload = true; // Enable/disable individual file downloads

// Display Configuration
$showFileSize = true; // Show/hide file sizes in file listings
$showFolderFileCount = true; // Show/hide folder/file count statistics
$showTotalSize = true; // Show/hide total size in statistics

// Advanced Options
$includeHiddenFiles = false; // Include hidden files (starting with .) in listings
$zipCompressionLevel = 6; // ZIP compression level (0-9, where 0=no compression, 9=maximum compression)

// Validate ZIP compression level to ensure it's within valid range
$zipCompressionLevel = max(0, min(9, (int)$zipCompressionLevel));

// Security Configuration
$realRoot = rtrim(realpath('.'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Blocked file extensions to prevent code execution
define('BLOCKED_EXTENSIONS', [
    'php', 'phtml', 'phar', 'cgi', 'pl', 'sh', 'bat', 'exe',
    'jsp', 'asp', 'aspx', 'py', 'rb', 'ps1', 'vbs', 'htaccess',
    'scr', 'com', 'jar'
]);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get list of previewable file types grouped by category
 * Note: PDF preview is limited in tooltips and shows a placeholder message
 * @return array Array of file types by category
 */
function getPreviewableFileTypes(): array {
    return [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'],
        'video' => ['mp4', 'webm', 'ogv'],
        'audio' => ['mp3', 'wav', 'oga', 'flac', 'm4a'],
        'pdf' => ['pdf'],
    ];
}

/**
 * Get MIME type for preview-supported file extensions
 * @param string $ext File extension
 * @return string|null MIME type or null if not supported
 */
function getPreviewMimeType(string $ext): ?string {
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

/**
 * Format file size in human-readable format
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize(int $bytes): string {
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

/**
 * Get appropriate icon class for file based on extension
 * @param string $path File path
 * @return array Array containing icon class and color class
 */
function getFileIcon(string $path): array {
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

/**
 * Render a single file or directory item in the list
 * @param string $entry File or directory name
 * @param bool $isDir Whether this is a directory
 * @param string $currentPath Current path context
 * @param int $fileSize File size in bytes (for files only)
 * @param bool $enableRename Whether rename functionality is enabled
 * @param bool $enableDelete Whether delete functionality is enabled
 * @param bool $showCheckbox Whether to show checkbox for multi-select
 * @param bool $showFileSize Whether to show file sizes
 * @param bool $enableDownload Whether individual downloads are enabled
 */
function renderItem(string $entry, bool $isDir, string $currentPath, int $fileSize = 0, bool $enableRename = false, bool $enableDelete = false, bool $showCheckbox = false, bool $showFileSize = true, bool $enableDownload = true): void {
    if ($isDir) {
        $href = '?path=' . rawurlencode($currentPath ? $currentPath . '/' . $entry : $entry);
        $iconClass = 'fa-solid fa-folder';
        $colorClass = 'icon-folder';
        $linkAttributes = 'class="dir-link"';
        $sizeHtml = '';
        $dataAttributes = '';
    } else {
        // Use secure download handler for files (or # if downloads disabled)
        $filePath = $currentPath ? $currentPath . '/' . $entry : $entry;
        if ($enableDownload) {
            $href = '?download=' . rawurlencode($filePath);
            // Open downloads in new tab to prevent loading overlay on main page
            $linkAttributes = 'target="_blank" rel="noopener noreferrer"';
        } else {
            // When downloads are disabled, use # as href and add aria-disabled
            $href = '#';
            $linkAttributes = 'aria-disabled="true" style="cursor: not-allowed; opacity: 0.6;"';
        }
        [$iconClass, $colorClass] = getFileIcon($entry);
        // Conditionally show file size based on configuration
        $sizeHtml = $showFileSize ? '<span class="file-size">' . htmlspecialchars(formatFileSize($fileSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>' : '';
        
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
    $filePath = $currentPath ? $currentPath . '/' . $entry : $entry;
    
    // Add checkbox for multi-select if enabled
    $checkbox = '';
    if ($showCheckbox) {
        $checkbox = sprintf(
            '<input type="checkbox" class="item-checkbox" data-item-path="%s" data-item-name="%s" data-is-dir="%s" data-item-size="%d" aria-label="Select %s">',
            htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $label,
            $isDir ? 'true' : 'false',
            (int)$fileSize,
            $label
        );
    }
    
    // Add rename button if enabled
    $renameButton = '';
    if ($enableRename) {
        $filePath = $currentPath ? $currentPath . '/' . $entry : $entry;
        $renameButton = sprintf(
            '<button class="rename-btn" data-file-path="%s" data-file-name="%s" data-is-dir="%s" title="Rename" aria-label="Rename %s"><i class="fa-solid fa-pen"></i></button>',
            htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $label,
            $isDir ? 'true' : 'false',
            $label
        );
    }
    
    // Add delete button if enabled
    $deleteButton = '';
    if ($enableDelete) {
        $filePath = $currentPath ? $currentPath . '/' . $entry : $entry;
        $deleteButton = sprintf(
            '<button class="delete-btn" data-file-path="%s" data-file-name="%s" data-is-dir="%s" title="Delete" aria-label="Delete %s"><i class="fa-solid fa-trash"></i></button>',
            htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $label,
            $isDir ? 'true' : 'false',
            $label
        );
    }

    printf(
        '<li>%s<a href="%s" %s%s><span class="file-icon %s"><i class="%s"></i></span><span class="file-name">%s</span>%s</a>%s%s</li>' . PHP_EOL,
        $checkbox,
        htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $linkAttributes,
        $dataAttributes,
        htmlspecialchars($colorClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars($iconClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $label,
        $sizeHtml,
        $renameButton,
        $deleteButton
    );
}

/**
 * Check if directory has downloadable content (recursively)
 * @param string $dir Directory path
 * @param string $realRoot Real root path for security validation
 * @param bool $includeHiddenFiles Whether to include hidden files
 * @return bool True if directory contains downloadable files
 */
function hasDownloadableContent(string $dir, string $realRoot, bool $includeHiddenFiles = false): bool {
    $hasContent = false;
    
    $checkContent = function($checkDir) use (&$checkContent, $realRoot, &$hasContent, $includeHiddenFiles) {
        $handle = opendir($checkDir);
        if ($handle === false) {
            return;
        }
        
        while (($entry = readdir($handle)) !== false) {
            if ($hasContent) break; // Early exit if we found content
            
            // Skip hidden files based on configuration
            if (in_array($entry, ['.', '..', 'index.php'], true) || (!$includeHiddenFiles && $entry[0] === '.')) {
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

// ============================================================================
// REQUEST HANDLERS
// ============================================================================

/**
 * FAST PATH: Secure preview handler (for inline display in browser)
 * This is placed at the top for maximum performance - exits immediately without loading anything else
 * NOTE: MIME types array is intentionally duplicated here (also in getPreviewMimeType()) 
 *       to avoid loading any functions. This duplication is a performance optimization.
 *       When updating supported file types, update BOTH locations.
 */
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
    
    // Get file extension and determine MIME type
    // PERFORMANCE NOTE: MIME types array is intentionally duplicated here (also in getPreviewMimeType())
    // This duplication is a deliberate performance optimization to avoid function calls in the fast path.
    // The preview handler is placed at the very top of the file and exits immediately to minimize overhead.
    // IMPORTANT: When updating supported file types, update BOTH this array AND getPreviewMimeType()
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

/**
 * Secure rename handler
 */
if (isset($_POST['rename'])) {
    header('Content-Type: application/json');
    
    // Check if rename is enabled
    if (!$enableRename) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Rename functionality is disabled']);
        exit;
    }
    
    $oldPath = isset($_POST['old_path']) ? (string)$_POST['old_path'] : '';
    $newName = isset($_POST['new_name']) ? (string)$_POST['new_name'] : '';
    
    // Validate inputs
    if (empty($oldPath) || empty($newName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // Sanitize new name - prevent path traversal
    $newName = str_replace(['/', '\\', "\0"], '', $newName);
    $newName = trim($newName);
    
    // Validate new name is not empty after sanitization
    if (empty($newName) || $newName === '.' || $newName === '..') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file name']);
        exit;
    }
    
    // Check for dangerous extensions in new name
    $ext = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
    
    // Prevent changing extension to a dangerous one
    if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot rename to this file type']);
        exit;
    }
    
    // Resolve old path
    $fullOldPath = realpath($realRoot . $oldPath);
    
    // Validate old path exists and is within root
    if ($fullOldPath === false || strpos($fullOldPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File or folder not found']);
        exit;
    }
    
    // Ensure it's not the index.php file or a hidden file
    $oldBaseName = basename($fullOldPath);
    if ($oldBaseName === 'index.php' || $oldBaseName[0] === '.') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot rename this item']);
        exit;
    }
    
    // Prevent renaming to hidden file
    if ($newName[0] === '.') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot rename to hidden file']);
        exit;
    }
    
    // Build new path (same directory, new name)
    $parentDir = dirname($fullOldPath);
    $fullNewPath = $parentDir . DIRECTORY_SEPARATOR . $newName;
    
    // Normalize and validate new path is within root
    $realNewPath = realpath($parentDir);
    if ($realNewPath === false || strpos($realNewPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid operation']);
        exit;
    }
    
    // Reconstruct full new path with sanitized name
    $fullNewPath = $realNewPath . DIRECTORY_SEPARATOR . $newName;
    
    // Check if target already exists
    if (file_exists($fullNewPath)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A file or folder with this name already exists']);
        exit;
    }
    
    // Perform the rename
    if (@rename($fullOldPath, $fullNewPath)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to rename item']);
    }
    exit;
}

/**
 * Secure delete handler
 */
if (isset($_POST['delete'])) {
    header('Content-Type: application/json');
    
    // Check if delete is enabled
    if (!$enableDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Delete functionality is disabled']);
        exit;
    }
    
    $filePath = isset($_POST['file_path']) ? (string)$_POST['file_path'] : '';
    
    // Validate input
    if (empty($filePath)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // Resolve path
    $fullPath = realpath($realRoot . $filePath);
    
    // Validate path exists and is within root
    if ($fullPath === false || strpos($fullPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File or folder not found']);
        exit;
    }
    
    // Ensure it's not the index.php file or a hidden file
    $baseName = basename($fullPath);
    if ($baseName === 'index.php' || ($baseName !== '' && $baseName[0] === '.')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot delete this item']);
        exit;
    }
    
    // Recursive delete function for directories
    $deleteRecursive = function($path) use (&$deleteRecursive) {
        if (is_dir($path)) {
            $handle = opendir($path);
            if ($handle === false) {
                return false;
            }
            
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                
                // Skip index.php and hidden files within subdirectories
                if ($entry === 'index.php' || ($entry !== '' && $entry[0] === '.')) {
                    closedir($handle);
                    return false;
                }
                
                $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
                if (!$deleteRecursive($entryPath)) {
                    closedir($handle);
                    return false;
                }
            }
            closedir($handle);
            
            return @rmdir($path);
        } else {
            return @unlink($path);
        }
    };
    
    // Perform the delete
    if ($deleteRecursive($fullPath)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete item']);
    }
    exit;
}

/**
 * Secure batch delete handler
 */
if (isset($_POST['delete_batch'])) {
    header('Content-Type: application/json');
    
    // Check if delete is enabled
    if (!$enableDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Delete functionality is disabled']);
        exit;
    }
    
    $itemsJson = isset($_POST['items']) ? (string)$_POST['items'] : '';
    
    // Validate input
    if (empty($itemsJson)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // Decode items array
    $items = json_decode($itemsJson, true);
    if (!is_array($items) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid items list']);
        exit;
    }
    
    // Recursive delete function for directories
    $deleteRecursive = function($path) use (&$deleteRecursive) {
        if (is_dir($path)) {
            $handle = opendir($path);
            if ($handle === false) {
                return false;
            }
            
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                
                // Skip index.php and hidden files within subdirectories
                if ($entry === 'index.php' || ($entry !== '' && $entry[0] === '.')) {
                    closedir($handle);
                    return false;
                }
                
                $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
                if (!$deleteRecursive($entryPath)) {
                    closedir($handle);
                    return false;
                }
            }
            closedir($handle);
            
            return @rmdir($path);
        } else {
            return @unlink($path);
        }
    };
    
    $deletedCount = 0;
    $failedItems = [];
    
    // Process each item
    foreach ($items as $itemPath) {
        if (!is_string($itemPath) || empty($itemPath)) {
            continue;
        }
        
        // Resolve path
        $fullPath = realpath($realRoot . $itemPath);
        
        // Validate path exists and is within root
        if ($fullPath === false || strpos($fullPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
            $failedItems[] = basename($itemPath) . ' (not found)';
            continue;
        }
        
        // Ensure it's not the index.php file or a hidden file
        $baseName = basename($fullPath);
        if ($baseName === 'index.php' || ($baseName !== '' && $baseName[0] === '.')) {
            $failedItems[] = $baseName . ' (protected)';
            continue;
        }
        
        // Perform the delete
        if ($deleteRecursive($fullPath)) {
            $deletedCount++;
        } else {
            $failedItems[] = $baseName . ' (delete failed)';
        }
    }
    
    if ($deletedCount > 0 && empty($failedItems)) {
        echo json_encode(['success' => true, 'deleted' => $deletedCount]);
    } elseif ($deletedCount > 0 && !empty($failedItems)) {
        echo json_encode(['success' => true, 'deleted' => $deletedCount, 'failed' => $failedItems, 'message' => 'Some items could not be deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete items', 'failed' => $failedItems]);
    }
    exit;
}

/**
 * Secure batch download as zip handler
 */
if (isset($_GET['download_batch_zip'])) {
    // Check if batch download is enabled
    if (!$enableBatchDownload) {
        http_response_code(403);
        exit('Batch download functionality is disabled');
    }
    
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('ZIP support is not available');
    }
    
    $itemsJson = isset($_GET['items']) ? (string)$_GET['items'] : '';
    
    // Validate input
    if (empty($itemsJson)) {
        http_response_code(400);
        exit('Invalid parameters');
    }
    
    // Decode items array
    $items = json_decode($itemsJson, true);
    if (!is_array($items) || empty($items)) {
        http_response_code(400);
        exit('Invalid items list');
    }
    
    // Create a temporary zip file with random name
    $tempZip = tempnam(sys_get_temp_dir(), 'spfl_batch_' . bin2hex(random_bytes(8)) . '_');
    
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
    $addToZip = function($dir, $zipPath, &$count) use (&$addToZip, $zip, $realRoot, $zipCompressionLevel, $includeHiddenFiles) {
        $handle = opendir($dir);
        if ($handle === false) {
            return;
        }
        
        while (($entry = readdir($handle)) !== false) {
            // Skip hidden files based on configuration
            if (in_array($entry, ['.', '..', 'index.php'], true) || (!$includeHiddenFiles && $entry[0] === '.')) {
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
                    // Set compression level for this file if supported
                    if (method_exists($zip, 'setCompressionIndex')) {
                        try {
                            $fileIndex = $zip->numFiles - 1;
                            $zip->setCompressionIndex($fileIndex, ZipArchive::CM_DEFLATE, $zipCompressionLevel);
                        } catch (Exception $e) {
                            // Compression setting failed - file will use default compression
                            // Continue processing without failing the entire operation
                        }
                    }
                    $count++;
                }
            }
        }
        closedir($handle);
    };
    
    $fileCount = 0;
    
    // Process each selected item
    foreach ($items as $itemPath) {
        if (!is_string($itemPath) || empty($itemPath)) {
            continue;
        }
        
        // Resolve path
        $fullPath = realpath($realRoot . $itemPath);
        
        // Validate path exists and is within root
        if ($fullPath === false || strpos($fullPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
            continue;
        }
        
        // Ensure it's not the index.php file or a hidden file (based on configuration)
        $baseName = basename($fullPath);
        if ($baseName === 'index.php' || (!$includeHiddenFiles && $baseName !== '' && $baseName[0] === '.')) {
            continue;
        }
        
        if (is_dir($fullPath)) {
            // Add directory and its contents
            $zip->addEmptyDir($baseName);
            $addToZip($fullPath, $baseName, $fileCount);
        } else {
            // Block dangerous extensions
            $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
            if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
                continue;
            }
            
            // Add single file
            if ($zip->addFile($fullPath, $baseName)) {
                // Set compression level for this file if supported
                if (method_exists($zip, 'setCompressionIndex')) {
                    try {
                        $fileIndex = $zip->numFiles - 1;
                        $zip->setCompressionIndex($fileIndex, ZipArchive::CM_DEFLATE, $zipCompressionLevel);
                    } catch (Exception $e) {
                        // Compression setting failed - file will use default compression
                        // Continue processing without failing the entire operation
                    }
                }
                $fileCount++;
            }
        }
    }
    
    $zip->close();
    
    // If no files were added, clean up and exit
    if ($fileCount === 0) {
        @unlink($tempZip);
        http_response_code(404);
        exit('No files available to download');
    }
    
    // Generate filename for the zip
    $zipFilename = 'selected_files.zip';
    
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

/**
 * Secure download all as zip handler
 */
if (isset($_GET['download_all_zip'])) {
    // Check if download all is enabled
    if (!$enableDownloadAll) {
        http_response_code(403);
        exit('Download all functionality is disabled');
    }
    
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
    $addToZip = function($dir, $zipPath, &$count) use (&$addToZip, $zip, $realRoot, $zipCompressionLevel, $includeHiddenFiles) {
        $handle = opendir($dir);
        if ($handle === false) {
            return;
        }
        
        while (($entry = readdir($handle)) !== false) {
            // Skip hidden files based on configuration
            if (in_array($entry, ['.', '..', 'index.php'], true) || (!$includeHiddenFiles && $entry[0] === '.')) {
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
                    // Set compression level for this file if supported
                    if (method_exists($zip, 'setCompressionIndex')) {
                        try {
                            $fileIndex = $zip->numFiles - 1;
                            $zip->setCompressionIndex($fileIndex, ZipArchive::CM_DEFLATE, $zipCompressionLevel);
                        } catch (Exception $e) {
                            // Compression setting failed - file will use default compression
                            // Continue processing without failing the entire operation
                        }
                    }
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

/**
 * Secure download handler for individual files
 */
if (isset($_GET['download'])) {
    // Check if individual download is enabled
    if (!$enableIndividualDownload) {
        http_response_code(403);
        exit('Download functionality is disabled');
    }
    
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

// ============================================================================
// MAIN PAGE SETUP & PROCESSING
// ============================================================================

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

// Process current path
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

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous">
    <style nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
        /* ================================================================
           CSS VARIABLES & THEME
           ================================================================ */
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
        
        /* ================================================================
           BASE STYLES
           ================================================================ */
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
        
        /* ================================================================
           LAYOUT COMPONENTS
           ================================================================ */
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
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================
           TYPOGRAPHY
           ================================================================ */
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
        
        /* ================================================================
           NAVIGATION & BREADCRUMBS
           ================================================================ */
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
        
        /* ================================================================
           FILE LIST STYLES
           ================================================================ */
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
        
        /* Add right padding on hover when action buttons appear to prevent overlap with file size */
        <?php if ($enableRename && $enableDelete): ?>
        .file-list li:not(:first-child):hover a {
            padding-right: 104px; /* Space for both rename (36px) + delete (36px) + gaps */
        }
        <?php elseif ($enableRename || $enableDelete): ?>
        .file-list li:not(:first-child):hover a {
            padding-right: 60px; /* Space for one button (36px) + gap */
        }
        <?php endif; ?>
        
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
        
        .rename-btn {
            position: absolute;
            <?php if ($enableDelete): ?>
            right: 56px; /* Leave space for delete button */
            <?php else: ?>
            right: 12px; /* No delete button, position closer to edge */
            <?php endif; ?>
            top: 50%;
            transform: translateY(-50%);
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 6px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            z-index: 10;
        }
        
        .file-list li:hover .rename-btn {
            opacity: 1;
        }
        
        .rename-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-50%) scale(1.1);
        }
        
        .rename-btn:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .delete-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            z-index: 10;
        }
        
        .file-list li:hover .delete-btn {
            opacity: 1;
        }
        
        .delete-btn:hover {
            background: #c0392b;
            transform: translateY(-50%) scale(1.1);
        }
        
        .delete-btn:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .file-list li {
            position: relative;
        }
        
        /* ================================================================
           MULTI-SELECT CONTROLS
           ================================================================ */
        
        .select-all-container {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            font-weight: 600;
            color: var(--text);
            user-select: none;
            padding: 7px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            background: white;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }
        
        .select-all-container:hover {
            background-color: rgba(102, 126, 234, 0.08);
            border-color: var(--accent);
        }
        
        .select-all-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
            border-radius: 4px;
        }
        
        .multi-select-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .selected-count {
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            color: white;
            font-weight: 700;
            padding: 7px 12px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
            letter-spacing: 0.02em;
        }
        
        .batch-actions-container {
            display: flex;
            align-items: center;
            gap: 0;
            flex-wrap: wrap;
        }
        
        /* Style batch buttons as a group when visible */
        .batch-actions-container > .batch-download-btn:not(.batch-btn-hidden),
        .batch-actions-container > .batch-delete-btn:not(.batch-btn-hidden) {
            border-radius: 0;
            box-shadow: none;
        }
        
        .batch-actions-container > .batch-download-btn:not(.batch-btn-hidden):first-child,
        .batch-actions-container > .batch-delete-btn:not(.batch-btn-hidden):first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        
        .batch-actions-container > :is(.batch-download-btn, .batch-delete-btn):not(.batch-btn-hidden):last-of-type {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        
        /* Add visual separator between visible batch buttons */
        .batch-actions-container > .batch-download-btn:not(.batch-btn-hidden):not(:last-of-type),
        .batch-actions-container > .batch-delete-btn:not(.batch-btn-hidden):not(:last-of-type) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Add gap before download-all button when batch buttons are visible */
        .batch-actions-container > .download-all-btn {
            margin-left: 8px;
        }
        
        .batch-download-btn,
        .batch-delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            white-space: nowrap;
            letter-spacing: 0.01em;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .batch-download-btn {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: white;
        }
        
        .batch-download-btn:hover {
            background: linear-gradient(135deg, var(--accent-hover) 0%, var(--accent) 100%);
            filter: brightness(1.1);
        }
        
        .batch-download-btn:active {
            filter: brightness(0.95);
        }
        
        .batch-delete-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .batch-delete-btn:hover {
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            filter: brightness(1.1);
        }
        
        .batch-delete-btn:active {
            filter: brightness(0.95);
        }
        
        .batch-btn-hidden {
            display: none !important;
        }
        
        .multi-select-actions-hidden {
            display: none !important;
        }
        
        .item-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-right: 12px;
            margin-left: 12px;
            flex-shrink: 0;
            accent-color: var(--accent);
            border-radius: 4px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        
        .item-checkbox:hover {
            transform: scale(1.1);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .item-checkbox:checked {
            transform: scale(1.05);
        }
        
        .item-checkbox:active {
            transform: scale(0.95);
        }
        
        .file-list li {
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .file-list li.selected {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(85, 104, 211, 0.12) 100%);
            border-left: 4px solid var(--accent);
        }
        
        .file-list li.selected a {
            padding-left: 12px;
        }
        
        .file-list li a {
            flex: 1;
        }
        
        /* ================================================================
           STATISTICS & INFO DISPLAY
           ================================================================ */
        .stats-container {
            margin-top: 24px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }
        
        .stats-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .folder-file-count {
            color: var(--text);
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        
        .stats-actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        /* ================================================================
           FOOTER & BRANDING
           ================================================================ */
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
            color: rgba(255, 255, 255, 0.95);
        }
        
        footer a:hover {
            transform: scale(1.05);
        }
        
        /* ================================================================
           LOADING OVERLAY & ANIMATIONS
           ================================================================ */
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
        
        @media (prefers-reduced-motion: reduce) { 
            .loading-spinner { animation: none; border-top-color: var(--border); }
            .file-list a { transition: none; }
            .card { animation: none; }
        }
        
        @keyframes spin { 
            to { transform: rotate(360deg); } 
        }
        
        /* ================================================================
           FILE TYPE ICON COLORS
           ================================================================ */
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
        
        /* ================================================================
           BUTTONS & INTERACTIVE ELEMENTS
           ================================================================ */
        .download-all-btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 8px 14px; 
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            font-weight: 700; 
            text-decoration: none; 
            cursor: pointer; 
            transition: all 0.25s ease; 
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .download-all-btn:hover { 
            background: linear-gradient(135deg, var(--accent-hover) 0%, var(--accent) 100%);
            filter: brightness(1.1);
        }
        
        .download-all-btn:active {
            filter: brightness(0.95);
        }
        
        .download-all-btn i { 
            font-size: 0.9rem; 
        }
        
        /* ================================================================
           RESPONSIVE DESIGN - MEDIA QUERIES
           ================================================================ */
        
        /* Mobile phones (portrait) */
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
            
            /* On mobile: icon and size on first row, name wraps below */
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
            
            .rename-btn {
                opacity: 1;
                <?php if ($enableDelete): ?>
                right: 48px; /* Leave space for delete button */
                <?php else: ?>
                right: 8px; /* No delete button, position closer to edge */
                <?php endif; ?>
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }
            
            .delete-btn {
                opacity: 1;
                right: 8px;
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }
            
            /* Adjust padding for mobile when action buttons are present */
            <?php if ($enableRename && $enableDelete): ?>
            .file-list li:not(:first-child) a {
                padding-right: 92px; /* Smaller buttons on mobile: 32px + 32px + gaps */
            }
            <?php elseif ($enableRename || $enableDelete): ?>
            .file-list li:not(:first-child) a {
                padding-right: 52px; /* Space for one smaller button (32px) + gap */
            }
            <?php endif; ?>
            
            .rename-modal-content,
            .delete-modal-content {
                padding: 24px 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 16px;
                padding: 18px 20px;
            }
            
            .folder-file-count {
                grid-column: 1;
            }
            
            .stats-actions-row {
                grid-column: 1;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding-top: 12px;
            }
            
            .stats-top-row {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .select-all-container {
                justify-content: center;
            }
            
            .multi-select-actions {
                justify-content: center;
            }
            
            .batch-actions-container {
                width: 100%;
                flex-direction: column;
            }
            
            .batch-download-btn,
            .batch-delete-btn,
            .download-all-btn {
                width: 100%;
                justify-content: center;
                padding: 12px 16px;
            }
        }
        
        /* Mobile phones (landscape) and small tablets */
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
            
            .stats-actions-row {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            
            .stats-top-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .batch-actions-container {
                width: 100%;
                flex-direction: column;
            }
            
            .batch-download-btn,
            .batch-delete-btn {
                width: 100%;
                justify-content: center;
                padding: 11px 16px;
            }
        }
        
        /* Tablets (portrait) */
        @media (min-width: 769px) and (max-width: 1024px) {
            body {
                padding: 24px 20px;
            }
            
            .card {
                padding: 28px 24px;
            }
            
            /* Use 85% width for better readability on tablet screens */
            .container {
                max-width: 85%;
            }
        }
        
        /* Large screens */
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
        
        /* Touch device optimizations */
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
        }
        
        /* ================================================================
           PREVIEW TOOLTIPS
           ================================================================ */
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
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* Hide preview on mobile/touch devices to avoid conflicts */
        @media (hover: none) and (pointer: coarse) {
            .preview-tooltip {
                display: none;
            }
        }
        
        /* ================================================================
           RENAME MODAL
           ================================================================ */
        .rename-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            padding: 20px;
        }
        
        .rename-modal.active {
            display: flex;
        }
        
        .rename-modal-content {
            background: white;
            border-radius: 12px;
            padding: 28px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .rename-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .rename-modal-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 20px;
            word-break: break-word;
        }
        
        .rename-modal-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            margin-bottom: 20px;
            transition: border-color 0.2s ease;
        }
        
        .rename-modal-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .rename-modal-error {
            background: #fee;
            color: #c00;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            display: none;
        }
        
        .rename-modal-error.active {
            display: block;
        }
        
        .rename-modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .rename-modal-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rename-modal-btn-cancel {
            background: var(--border);
            color: var(--text);
        }
        
        .rename-modal-btn-cancel:hover {
            background: #cbd5e0;
        }
        
        .rename-modal-btn-confirm {
            background: var(--accent);
            color: white;
        }
        
        .rename-modal-btn-confirm:hover {
            background: var(--accent-hover);
        }
        
        .rename-modal-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* ================================================================
           DELETE MODAL
           ================================================================ */
        .delete-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            padding: 20px;
        }
        
        .delete-modal.active {
            display: flex;
        }
        
        .delete-modal-content {
            background: white;
            border-radius: 12px;
            padding: 28px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        .delete-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #e74c3c;
        }
        
        .delete-modal-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 16px;
            word-break: break-word;
        }
        
        .delete-modal-warning {
            background: #fff3cd;
            color: #856404;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid #ffeaa7;
        }
        
        .delete-modal-error {
            background: #fee;
            color: #c00;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            display: none;
        }
        
        .delete-modal-error.active {
            display: block;
        }
        
        .delete-modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .delete-modal-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .delete-modal-btn-cancel {
            background: var(--border);
            color: var(--text);
        }
        
        .delete-modal-btn-cancel:hover {
            background: #cbd5e0;
        }
        
        .delete-modal-btn-confirm {
            background: #e74c3c;
            color: white;
        }
        
        .delete-modal-btn-confirm:hover {
            background: #c0392b;
        }
        
        .delete-modal-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
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
        
        /* ================================================================
           PAGINATION CONTROLS
           ================================================================ */
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
        
        /* Responsive pagination */
        @media (max-width: 480px) {
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
        
        @media (min-width: 481px) and (max-width: 768px) {
            .pagination {
                padding: 18px 16px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <?php if (!empty($subtitle)): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
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

            <ul class="file-list">
                <?php
                if (!$isValidPath) {
                    echo '<li>Invalid path</li>';
                } else {
                    // Show parent directory link if not at root
                    if ($currentPath) {
                        $parentPath = dirname($currentPath);
                        printf('<li><a href="?path=%s" class="dir-link"><span class="file-icon icon-folder"><i class="fa-solid fa-arrow-up"></i></span><span class="file-name">..</span></a></li>' . PHP_EOL, $parentPath ? rawurlencode($parentPath) : '');
                    }

                    $dirs = [];
                    $files = [];
                    $totalSize = 0;

                    if ($handle = opendir($basePath)) {
                        while (($entry = readdir($handle)) !== false) {
                            // Skip hidden files based on configuration
                            if (in_array($entry, ['.', '..', 'index.php'], true) || (!$includeHiddenFiles && $entry[0] === '.')) {
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

                    // Pagination logic: combine dirs and files for pagination
                    // Note: $dirs is an array of directory name strings
                    // Note: $files is an array of ['name' => string, 'size' => int] arrays
                    $totalItems = count($dirs) + count($files);
                    
                    // Store if we have items for later use in stats container
                    $hasItemsToSelect = $totalItems > 0;
                    
                    // Only show pagination if items exceed the threshold (e.g., 26+ items when threshold is 25)
                    $totalPages = ($totalItems > $paginationThreshold) ? (int)ceil($totalItems / $paginationThreshold) : 1;
                    
                    // Ensure current page is within valid range
                    $currentPage = max(1, min($currentPage, $totalPages));
                    
                    // Calculate pagination offsets
                    $itemsPerPage = $paginationThreshold;
                    $offset = ($currentPage - 1) * $itemsPerPage;
                    
                    // Merge dirs and files into a single array for pagination
                    // Convert dirs (strings) to standardized format: ['type' => 'dir', 'name' => string, 'size' => 0]
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
                            renderItem($item['name'], true, $currentPath, 0, $enableRename, $enableDelete, ($enableBatchDownload || $enableDelete), $showFileSize, $enableIndividualDownload);
                        } else {
                            renderItem($item['name'], false, $currentPath, $item['size'], $enableRename, $enableDelete, ($enableBatchDownload || $enableDelete), $showFileSize, $enableIndividualDownload);
                        }
                    }
                }
                ?>
            </ul>

            <?php
            // Display pagination controls if needed
            if ($isValidPath && $totalPages > 1) {
                // Build base URL for pagination links (preserve current path)
                $baseUrl = '?';
                if ($currentPath) {
                    $baseUrl .= 'path=' . rawurlencode($currentPath) . '&';
                }
                
                echo '<div class="pagination" role="navigation" aria-label="Pagination">';
                
                // Previous button
                if ($currentPage > 1) {
                    $prevUrl = $baseUrl . 'page=' . ($currentPage - 1);
                    echo '<a href="' . htmlspecialchars($prevUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-btn pagination-prev" aria-label="Previous page">';
                    echo '<i class="fa-solid fa-chevron-left"></i> Previous';
                    echo '</a>';
                } else {
                    echo '<span class="pagination-btn pagination-prev pagination-disabled" aria-disabled="true">';
                    echo '<i class="fa-solid fa-chevron-left"></i> Previous';
                    echo '</span>';
                }
                
                // Page numbers
                echo '<div class="pagination-numbers">';
                
                // Calculate range of pages to show
                $maxPagesToShow = 7; // Show up to 7 page numbers
                $halfRange = floor($maxPagesToShow / 2);
                $startPage = max(1, $currentPage - $halfRange);
                $endPage = min($totalPages, $currentPage + $halfRange);
                
                // Adjust if we're near the start or end
                if ($currentPage <= $halfRange) {
                    $endPage = min($totalPages, $maxPagesToShow);
                } elseif ($currentPage >= $totalPages - $halfRange) {
                    $startPage = max(1, $totalPages - $maxPagesToShow + 1);
                }
                
                // Show first page if not in range
                if ($startPage > 1) {
                    $pageUrl = $baseUrl . 'page=1';
                    echo '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-number" aria-label="Go to page 1">1</a>';
                    if ($startPage > 2) {
                        echo '<span class="pagination-ellipsis" aria-hidden="true">...</span>';
                    }
                }
                
                // Show page numbers in range
                for ($i = $startPage; $i <= $endPage; $i++) {
                    if ($i === $currentPage) {
                        echo '<span class="pagination-number pagination-current" aria-current="page" aria-label="Current page, page ' . $i . '">' . $i . '</span>';
                    } else {
                        $pageUrl = $baseUrl . 'page=' . $i;
                        echo '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-number" aria-label="Go to page ' . $i . '">' . $i . '</a>';
                    }
                }
                
                // Show last page if not in range
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span class="pagination-ellipsis" aria-hidden="true">...</span>';
                    }
                    $pageUrl = $baseUrl . 'page=' . $totalPages;
                    echo '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-number" aria-label="Go to page ' . $totalPages . '">' . $totalPages . '</a>';
                }
                
                echo '</div>';
                
                // Next button
                if ($currentPage < $totalPages) {
                    $nextUrl = $baseUrl . 'page=' . ($currentPage + 1);
                    echo '<a href="' . htmlspecialchars($nextUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="pagination-btn pagination-next" aria-label="Next page">';
                    echo 'Next <i class="fa-solid fa-chevron-right"></i>';
                    echo '</a>';
                } else {
                    echo '<span class="pagination-btn pagination-next pagination-disabled" aria-disabled="true">';
                    echo 'Next <i class="fa-solid fa-chevron-right"></i>';
                    echo '</span>';
                }
                
                echo '</div>'; // end pagination
            }
            ?>

            <?php
            // Unified stats container with folder/file count and download button
            if ($isValidPath) {
                // Build stats HTML conditionally based on configuration
                $statsHtml = '';
                if ($showFolderFileCount) {
                    $statsHtml .= count($dirs) . ' folder' . (count($dirs) !== 1 ? 's' : '') . ', ';
                    $statsHtml .= count($files) . ' file' . (count($files) !== 1 ? 's' : '');
                    if ($showTotalSize && $totalSize > 0) {
                        $statsHtml .= ' (' . htmlspecialchars(formatFileSize($totalSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' total)';
                    }
                }
                
                // Determine if stats container should be displayed
                $hasStatsToShow = !empty($statsHtml);
                $hasBatchActions = isset($hasItemsToSelect) && $hasItemsToSelect && ($enableBatchDownload || $enableDelete);
                $hasDownloadAll = $enableDownloadAll && hasDownloadableContent($basePath, $realRoot, $includeHiddenFiles);
                $showStatsContainer = $hasStatsToShow || $hasBatchActions || $hasDownloadAll;
                
                if ($showStatsContainer) {
                    echo '<div class="stats-container">';
                    
                    // First row: folder/file count and Select All checkbox
                    echo '<div class="stats-top-row">';
                    if (!empty($statsHtml)) {
                        echo '<div class="folder-file-count">' . $statsHtml . '</div>';
                    }
                    
                    // Multi-select checkbox (when items are available and batch download or delete is enabled)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && ($enableBatchDownload || $enableDelete)) {
                        echo '<label class="select-all-container">';
                        echo '<input type="checkbox" id="selectAllCheckbox" aria-label="Select all items">';
                        echo '<span>Select All</span>';
                        echo '</label>';
                    }
                    echo '</div>'; // end stats-top-row
                    
                    // Second row: selected count and action buttons
                    echo '<div class="stats-actions-row">';
                    
                    // Selected count display (when items are available and batch download or delete is enabled)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && ($enableBatchDownload || $enableDelete)) {
                        echo '<div class="multi-select-actions multi-select-actions-hidden" id="multiSelectActions">';
                        echo '<span class="selected-count" id="selectedCount">0 selected</span>';
                        echo '</div>';
                    }
                    
                    // Batch action buttons container
                    echo '<div class="batch-actions-container">';
                    
                    // Batch download button (hidden by default, shown when items selected)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && $enableBatchDownload) {
                        echo '<button class="batch-download-btn batch-btn-hidden" id="batchDownloadBtn" title="Download selected as ZIP">';
                        echo '<i class="fa-solid fa-download"></i> Download Selected';
                        echo '</button>';
                    }
                    
                    // Batch delete button (hidden by default, shown when items selected)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && $enableDelete) {
                        echo '<button class="batch-delete-btn batch-btn-hidden" id="batchDeleteBtn" title="Delete selected items">';
                        echo '<i class="fa-solid fa-trash"></i> Delete Selected';
                        echo '</button>';
                    }
                    
                    // Show download all button if enabled and content check already passed
                    if ($hasDownloadAll) {
                        $downloadAllUrl = '?download_all_zip=1';
                        if ($currentPath) {
                            $downloadAllUrl .= '&path=' . rawurlencode($currentPath);
                        }
                        echo '<a href="' . htmlspecialchars($downloadAllUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="download-all-btn" target="_blank" rel="noopener noreferrer">';
                        echo '<i class="fa-solid fa-download"></i>';
                        echo 'Download All as ZIP';
                        echo '</a>';
                    }
                    
                    echo '</div>'; // end batch-actions-container
                    echo '</div>'; // end stats-actions-row
                    echo '</div>'; // end stats-container
                }
            }
            ?>
        </div>

        <?php if (!empty($footer)): ?>
        <footer><?php echo htmlspecialchars($footer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></footer>
        <?php endif; ?>

        <footer>
            <a href="https://github.com/BlindTrevor/SimplePhpFileLister/releases" target="_blank">
                <img src="https://img.shields.io/badge/Created_by_Blind_Trevor-Simple_PHP_File_Lister_<?php echo htmlspecialchars(APP_VERSION, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>-magenta" alt="GitHub"/>
            </a>
        </footer>
    </div>

    <!-- Rename Modal -->
    <div class="rename-modal" id="renameModal" role="dialog" aria-labelledby="renameModalTitle" aria-modal="true">
        <div class="rename-modal-content">
            <h2 class="rename-modal-title" id="renameModalTitle">Rename</h2>
            <p class="rename-modal-subtitle" id="renameModalSubtitle"></p>
            <div class="rename-modal-error" id="renameModalError"></div>
            <input type="text" class="rename-modal-input" id="renameModalInput" placeholder="Enter new name" aria-label="New name">
            <div class="rename-modal-buttons">
                <button class="rename-modal-btn rename-modal-btn-cancel" id="renameModalCancel">Cancel</button>
                <button class="rename-modal-btn rename-modal-btn-confirm" id="renameModalConfirm">Rename</button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="delete-modal" id="deleteModal" role="dialog" aria-labelledby="deleteModalTitle" aria-modal="true">
        <div class="delete-modal-content">
            <h2 class="delete-modal-title" id="deleteModalTitle">Confirm Delete</h2>
            <p class="delete-modal-subtitle" id="deleteModalSubtitle"></p>
            <div class="delete-modal-warning">
                <strong>⚠️ Warning:</strong> This action cannot be undone. The item will be permanently deleted.
            </div>
            <div class="delete-modal-error" id="deleteModalError"></div>
            <div class="delete-modal-buttons">
                <button class="delete-modal-btn delete-modal-btn-cancel" id="deleteModalCancel">Cancel</button>
                <button class="delete-modal-btn delete-modal-btn-confirm" id="deleteModalConfirm">Delete</button>
            </div>
        </div>
    </div>

    <div class="loading-overlay" aria-hidden="true">
        <div role="status" aria-live="polite" aria-label="Loading">
            <div class="loading-spinner" aria-hidden="true"></div>
            <div class="loading-text">Loading directory…</div>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
        (function() {
            const overlay = document.querySelector('.loading-overlay');

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

                // Show loading overlay for directory navigation and pagination
                const isDirLink = a.classList.contains('dir-link') && !a.hasAttribute('download');
                const isPaginationLink = a.classList.contains('pagination-btn') || a.classList.contains('pagination-number');
                
                if (isDirLink || isPaginationLink) showOverlay();
            }, { capture: true });

            window.addEventListener('beforeunload', showOverlay);
            
            // Preview tooltip functionality
            (function() {
                // Skip on touch-only devices (mobile/tablet)
                // Allow on hybrid devices (laptops with touchscreens) by checking if hover is supported
                const isTouchOnly = ('ontouchstart' in window || navigator.maxTouchPoints > 0) && 
                                   !window.matchMedia('(hover: hover) and (pointer: fine)').matches;
                
                if (isTouchOnly) {
                    console.log('[Preview] Disabled: Touch-only device detected');
                    return;
                }
                
                console.log('[Preview] Initialized: Preview functionality enabled');
                console.log('[Preview] Version: mouseenter/mouseleave (event bubbling fix)');
                
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
                    
                    // Ensure tooltip doesn't go off left or top edge
                    x = Math.max(padding, x);
                    y = Math.max(padding, y);
                    
                    tooltip.style.left = x + 'px';
                    tooltip.style.top = y + 'px';
                }
                
                function showPreview(link, e) {
                    const previewType = link.dataset.preview;
                    const filePath = link.dataset.filePath;
                    
                    console.log('[Preview] showPreview() called:', {
                        previewType: previewType,
                        filePath: filePath,
                        hasDataset: !!link.dataset,
                        linkElement: link.tagName
                    });
                    
                    if (!previewType || !filePath) {
                        console.warn('[Preview] Missing data attributes:', {
                            previewType: previewType,
                            filePath: filePath
                        });
                        return;
                    }
                    
                    createTooltip();
                    tooltip.innerHTML = '<div class="preview-loading">⏳ Loading preview...</div>';
                    positionTooltip(e);
                    
                    // Show tooltip after a brief delay
                    setTimeout(() => {
                        tooltip.classList.add('visible');
                        console.log('[Preview] Tooltip made visible');
                    }, 50);
                    
                    const previewUrl = '?preview=' + encodeURIComponent(filePath);
                    console.log('[Preview] Fetching from URL:', previewUrl);
                    
                    if (previewType === 'image') {
                        console.log('[Preview] Creating image element for:', filePath);
                        const img = new Image();
                        const startTime = Date.now();
                        
                        img.onload = function() {
                            const loadTime = Date.now() - startTime;
                            console.log('[Preview] Image loaded successfully in ' + loadTime + 'ms:', {
                                filePath: filePath,
                                width: img.naturalWidth,
                                height: img.naturalHeight,
                                stillCurrent: currentPreview === link
                            });
                            
                            if (currentPreview === link) {
                                tooltip.innerHTML = '';
                                tooltip.appendChild(img);
                                positionTooltip(e);
                            } else {
                                console.log('[Preview] Image loaded but preview already moved to another file');
                            }
                        };
                        
                        img.onerror = function(error) {
                            const loadTime = Date.now() - startTime;
                            console.error('[Preview] Image failed to load after ' + loadTime + 'ms:', {
                                filePath: filePath,
                                previewUrl: previewUrl,
                                error: error
                            });
                            
                            if (currentPreview === link) {
                                tooltip.innerHTML = '<div class="preview-error">❌ Unable to load preview</div>';
                            }
                        };
                        
                        img.src = previewUrl;
                        img.alt = 'Preview';
                    } else if (previewType === 'video') {
                        const video = document.createElement('video');
                        video.controls = false;
                        video.muted = true;
                        video.autoplay = false;
                        video.preload = 'metadata';
                        video.onloadedmetadata = function() {
                            console.log('[Preview] Video metadata loaded:', filePath);
                            if (currentPreview === link) {
                                tooltip.innerHTML = '';
                                tooltip.appendChild(video);
                                positionTooltip(e);
                            }
                        };
                        video.onerror = function() {
                            console.error('[Preview] Video failed to load:', filePath);
                            if (currentPreview === link) {
                                tooltip.innerHTML = '<div class="preview-error">❌ Unable to load video preview</div>';
                            }
                        };
                        video.src = previewUrl;
                    } else if (previewType === 'audio') {
                        console.log('[Preview] Creating audio element for:', filePath);
                        const audio = document.createElement('audio');
                        audio.controls = true;
                        audio.preload = 'metadata';
                        audio.onloadedmetadata = function() {
                            console.log('[Preview] Audio metadata loaded:', filePath);
                            if (currentPreview === link) {
                                tooltip.innerHTML = '';
                                tooltip.appendChild(audio);
                                positionTooltip(e);
                            }
                        };
                        audio.onerror = function() {
                            console.error('[Preview] Audio failed to load:', filePath);
                            if (currentPreview === link) {
                                tooltip.innerHTML = '<div class="preview-error">❌ Unable to load audio preview</div>';
                            }
                        };
                        audio.src = previewUrl;
                    } else if (previewType === 'pdf') {
                        console.log('[Preview] PDF preview requested (showing message):', filePath);
                        // PDF inline preview in a small tooltip is impractical due to size/readability
                        // Instead, show a helpful message prompting user to click to view full document
                        tooltip.innerHTML = '<div class="preview-error">PDF preview not available<br>(Click to view)</div>';
                    }
                }
                
                function hidePreview() {
                    console.log('[Preview] hidePreview() called');
                    if (tooltip) {
                        tooltip.classList.remove('visible');
                        currentPreview = null;
                    }
                }
                
                // Use mouseenter/mouseleave to avoid child element interference
                // We need to attach these after DOM is loaded since they're not delegated
                function attachPreviewListeners() {
                    const links = document.querySelectorAll('a[data-preview]');
                    console.log('[Preview] Attaching listeners to ' + links.length + ' previewable links');
                    
                    links.forEach(link => {
                        link.addEventListener('mouseenter', function(e) {
                            console.log('[Preview] Mouseenter detected on link:', {
                                fileName: link.querySelector('.file-name')?.textContent || 'unknown',
                                previewType: link.dataset.preview,
                                filePath: link.dataset.filePath,
                                targetElement: e.target.tagName + (e.target.className ? '.' + e.target.className.split(' ').join('.') : ''),
                                currentTargetElement: e.currentTarget.tagName
                            });
                            
                            // Clear any pending hide
                            clearTimeout(hideTimeout);
                            
                            // If already showing for this exact link, don't restart
                            if (currentPreview === link) {
                                console.log('[Preview] Already showing preview for this link');
                                return;
                            }
                            
                            // If showing preview for a different link, hide it immediately and show new one
                            if (currentPreview && currentPreview !== link) {
                                console.log('[Preview] Switching preview to different file');
                                hidePreview();
                            }
                            
                            currentPreview = link;
                            
                            // Reduced delay from 500ms to 200ms for faster response
                            clearTimeout(showTimeout);
                            console.log('[Preview] Starting 200ms delay timer before showing preview');
                            showTimeout = setTimeout(() => {
                                if (currentPreview === link) {
                                    console.log('[Preview] 200ms delay complete, calling showPreview()');
                                    showPreview(link, e);
                                } else {
                                    console.log('[Preview] 200ms delay complete but preview target changed');
                                }
                            }, 200);
                        });
                        
                        link.addEventListener('mouseleave', function(e) {
                            console.log('[Preview] Mouseleave detected on link:', {
                                fileName: link.querySelector('.file-name')?.textContent || 'unknown',
                                targetElement: e.target.tagName + (e.target.className ? '.' + e.target.className.split(' ').join('.') : ''),
                                currentTargetElement: e.currentTarget.tagName
                            });
                            
                            // Clear any pending show
                            clearTimeout(showTimeout);
                            
                            // Increased delay from 100ms to 300ms to be more forgiving
                            hideTimeout = setTimeout(() => {
                                hidePreview();
                            }, 300);
                        });
                    });
                }
                
                // Attach listeners after DOM is ready
                attachPreviewListeners();
                
                // Update tooltip position on mousemove
                document.addEventListener('mousemove', function(e) {
                    if (tooltip && tooltip.classList.contains('visible')) {
                        positionTooltip(e);
                    }
                });
            })();
            
            // Rename functionality
            (function() {
                const modal = document.getElementById('renameModal');
                const input = document.getElementById('renameModalInput');
                const subtitle = document.getElementById('renameModalSubtitle');
                const error = document.getElementById('renameModalError');
                const cancelBtn = document.getElementById('renameModalCancel');
                const confirmBtn = document.getElementById('renameModalConfirm');
                
                if (!modal) return;
                
                let currentFilePath = '';
                let currentFileName = '';
                let isDirectory = false;
                
                function showError(message) {
                    error.textContent = message;
                    error.classList.add('active');
                }
                
                function hideError() {
                    error.classList.remove('active');
                }
                
                function openModal(filePath, fileName, isDir) {
                    currentFilePath = filePath;
                    currentFileName = fileName;
                    isDirectory = isDir;
                    
                    const itemType = isDir ? 'folder' : 'file';
                    subtitle.textContent = 'Renaming ' + itemType + ': ' + fileName;
                    input.value = fileName;
                    hideError();
                    
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                    
                    // Focus input and select filename without extension for files
                    setTimeout(() => {
                        input.focus();
                        if (!isDir) {
                            const lastDot = fileName.lastIndexOf('.');
                            if (lastDot > 0) {
                                input.setSelectionRange(0, lastDot);
                            } else {
                                input.select();
                            }
                        } else {
                            input.select();
                        }
                    }, 50);
                }
                
                function closeModal() {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                    currentFilePath = '';
                    currentFileName = '';
                    isDirectory = false;
                }
                
                function performRename() {
                    const newName = input.value.trim();
                    
                    if (!newName) {
                        showError('Please enter a name');
                        return;
                    }
                    
                    if (newName === currentFileName) {
                        closeModal();
                        return;
                    }
                    
                    // Disable buttons during operation
                    confirmBtn.disabled = true;
                    cancelBtn.disabled = true;
                    hideError();
                    
                    // Send rename request
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'rename=1&old_path=' + encodeURIComponent(currentFilePath) + '&new_name=' + encodeURIComponent(newName)
                    })
                    .then(response => {
                        // Check if response is ok before parsing JSON
                        if (!response.ok) {
                            return response.json().then(data => {
                                throw new Error(data.error || 'Failed to rename');
                            }).catch(() => {
                                throw new Error('Failed to rename');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Reload page to show renamed item
                            window.location.reload();
                        } else {
                            showError(data.error || 'Failed to rename');
                            confirmBtn.disabled = false;
                            cancelBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        showError(err.message || 'An error occurred. Please try again.');
                        confirmBtn.disabled = false;
                        cancelBtn.disabled = false;
                        console.error('Rename error:', err);
                    });
                }
                
                // Event listeners
                document.addEventListener('click', function(e) {
                    const renameBtn = e.target.closest('.rename-btn');
                    if (renameBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const filePath = renameBtn.dataset.filePath;
                        const fileName = renameBtn.dataset.fileName;
                        const isDir = renameBtn.dataset.isDir === 'true';
                        
                        openModal(filePath, fileName, isDir);
                    }
                });
                
                cancelBtn.addEventListener('click', closeModal);
                
                confirmBtn.addEventListener('click', performRename);
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        performRename();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeModal();
                    }
                });
                
                // Close modal when clicking outside
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            })();
            
            // Multi-select functionality
            (function() {
                const selectAllCheckbox = document.getElementById('selectAllCheckbox');
                const multiSelectActions = document.getElementById('multiSelectActions');
                const selectedCountEl = document.getElementById('selectedCount');
                const batchDownloadBtn = document.getElementById('batchDownloadBtn');
                const batchDeleteBtn = document.getElementById('batchDeleteBtn');
                
                if (!selectAllCheckbox) return;
                
                let selectedItems = new Set();
                
                /**
                 * Format file size in human-readable format
                 * @param {number} bytes - File size in bytes
                 * @return {string} Formatted file size
                 */
                function formatFileSize(bytes) {
                    if (bytes === 0) {
                        return '0 B';
                    }
                    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(1024));
                    const actualI = Math.min(i, units.length - 1);
                    const size = bytes / Math.pow(1024, actualI);
                    
                    if (actualI === 0) {
                        return Math.floor(size) + ' B';
                    }
                    return size.toFixed(2) + ' ' + units[actualI];
                }
                
                function updateUI() {
                    const count = selectedItems.size;
                    
                    // Query all checkboxes once and calculate totals in single pass
                    const allCheckboxes = document.querySelectorAll('.item-checkbox');
                    let totalSize = 0;
                    let checkedCount = 0;
                    
                    allCheckboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            checkedCount++;
                            const itemSize = parseInt(checkbox.dataset.itemSize || '0', 10);
                            totalSize += itemSize;
                        }
                    });
                    
                    if (count > 0) {
                        multiSelectActions.classList.remove('multi-select-actions-hidden');
                        
                        // Update text with count and total size
                        let displayText = count + ' selected';
                        if (totalSize > 0) {
                            displayText += ' (' + formatFileSize(totalSize) + ')';
                        }
                        selectedCountEl.textContent = displayText;
                        
                        // Show batch action buttons by removing hidden class
                        if (batchDownloadBtn) batchDownloadBtn.classList.remove('batch-btn-hidden');
                        if (batchDeleteBtn) batchDeleteBtn.classList.remove('batch-btn-hidden');
                    } else {
                        multiSelectActions.classList.add('multi-select-actions-hidden');
                        // Hide batch action buttons by adding hidden class
                        if (batchDownloadBtn) batchDownloadBtn.classList.add('batch-btn-hidden');
                        if (batchDeleteBtn) batchDeleteBtn.classList.add('batch-btn-hidden');
                    }
                    
                    // Update select all checkbox state
                    selectAllCheckbox.checked = checkedCount > 0 && checkedCount === allCheckboxes.length;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
                }
                
                function getSelectedPaths() {
                    return Array.from(selectedItems);
                }
                
                // Select all/deselect all
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.item-checkbox');
                    const shouldCheck = this.checked;
                    
                    selectedItems.clear();
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = shouldCheck;
                        const listItem = checkbox.closest('li');
                        if (listItem) {
                            if (shouldCheck) {
                                listItem.classList.add('selected');
                                selectedItems.add(checkbox.dataset.itemPath);
                            } else {
                                listItem.classList.remove('selected');
                            }
                        }
                    });
                    
                    updateUI();
                });
                
                // Individual checkbox change
                document.addEventListener('change', function(e) {
                    if (e.target.classList.contains('item-checkbox')) {
                        const path = e.target.dataset.itemPath;
                        const listItem = e.target.closest('li');
                        
                        if (e.target.checked) {
                            selectedItems.add(path);
                            if (listItem) listItem.classList.add('selected');
                        } else {
                            selectedItems.delete(path);
                            if (listItem) listItem.classList.remove('selected');
                        }
                        
                        updateUI();
                    }
                });
                
                // Batch download
                if (batchDownloadBtn) {
                    batchDownloadBtn.addEventListener('click', function() {
                        const paths = getSelectedPaths();
                        
                        if (paths.length === 0) {
                            alert('Please select at least one item');
                            return;
                        }
                        
                        // Create download URL with items parameter
                        const itemsJson = JSON.stringify(paths);
                        const url = '?download_batch_zip=1&items=' + encodeURIComponent(itemsJson);
                        
                        // Open in new tab to trigger download
                        window.open(url, '_blank');
                    });
                }
                
                // Batch delete
                if (batchDeleteBtn) {
                    batchDeleteBtn.addEventListener('click', function() {
                        const paths = getSelectedPaths();
                        
                        if (paths.length === 0) {
                            alert('Please select at least one item');
                            return;
                        }
                        
                        const itemCount = paths.length;
                        const confirmMessage = 'Are you sure you want to delete ' + itemCount + ' item' + (itemCount > 1 ? 's' : '') + '?\\n\\nThis action cannot be undone.';
                        
                        if (!confirm(confirmMessage)) {
                            return;
                        }
                        
                        // Disable button during operation
                        batchDeleteBtn.disabled = true;
                        batchDeleteBtn.textContent = 'Deleting...';
                        
                        // Send delete request
                        const itemsJson = JSON.stringify(paths);
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'delete_batch=1&items=' + encodeURIComponent(itemsJson)
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(data => {
                                    throw new Error(data.error || 'Failed to delete items');
                                }).catch(() => {
                                    throw new Error('Failed to delete items');
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Show success message if some items failed
                                if (data.failed && data.failed.length > 0) {
                                    alert(data.message + '\\n\\nFailed items:\\n' + data.failed.join('\\n'));
                                }
                                
                                // Reload page to show updated list
                                window.location.reload();
                            } else {
                                let errorMsg = data.error || 'Failed to delete items';
                                if (data.failed && data.failed.length > 0) {
                                    errorMsg += '\\n\\nFailed items:\\n' + data.failed.join('\\n');
                                }
                                alert(errorMsg);
                                batchDeleteBtn.disabled = false;
                                batchDeleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Selected';
                            }
                        })
                        .catch(err => {
                            alert(err.message || 'An error occurred. Please try again.');
                            batchDeleteBtn.disabled = false;
                            batchDeleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Selected';
                            console.error('Batch delete error:', err);
                        });
                    });
                }
            })();
            
            // Delete functionality
            (function() {
                const modal = document.getElementById('deleteModal');
                const subtitle = document.getElementById('deleteModalSubtitle');
                const error = document.getElementById('deleteModalError');
                const cancelBtn = document.getElementById('deleteModalCancel');
                const confirmBtn = document.getElementById('deleteModalConfirm');
                
                if (!modal) return;
                
                let currentFilePath = '';
                let currentFileName = '';
                let isDirectory = false;
                
                function showError(message) {
                    error.textContent = message;
                    error.classList.add('active');
                }
                
                function hideError() {
                    error.classList.remove('active');
                }
                
                function openModal(filePath, fileName, isDir) {
                    currentFilePath = filePath;
                    currentFileName = fileName;
                    isDirectory = isDir;
                    
                    const itemType = isDir ? 'folder' : 'file';
                    subtitle.textContent = 'Are you sure you want to delete this ' + itemType + '? ' + fileName;
                    hideError();
                    
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                    
                    // Focus confirm button
                    setTimeout(() => {
                        confirmBtn.focus();
                    }, 50);
                }
                
                function closeModal() {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                    currentFilePath = '';
                    currentFileName = '';
                    isDirectory = false;
                }
                
                function performDelete() {
                    // Disable buttons during operation
                    confirmBtn.disabled = true;
                    cancelBtn.disabled = true;
                    hideError();
                    
                    // Send delete request
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'delete=1&file_path=' + encodeURIComponent(currentFilePath)
                    })
                    .then(response => {
                        // Check if response is ok before parsing JSON
                        if (!response.ok) {
                            return response.json().then(data => {
                                throw new Error(data.error || 'Failed to delete');
                            }).catch(() => {
                                throw new Error('Failed to delete');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Reload page to show updated list
                            window.location.reload();
                        } else {
                            showError(data.error || 'Failed to delete');
                            confirmBtn.disabled = false;
                            cancelBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        showError(err.message || 'An error occurred. Please try again.');
                        confirmBtn.disabled = false;
                        cancelBtn.disabled = false;
                        console.error('Delete error:', err);
                    });
                }
                
                // Event listeners
                document.addEventListener('click', function(e) {
                    const deleteBtn = e.target.closest('.delete-btn');
                    if (deleteBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const filePath = deleteBtn.dataset.filePath;
                        const fileName = deleteBtn.dataset.fileName;
                        const isDir = deleteBtn.dataset.isDir === 'true';
                        
                        openModal(filePath, fileName, isDir);
                    }
                });
                
                cancelBtn.addEventListener('click', closeModal);
                
                confirmBtn.addEventListener('click', performDelete);
                
                // Close modal when clicking outside
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
                
                // Keyboard support
                modal.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeModal();
                    }
                });
            })();
        })();
    </script>
</body>
</html>