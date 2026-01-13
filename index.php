<?php
/**
 * Simple PHP File Lister
 * A lightweight, secure directory listing application
 * 
 * @author Blind Trevor
 * @link https://github.com/BlindTrevor/SimplePhpFileLister
 * @version 2.0.0
 */

// ============================================================================
// SECURITY: Prevent direct access to configuration files
// ============================================================================
$requestUri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$requestedFile = basename($requestUri);
if (in_array($requestedFile, ['SPFL-Config.json', 'SPFL-Users.json'], true)) {
    http_response_code(403);
    die('403 Forbidden: Direct access to configuration files is not allowed.');
}

// ============================================================================
// VERSION INFORMATION
// ============================================================================
// Version is automatically updated by GitHub Actions on merge to main branch
define('APP_VERSION', '2.0.0');

// ============================================================================
// CONFIGURATION
// ============================================================================
// All user-configurable settings are centralized in this section.
// Modify these values to customize the behavior and appearance of your file lister.

// --- Display Customization ---
$title = "Simple PHP File Lister";                    // Page title
$subtitle = "The Easy Way To List Files In A Directory"; // Subtitle shown below title
$footer = "Made with ❤️ by Blind Trevor";              // Footer text

// --- Pagination Settings ---
$paginationThreshold = 25; // Number of items per page before pagination appears
$enablePaginationAmountSelector = true; // Enable/disable pagination amount selector dropdown
$defaultPaginationAmount = 30; // Default items per page (5, 10, 20, 30, 50, or 'all')

// --- Feature Toggles ---
$enableRename = false;             // Enable/disable rename functionality
$enableDelete = false;             // Enable/disable delete functionality
$enableDownloadAll = true;         // Enable/disable "Download All as ZIP" button
$enableBatchDownload = true;       // Enable/disable batch download of selected items as ZIP
$enableIndividualDownload = true;  // Enable/disable individual file downloads
$enableUpload = false;              // Enable/disable file upload functionality
$enableCreateDirectory = false;     // Enable/disable create directory functionality

// --- Display Options ---
$showFileSize = true;           // Show/hide file sizes in file listings
$showFolderFileCount = true;    // Show/hide folder/file count statistics
$showTotalSize = true;          // Show/hide total size in statistics

// --- Theme Settings ---
$defaultTheme = 'purple';       // Default theme: 'purple', 'blue', 'green', 'dark', 'light'
$allowThemeChange = true;       // Allow users to change the theme via settings icon

// --- Advanced Options ---
$includeHiddenFiles = false;    // Include hidden files (starting with .) in listings
$zipCompressionLevel = 6;       // ZIP compression level (0-9, where 0=no compression, 9=maximum compression)

// --- Upload Settings ---
$uploadMaxFileSize = 10 * 1024 * 1024;        // Maximum file size in bytes (default: 10 MB)
$uploadMaxTotalSize = 50 * 1024 * 1024;       // Maximum total size for multiple uploads (default: 50 MB)
$uploadAllowedExtensions = [];                // Optional: Array of allowed extensions (empty = allow all except blocked)
                                              // Example: ['jpg', 'png', 'pdf', 'txt'] to only allow these types

// --- Authentication Settings ---
$configFilePath = './SPFL-Config.json';             // Path to config file (if exists, login required)
$sessionTimeout = 3600;                        // Session timeout in seconds (default: 1 hour)
$enableReadOnlyMode = false;                   // When true, unauthenticated users can view files read-only
$enableGuestMode = false;                      // When true, unauthenticated users can browse (login button for admins)

// For backward compatibility, check old filenames
if (!file_exists($configFilePath)) {
    if (file_exists('./SPFL-Users.json')) {
        $configFilePath = './SPFL-Users.json';
    }
}
$usersFilePath = $configFilePath; // Alias for backward compatibility

// ============================================================================
// CONFIGURATION VALIDATION
// ============================================================================
// This section validates and sanitizes configuration values.
// Do not modify unless you understand the security implications.

// Validate default theme to prevent injection attacks
// Only allow known, safe theme names
$validThemes = ['purple', 'blue', 'green', 'dark', 'light'];
if (!in_array($defaultTheme, $validThemes, true)) {
    $defaultTheme = 'purple'; // Fallback to purple if invalid theme specified
}

// Validate ZIP compression level to ensure it's within PHP ZipArchive valid range (0-9)
// Level 0 = no compression (fastest), 9 = maximum compression (smallest)
$zipCompressionLevel = max(0, min(9, (int)$zipCompressionLevel));

// Security Configuration: Establish real root directory for path validation
// This is the absolute path that all file operations must stay within
// DIRECTORY_SEPARATOR suffix is critical for security - prevents edge case path traversal bypasses
$realRoot = rtrim(realpath('.'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Blocked file extensions to prevent code execution and security vulnerabilities
// These file types are hidden from listings and blocked from downloads/uploads
define('BLOCKED_EXTENSIONS', [
    'php', 'phtml', 'phar', 'cgi', 'pl', 'sh', 'bat', 'exe',
    'jsp', 'asp', 'aspx', 'py', 'rb', 'ps1', 'vbs', 'htaccess',
    'scr', 'com', 'jar'
]);

// Reserved filesystem names that cannot be used for directories
// Includes special files and paths that should never be created/modified
define('RESERVED_NAMES', [
    'index.php', '.', '..', '.htaccess', '.gitignore', '.env', 'SPFL-Config.json', 'SPFL-Users.json'
]);

// Authentication: Check if config file exists (determines if login is required)
$authEnabled = file_exists($configFilePath) && is_readable($configFilePath);

// Load admin-controlled settings if auth is enabled
if ($authEnabled) {
    $configContent = file_get_contents($configFilePath);
    if ($configContent !== false) {
        $config = json_decode($configContent, true);
        if (is_array($config) && isset($config['settings']) && is_array($config['settings'])) {
            $settings = $config['settings'];
            // Override feature toggles with admin-controlled settings
            if (isset($settings['enableRename'])) $enableRename = (bool)$settings['enableRename'];
            if (isset($settings['enableDelete'])) $enableDelete = (bool)$settings['enableDelete'];
            if (isset($settings['enableDownloadAll'])) $enableDownloadAll = (bool)$settings['enableDownloadAll'];
            if (isset($settings['enableBatchDownload'])) $enableBatchDownload = (bool)$settings['enableBatchDownload'];
            if (isset($settings['enableIndividualDownload'])) $enableIndividualDownload = (bool)$settings['enableIndividualDownload'];
            if (isset($settings['enableUpload'])) $enableUpload = (bool)$settings['enableUpload'];
            if (isset($settings['enableCreateDirectory'])) $enableCreateDirectory = (bool)$settings['enableCreateDirectory'];
        }
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get list of previewable file types grouped by category
 * @return array Array of file types by category
 */
function getPreviewableFileTypes(): array {
    return [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'],
        'video' => ['mp4', 'webm', 'ogv', 'mpg', 'mpeg'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'],
    ];
}

/**
 * Get MIME type for preview-supported file extensions
 * 
 * IMPORTANT: This array is intentionally duplicated in the fast-path preview handler
 * (around line 630) for performance optimization. When adding or removing file types,
 * you MUST update BOTH locations to maintain consistency:
 * 1. This function's $mimeTypes array
 * 2. The $mimeTypes array in the preview handler (if (isset($_GET['preview'])))
 * 
 * @param string $ext File extension
 * @return string|null MIME type or null if not supported
 */
function getPreviewMimeType(string $ext): ?string {
    // NOTE: Keep this array synchronized with the fast-path preview handler
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
        'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
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
        // Document formats
        'pdf' => ['fa-regular fa-file-pdf', 'icon-pdf'],
        'doc' => ['fa-regular fa-file-word', 'icon-word'],
        'docx' => ['fa-regular fa-file-word', 'icon-word'],
        'xls' => ['fa-regular fa-file-excel', 'icon-excel'],
        'xlsx' => ['fa-regular fa-file-excel', 'icon-excel'],
        'ppt' => ['fa-regular fa-file-powerpoint', 'icon-powerpoint'],
        'pptx' => ['fa-regular fa-file-powerpoint', 'icon-powerpoint'],
        'txt' => ['fa-regular fa-file-lines', 'icon-text'],
        'rtf' => ['fa-regular fa-file-lines', 'icon-text'],
        'odt' => ['fa-regular fa-file-word', 'icon-word'],
        'ods' => ['fa-regular fa-file-excel', 'icon-excel'],
        'odp' => ['fa-regular fa-file-powerpoint', 'icon-powerpoint'],
        
        // Archive formats
        'zip' => ['fa-solid fa-file-zipper', 'icon-archive'],
        'rar' => ['fa-solid fa-file-zipper', 'icon-archive'],
        '7z' => ['fa-solid fa-file-zipper', 'icon-archive'],
        'tar' => ['fa-solid fa-file-zipper', 'icon-archive'],
        'gz' => ['fa-solid fa-file-zipper', 'icon-archive'],
        'bz2' => ['fa-solid fa-file-zipper', 'icon-archive'],
        
        // Image formats
        'jpg' => ['fa-regular fa-file-image', 'icon-image'],
        'jpeg' => ['fa-regular fa-file-image', 'icon-image'],
        'png' => ['fa-regular fa-file-image', 'icon-image'],
        'gif' => ['fa-regular fa-file-image', 'icon-image'],
        'svg' => ['fa-regular fa-file-image', 'icon-image'],
        'bmp' => ['fa-regular fa-file-image', 'icon-image'],
        'ico' => ['fa-regular fa-file-image', 'icon-image'],
        'webp' => ['fa-regular fa-file-image', 'icon-image'],
        'tiff' => ['fa-regular fa-file-image', 'icon-image'],
        'tif' => ['fa-regular fa-file-image', 'icon-image'],
        
        // Audio formats
        'mp3' => ['fa-regular fa-file-audio', 'icon-audio'],
        'wav' => ['fa-regular fa-file-audio', 'icon-audio'],
        'ogg' => ['fa-regular fa-file-audio', 'icon-audio'],
        'm4a' => ['fa-regular fa-file-audio', 'icon-audio'],
        'flac' => ['fa-regular fa-file-audio', 'icon-audio'],
        'aac' => ['fa-regular fa-file-audio', 'icon-audio'],
        
        // Video formats
        'mp4' => ['fa-regular fa-file-video', 'icon-video'],
        'avi' => ['fa-regular fa-file-video', 'icon-video'],
        'mov' => ['fa-regular fa-file-video', 'icon-video'],
        'wmv' => ['fa-regular fa-file-video', 'icon-video'],
        'flv' => ['fa-regular fa-file-video', 'icon-video'],
        'mkv' => ['fa-regular fa-file-video', 'icon-video'],
        'webm' => ['fa-regular fa-file-video', 'icon-video'],
        'ogv' => ['fa-regular fa-file-video', 'icon-video'],
        'mpg' => ['fa-regular fa-file-video', 'icon-video'],
        'mpeg' => ['fa-regular fa-file-video', 'icon-video'],
        
        // Code and markup formats
        'html' => ['fa-regular fa-file-code', 'icon-html'],
        'htm' => ['fa-regular fa-file-code', 'icon-html'],
        'css' => ['fa-regular fa-file-code', 'icon-css'],
        'js' => ['fa-regular fa-file-code', 'icon-js'],
        'php' => ['fa-regular fa-file-code', 'icon-php'],
        'json' => ['fa-regular fa-file-code', 'icon-code'],
        'xml' => ['fa-regular fa-file-code', 'icon-code'],
        'yaml' => ['fa-regular fa-file-code', 'icon-code'],
        'yml' => ['fa-regular fa-file-code', 'icon-code'],
        'py' => ['fa-regular fa-file-code', 'icon-code'],
        'rb' => ['fa-regular fa-file-code', 'icon-code'],
        'java' => ['fa-regular fa-file-code', 'icon-code'],
        'c' => ['fa-regular fa-file-code', 'icon-code'],
        'cpp' => ['fa-regular fa-file-code', 'icon-code'],
        'cs' => ['fa-regular fa-file-code', 'icon-code'],
        'go' => ['fa-regular fa-file-code', 'icon-code'],
        'rs' => ['fa-regular fa-file-code', 'icon-code'],
        'ts' => ['fa-regular fa-file-code', 'icon-code'],
        'tsx' => ['fa-regular fa-file-code', 'icon-code'],
        'jsx' => ['fa-regular fa-file-code', 'icon-code'],
        'sql' => ['fa-regular fa-file-code', 'icon-code'],
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
        // Conditionally show folder size based on configuration
        $sizeHtml = ($showFileSize && $fileSize > 0) ? '<span class="file-size">' . htmlspecialchars(formatFileSize($fileSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>' : '';
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
 * Calculate total size of a directory recursively
 * @param string $dir Directory path
 * @param string $realRoot Real root path for security validation
 * @param bool $includeHiddenFiles Whether to include hidden files
 * @return int Total size in bytes
 */
function calculateDirectorySize(string $dir, string $realRoot, bool $includeHiddenFiles = false): int {
    $totalSize = 0;
    
    $handle = opendir($dir);
    if ($handle === false) {
        return 0;
    }
    
    while (($entry = readdir($handle)) !== false) {
        // Skip hidden files based on configuration
        if (in_array($entry, ['.', '..', 'index.php'], true) || (!$includeHiddenFiles && $entry[0] === '.')) {
            continue;
        }
        
        $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
        $realPath = realpath($fullPath);
        
        // Skip invalid paths, symlinks
        if (is_link($fullPath) || $realPath === false || 
            strpos($realPath . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
            continue;
        }
        
        if (is_dir($fullPath)) {
            // Recurse into directory
            $totalSize += calculateDirectorySize($fullPath, $realRoot, $includeHiddenFiles);
        } else {
            // Skip dangerous extensions
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
                continue;
            }
            
            // Add file size
            $fileSize = @filesize($fullPath);
            if ($fileSize !== false) {
                $totalSize += $fileSize;
            }
        }
    }
    closedir($handle);
    
    return $totalSize;
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

/**
 * Recursively delete a file or directory
 * @param string $path Path to delete
 * @return bool True on success, false on failure
 */
function deleteRecursive(string $path): bool {
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
            if (!deleteRecursive($entryPath)) {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        
        return @rmdir($path);
    } else {
        return @unlink($path);
    }
}

/**
 * Recursively add directory contents to a ZIP archive
 * @param ZipArchive $zip ZIP archive object
 * @param string $dir Directory to add
 * @param string $zipPath Path within ZIP archive
 * @param int $count Reference to file count
 * @param string $realRoot Real root path for security validation
 * @param int $zipCompressionLevel Compression level (0-9)
 * @param bool $includeHiddenFiles Whether to include hidden files
 * @return void
 */
function addToZip(ZipArchive $zip, string $dir, string $zipPath, int &$count, string $realRoot, int $zipCompressionLevel, bool $includeHiddenFiles): void {
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
            addToZip($zip, $fullPath, $zipEntryPath, $count, $realRoot, $zipCompressionLevel, $includeHiddenFiles);
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
}

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

/**
 * Load users from the users file
 * @return array Array of users with their configuration
 */
function loadUsers(): array {
    global $usersFilePath;
    
    if (!file_exists($usersFilePath) || !is_readable($usersFilePath)) {
        return [];
    }
    
    $content = file_get_contents($usersFilePath);
    if ($content === false) {
        return [];
    }
    
    $data = json_decode($content, true);
    if (!is_array($data) || !isset($data['users']) || !is_array($data['users'])) {
        return [];
    }
    
    // Validate user structure
    $validUsers = [];
    $validPermissions = ['view', 'download', 'upload', 'delete', 'rename', 'create_directory'];
    
    foreach ($data['users'] as $user) {
        // Validate required fields
        if (!is_array($user) || 
            !isset($user['username']) || !is_string($user['username']) ||
            !isset($user['password']) || !is_string($user['password'])) {
            continue; // Skip invalid users
        }
        
        // Validate admin flag
        $admin = isset($user['admin']) && $user['admin'] === true;
        
        // Validate permissions array
        $permissions = [];
        if (isset($user['permissions']) && is_array($user['permissions'])) {
            foreach ($user['permissions'] as $perm) {
                if (is_string($perm) && in_array($perm, $validPermissions, true)) {
                    $permissions[] = $perm;
                }
            }
        }
        
        $validUsers[] = [
            'username' => $user['username'],
            'password' => $user['password'],
            'admin' => $admin,
            'permissions' => array_unique($permissions)
        ];
    }
    
    return $validUsers;
}

/**
 * Save users to the config file (preserves existing settings)
 * @param array $users Array of users to save
 * @return bool True on success, false on failure
 */
function saveUsers(array $users): bool {
    global $configFilePath;
    
    // Load existing config to preserve settings
    $config = ['users' => $users];
    if (file_exists($configFilePath)) {
        $existingContent = file_get_contents($configFilePath);
        if ($existingContent !== false) {
            $existingConfig = json_decode($existingContent, true);
            if (is_array($existingConfig) && isset($existingConfig['settings'])) {
                $config['settings'] = $existingConfig['settings'];
            }
        }
    }
    
    $json = json_encode($config, JSON_PRETTY_PRINT);
    
    if ($json === false) {
        return false;
    }
    
    return file_put_contents($configFilePath, $json, LOCK_EX) !== false;
}

/**
 * Authenticate a user with username and password
 * @param string $username Username to authenticate
 * @param string $password Plain text password
 * @return array|null User data if authenticated, null otherwise
 */
function authenticateUser(string $username, string $password): ?array {
    $users = loadUsers();
    
    // Convert username to lowercase for case-insensitive comparison
    $usernameLower = strtolower($username);
    
    foreach ($users as $user) {
        if (!isset($user['username']) || !isset($user['password'])) {
            continue;
        }
        
        if (strtolower($user['username']) === $usernameLower) {
            // Verify password using password_verify for bcrypt hashes
            if (password_verify($password, $user['password'])) {
                return $user;
            }
        }
    }
    
    return null;
}

/**
 * Check if current user has a specific permission
 * @param string $permission Permission to check
 * @return bool True if user has permission, false otherwise
 */
function hasPermission(string $permission): bool {
    global $authEnabled, $enableReadOnlyMode;
    
    // If authentication is disabled, all permissions are granted
    if (!$authEnabled) {
        return true;
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        // If read-only mode is enabled and permission is read-only, allow
        if ($enableReadOnlyMode && in_array($permission, ['view', 'download'], true)) {
            return true;
        }
        return false;
    }
    
    $user = $_SESSION['user'];
    
    // Admin users have all permissions
    if (isset($user['admin']) && $user['admin'] === true) {
        return true;
    }
    
    // Check specific permission
    if (isset($user['permissions']) && is_array($user['permissions'])) {
        return in_array($permission, $user['permissions'], true);
    }
    
    return false;
}

/**
 * Check if current user is admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin(): bool {
    global $authEnabled;
    
    // If authentication is disabled, all users are considered admin
    if (!$authEnabled) {
        return true;
    }
    
    if (!isset($_SESSION['user'])) {
        return false;
    }
    
    return isset($_SESSION['user']['admin']) && $_SESSION['user']['admin'] === true;
}

/**
 * Check if user is authenticated (actually logged in with credentials)
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated(): bool {
    return isset($_SESSION['user']);
}

/**
 * Check if user can access the file lister
 * @return bool True if can access (authenticated, guest mode, or read-only mode), false otherwise
 */
function canAccessFileLister(): bool {
    global $authEnabled, $enableReadOnlyMode, $enableGuestMode;
    
    // If authentication is disabled, always allow access
    if (!$authEnabled) {
        return true;
    }
    
    // If user is authenticated, allow access
    if (isAuthenticated()) {
        return true;
    }
    
    // If guest mode is enabled, allow unauthenticated access
    if ($enableGuestMode) {
        return true;
    }
    
    // If read-only mode is enabled, allow unauthenticated access
    if ($enableReadOnlyMode) {
        return true;
    }
    
    return false;
}

/**
 * Check if user is logged in (deprecated, use isAuthenticated() or canAccessFileLister())
 * @return bool True if logged in, false otherwise
 * @deprecated Use isAuthenticated() to check if user is logged in, or canAccessFileLister() to check if user can access
 */
function isLoggedIn(): bool {
    return isAuthenticated();
}

/**
 * Get current logged in user
 * @return array|null User data if logged in, null otherwise
 */
function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Start authentication session
 */
function initAuthSession(): void {
    global $sessionTimeout;
    
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string)$sessionTimeout);
        
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            // Regenerate session every 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
            // Session expired
            session_unset();
            session_destroy();
            session_start();
        }
        
        $_SESSION['last_activity'] = time();
    }
}

// ============================================================================
// REQUEST HANDLERS
// ============================================================================

// Initialize authentication session if auth is enabled
if ($authEnabled) {
    initAuthSession();
}

/**
 * Login handler
 */
if (isset($_POST['login'])) {
    header('Content-Type: application/json');
    
    if (!$authEnabled) {
        echo json_encode(['success' => false, 'message' => 'Authentication is not enabled']);
        exit;
    }
    
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }
    
    $user = authenticateUser($username, $password);
    
    if ($user !== null) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $_SESSION['user'] = $user;
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();
        
        // Check if user has default password
        $defaultPasswords = ['admin' => 'admin', 'user' => 'user', 'editor' => 'editor'];
        $requiresPasswordChange = false;
        
        if (isset($defaultPasswords[strtolower($username)])) {
            $defaultPassword = $defaultPasswords[strtolower($username)];
            if (password_verify($defaultPassword, $user['password'])) {
                $requiresPasswordChange = true;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'requirePasswordChange' => $requiresPasswordChange
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
    exit;
}

/**
 * Logout handler
 */
if (isset($_GET['logout'])) {
    if ($authEnabled) {
        session_unset();
        session_destroy();
    }
    
    // Redirect to home
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/**
 * Setup authentication wizard handler (first-time admin creation)
 */
if (isset($_POST['setup_auth'])) {
    header('Content-Type: application/json');
    
    // Only allow if auth is not already enabled
    if ($authEnabled) {
        echo json_encode(['success' => false, 'message' => 'Authentication is already configured']);
        exit;
    }
    
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password']) : '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }
    
    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']);
        exit;
    }
    
    // Create new admin user
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $config = [
        'users' => [
            [
                'username' => $username,
                'password' => $hashedPassword,
                'admin' => true,
                'permissions' => []
            ]
        ],
        'settings' => [
            'enableRename' => false,
            'enableDelete' => false,
            'enableUpload' => false,
            'enableCreateDirectory' => false,
            'enableIndividualDownload' => true
        ]
    ];
    
    // Write config file
    $jsonContent = json_encode($config, JSON_PRETTY_PRINT);
    if (file_put_contents($configFilePath, $jsonContent) === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to create configuration file']);
        exit;
    }
    
    // Log the user in immediately
    session_regenerate_id(true);
    $_SESSION['user'] = $config['users'][0];
    $_SESSION['created'] = time();
    $_SESSION['last_activity'] = time();
    
    echo json_encode(['success' => true, 'message' => 'Authentication setup complete']);
    exit;
}

/**
 * Force password change handler
 */
if (isset($_POST['change_default_password'])) {
    header('Content-Type: application/json');
    
    if (!$authEnabled || !isAuthenticated()) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    $newPassword = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';
    
    if (empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'New password is required']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    // Check if new password is a default password
    $defaultPasswords = ['admin', 'user', 'editor'];
    if (in_array($newPassword, $defaultPasswords, true)) {
        echo json_encode(['success' => false, 'message' => 'Cannot use a default password']);
        exit;
    }
    
    // Get current user
    $currentUser = $_SESSION['user'];
    $username = $currentUser['username'];
    
    // Load config and update password
    $config = loadUsers();
    $userFound = false;
    
    foreach ($config['users'] as &$user) {
        if (strcasecmp($user['username'], $username) === 0) {
            $user['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
            $userFound = true;
            
            // Update session
            $_SESSION['user'] = $user;
            break;
        }
    }
    
    if (!$userFound) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Save updated config
    $jsonContent = json_encode($config, JSON_PRETTY_PRINT);
    if (file_put_contents($configFilePath, $jsonContent) === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to update configuration']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    exit;
}

/**
 * User management handler (admin only)
 */
if (isset($_POST['user_management'])) {
    header('Content-Type: application/json');
    
    if (!$authEnabled) {
        echo json_encode(['success' => false, 'message' => 'Authentication is not enabled']);
        exit;
    }
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required']);
        exit;
    }
    
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    
    if ($action === 'list') {
        // List all users (without passwords)
        $users = loadUsers();
        $safeUsers = array_map(function($user) {
            unset($user['password']);
            return $user;
        }, $users);
        
        echo json_encode(['success' => true, 'users' => array_values($safeUsers)]);
        exit;
    }
    
    if ($action === 'create') {
        $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $admin = isset($_POST['admin']) && $_POST['admin'] === 'true';
        $permissions = isset($_POST['permissions']) ? json_decode((string)$_POST['permissions'], true) : [];
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username and password are required']);
            exit;
        }
        
        // Validate username (alphanumeric, underscore, dash only)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, underscores, and dashes']);
            exit;
        }
        
        // Validate permissions array
        if (!is_array($permissions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid permissions format']);
            exit;
        }
        
        $validPermissions = ['view', 'download', 'upload', 'delete', 'rename', 'create_directory'];
        $sanitizedPermissions = [];
        foreach ($permissions as $perm) {
            if (is_string($perm) && in_array($perm, $validPermissions, true)) {
                $sanitizedPermissions[] = $perm;
            }
        }
        $permissions = array_unique($sanitizedPermissions);
        
        $users = loadUsers();
        
        // Check if username already exists
        foreach ($users as $user) {
            if (isset($user['username']) && $user['username'] === $username) {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
                exit;
            }
        }
        
        // Create new user
        $newUser = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'admin' => $admin,
            'permissions' => is_array($permissions) ? $permissions : []
        ];
        
        $users[] = $newUser;
        
        if (saveUsers($users)) {
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save user']);
        }
        exit;
    }
    
    if ($action === 'update') {
        $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $admin = isset($_POST['admin']) && $_POST['admin'] === 'true';
        $permissions = isset($_POST['permissions']) ? json_decode((string)$_POST['permissions'], true) : [];
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Username is required']);
            exit;
        }
        
        // Validate permissions array
        if (!is_array($permissions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid permissions format']);
            exit;
        }
        
        $validPermissions = ['view', 'download', 'upload', 'delete', 'rename', 'create_directory'];
        $sanitizedPermissions = [];
        foreach ($permissions as $perm) {
            if (is_string($perm) && in_array($perm, $validPermissions, true)) {
                $sanitizedPermissions[] = $perm;
            }
        }
        $permissions = array_unique($sanitizedPermissions);
        
        $users = loadUsers();
        $found = false;
        
        foreach ($users as $key => $user) {
            if (isset($user['username']) && $user['username'] === $username) {
                $users[$key]['admin'] = $admin;
                $users[$key]['permissions'] = $permissions;
                
                // Only update password if provided
                if (!empty($password)) {
                    $users[$key]['password'] = password_hash($password, PASSWORD_BCRYPT);
                }
                
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        if (saveUsers($users)) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save user']);
        }
        exit;
    }
    
    if ($action === 'delete') {
        $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Username is required']);
            exit;
        }
        
        // Prevent deleting yourself
        $currentUser = getCurrentUser();
        if ($currentUser && isset($currentUser['username']) && $currentUser['username'] === $username) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
            exit;
        }
        
        $users = loadUsers();
        $newUsers = [];
        $found = false;
        
        foreach ($users as $user) {
            if (isset($user['username']) && $user['username'] === $username) {
                $found = true;
                continue; // Skip this user (delete)
            }
            $newUsers[] = $user;
        }
        
        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        if (saveUsers($newUsers)) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save users']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

/**
 * Settings management handler (admin only, auth enabled only)
 */
if (isset($_POST['settings_management'])) {
    header('Content-Type: application/json');
    
    if (!$authEnabled) {
        echo json_encode(['success' => false, 'message' => 'Authentication is not enabled']);
        exit;
    }
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required']);
        exit;
    }
    
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    
    if ($action === 'get') {
        // Get current settings
        global $enableRename, $enableDelete, $enableDownloadAll, $enableBatchDownload, $enableIndividualDownload, $enableUpload, $enableCreateDirectory;
        
        $settings = [
            'enableRename' => $enableRename,
            'enableDelete' => $enableDelete,
            'enableDownloadAll' => $enableDownloadAll,
            'enableBatchDownload' => $enableBatchDownload,
            'enableIndividualDownload' => $enableIndividualDownload,
            'enableUpload' => $enableUpload,
            'enableCreateDirectory' => $enableCreateDirectory
        ];
        
        echo json_encode(['success' => true, 'settings' => $settings]);
        exit;
    }
    
    if ($action === 'save') {
        global $configFilePath;
        
        // Get settings from POST
        $settings = [
            'enableRename' => isset($_POST['enableRename']) && $_POST['enableRename'] === 'true',
            'enableDelete' => isset($_POST['enableDelete']) && $_POST['enableDelete'] === 'true',
            'enableDownloadAll' => isset($_POST['enableDownloadAll']) && $_POST['enableDownloadAll'] === 'true',
            'enableBatchDownload' => isset($_POST['enableBatchDownload']) && $_POST['enableBatchDownload'] === 'true',
            'enableIndividualDownload' => isset($_POST['enableIndividualDownload']) && $_POST['enableIndividualDownload'] === 'true',
            'enableUpload' => isset($_POST['enableUpload']) && $_POST['enableUpload'] === 'true',
            'enableCreateDirectory' => isset($_POST['enableCreateDirectory']) && $_POST['enableCreateDirectory'] === 'true'
        ];
        
        // Load existing config to preserve users
        $config = ['settings' => $settings];
        if (file_exists($configFilePath)) {
            $existingContent = file_get_contents($configFilePath);
            if ($existingContent !== false) {
                $existingConfig = json_decode($existingContent, true);
                if (is_array($existingConfig) && isset($existingConfig['users'])) {
                    $config['users'] = $existingConfig['users'];
                }
            }
        }
        
        $json = json_encode($config, JSON_PRETTY_PRINT);
        if ($json === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to encode settings']);
            exit;
        }
        
        if (file_put_contents($configFilePath, $json, LOCK_EX) !== false) {
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save settings file']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

/**
 * FAST PATH: Secure preview handler (for inline display in browser)
 * This is placed at the top for maximum performance - exits immediately without loading anything else
 * NOTE: MIME types array is intentionally duplicated here (also in getPreviewMimeType()) 
 *       to avoid loading any functions. This duplication is a performance optimization.
 *       When updating supported file types, update BOTH locations.
 */
if (isset($_GET['preview'])) {
    // Check permissions for preview (requires view permission)
    if (!hasPermission('view')) {
        http_response_code(403);
        exit('Permission denied: view access required');
    }
    
    $rel = (string)$_GET['preview'];
    $full = realpath($realRoot . $rel);
    
    // Security: Validate path is within root and file exists
    // realpath() resolves symlinks and normalizes path to prevent directory traversal attacks
    // DIRECTORY_SEPARATOR suffix prevents edge case where path could bypass check
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
    // PERFORMANCE NOTE: This MIME types array is intentionally duplicated here (also in getPreviewMimeType())
    // This duplication is a deliberate performance optimization to avoid function calls in the fast path.
    // The preview handler is placed at the very top of the file and exits immediately to minimize overhead.
    // 
    // MAINTENANCE: When updating supported file types, update BOTH locations:
    // 1. This array (in the fast-path preview handler)
    // 2. The getPreviewMimeType() function (around line 125)
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    // NOTE: Keep this array synchronized with getPreviewMimeType() function
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
        'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
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
    
    $fileSize = filesize($full);
    $start = 0;
    $end = $fileSize - 1;
    
    // Handle range requests (critical for audio/video seeking and duration detection)
    if (isset($_SERVER['HTTP_RANGE'])) {
        header('Accept-Ranges: bytes');
        
        // Parse range header
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
            $start = intval($matches[1]);
            $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
            
            // Validate range
            if ($start > $end || $start < 0 || $end >= $fileSize) {
                http_response_code(416); // Range Not Satisfiable
                header('Content-Range: bytes */' . $fileSize);
                fclose($fp);
                exit;
            }
            
            // Send partial content
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
            header('Content-Length: ' . ($end - $start + 1));
            
            // Seek to start position
            fseek($fp, $start);
        }
    } else {
        // No range request - send full file
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $fileSize);
    }
    
    // Set headers for inline display
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    
    // Disable output buffering for efficient streaming
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Stream file content
    if (isset($_SERVER['HTTP_RANGE'])) {
        // Stream only the requested range
        $remaining = $end - $start + 1;
        $bufferSize = 8192;
        while ($remaining > 0 && !feof($fp)) {
            $readSize = min($bufferSize, $remaining);
            echo fread($fp, $readSize);
            $remaining -= $readSize;
            flush();
        }
    } else {
        // Stream entire file
        fpassthru($fp);
    }
    fclose($fp);
    exit;
}

/**
 * Secure rename handler
 */
if (isset($_POST['rename'])) {
    header('Content-Type: application/json');
    
    // Check permissions
    if (!hasPermission('rename')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied: rename access required']);
        exit;
    }
    
    // Check if rename is enabled (admins and users with permission can bypass this when auth is enabled)
    if (!$enableRename && !($authEnabled && (isAdmin() || hasPermission('rename')))) {
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
    
    // Check permissions
    if (!hasPermission('delete')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied: delete access required']);
        exit;
    }
    
    // Check if delete is enabled (admins and users with permission can bypass this when auth is enabled)
    if (!$enableDelete && !($authEnabled && (isAdmin() || hasPermission('delete')))) {
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
    
    // Perform the delete
    if (deleteRecursive($fullPath)) {
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
    
    // Check permissions
    if (!hasPermission('delete')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied: delete access required']);
        exit;
    }
    
    // Check if delete is enabled (admins and users with permission can bypass this when auth is enabled)
    if (!$enableDelete && !($authEnabled && (isAdmin() || hasPermission('delete')))) {
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
        if (deleteRecursive($fullPath)) {
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
 * Secure file upload handler
 */
if (isset($_POST['upload'])) {
    header('Content-Type: application/json');
    
    // Check permissions
    if (!hasPermission('upload')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied: upload access required']);
        exit;
    }
    
    // Check if upload is enabled (admins can bypass this when auth is enabled)
    if (!$enableUpload && !($authEnabled && isAdmin())) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Upload functionality is disabled']);
        exit;
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No files provided']);
        exit;
    }
    
    // Get target path
    $targetPath = isset($_POST['target_path']) ? (string)$_POST['target_path'] : '';
    
    // Validate target path
    $basePath = $targetPath ? './' . str_replace('\\', '/', $targetPath) : '.';
    $realBase = realpath($basePath);
    
    // Validate path is within root
    if ($realBase === false || strpos($realBase . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invalid target directory']);
        exit;
    }
    
    // Ensure target is a directory
    if (!is_dir($realBase)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Target is not a directory']);
        exit;
    }
    
    $uploadedFiles = [];
    $failedFiles = [];
    $totalSize = 0;
    
    // Handle multiple file uploads
    $fileCount = is_array($_FILES['files']['name']) ? count($_FILES['files']['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        // Extract file info (handle both single and multiple file uploads)
        if (is_array($_FILES['files']['name'])) {
            $fileName = $_FILES['files']['name'][$i];
            $fileTmpName = $_FILES['files']['tmp_name'][$i];
            $fileError = $_FILES['files']['error'][$i];
            $fileSize = $_FILES['files']['size'][$i];
        } else {
            $fileName = $_FILES['files']['name'];
            $fileTmpName = $_FILES['files']['tmp_name'];
            $fileError = $_FILES['files']['error'];
            $fileSize = $_FILES['files']['size'];
        }
        
        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $errorMsg = 'Upload error';
            switch ($fileError) {
                case UPLOAD_ERR_INI_SIZE:
                    $errorMsg = 'File too large (server limit)';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = 'File exceeds form size limit';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = 'Partial upload';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    continue 2; // Skip this file (continue the outer for loop)
                case UPLOAD_ERR_NO_TMP_DIR:
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMsg = 'Server error';
                    break;
            }
            $failedFiles[] = $fileName . ' (' . $errorMsg . ')';
            continue;
        }
        
        // Check file size
        if ($fileSize > $uploadMaxFileSize) {
            $maxSizeMB = round($uploadMaxFileSize / (1024 * 1024), 1);
            $failedFiles[] = $fileName . ' (exceeds ' . $maxSizeMB . ' MB limit)';
            continue;
        }
        
        // Check total size
        $totalSize += $fileSize;
        if ($totalSize > $uploadMaxTotalSize) {
            $maxTotalMB = round($uploadMaxTotalSize / (1024 * 1024), 1);
            $failedFiles[] = $fileName . ' (total size exceeds ' . $maxTotalMB . ' MB)';
            continue;
        }
        
        // Sanitize filename - remove path separators and dangerous characters
        // basename() removes all path components for security
        $fileName = basename($fileName);
        $fileName = str_replace(['/', '\\', "\0"], '', $fileName);
        $fileName = trim($fileName);
        
        // Validate filename
        if (empty($fileName) || $fileName === '.' || $fileName === '..') {
            $failedFiles[] = ($fileName ?: 'unnamed') . ' (invalid name)';
            continue;
        }
        
        // Prevent uploading hidden files
        if ($fileName[0] === '.') {
            $failedFiles[] = $fileName . ' (hidden files not allowed)';
            continue;
        }
        
        // Prevent overwriting index.php
        if ($fileName === 'index.php') {
            $failedFiles[] = $fileName . ' (protected file)';
            continue;
        }
        
        // Get file extension
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check against blocked extensions
        if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
            $failedFiles[] = $fileName . ' (blocked file type)';
            continue;
        }
        
        // Check against allow list if configured
        if (!empty($uploadAllowedExtensions) && !in_array($ext, array_map('strtolower', $uploadAllowedExtensions), true)) {
            $failedFiles[] = $fileName . ' (file type not allowed)';
            continue;
        }
        
        // Build target file path
        $targetFile = $realBase . DIRECTORY_SEPARATOR . $fileName;
        
        // Check if file already exists
        if (file_exists($targetFile)) {
            // Generate unique filename
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $counter = 1;
            
            do {
                $newFileName = $baseName . '_' . $counter . ($extension ? '.' . $extension : '');
                $targetFile = $realBase . DIRECTORY_SEPARATOR . $newFileName;
                $counter++;
            } while (file_exists($targetFile) && $counter < 1000);
            
            if ($counter >= 1000) {
                $failedFiles[] = $fileName . ' (too many duplicates)';
                continue;
            }
            
            $fileName = $newFileName;
        }
        
        // Move uploaded file
        if (@move_uploaded_file($fileTmpName, $targetFile)) {
            // Set file permissions to read-only for security
            @chmod($targetFile, 0644);
            $uploadedFiles[] = $fileName;
        } else {
            $failedFiles[] = $fileName . ' (failed to save)';
        }
    }
    
    // Return response
    if (count($uploadedFiles) > 0 && empty($failedFiles)) {
        echo json_encode(['success' => true, 'uploaded' => count($uploadedFiles), 'files' => $uploadedFiles]);
    } elseif (count($uploadedFiles) > 0 && !empty($failedFiles)) {
        echo json_encode([
            'success' => true,
            'uploaded' => count($uploadedFiles),
            'files' => $uploadedFiles,
            'failed' => $failedFiles,
            'message' => 'Some files could not be uploaded'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to upload files', 'failed' => $failedFiles]);
    }
    exit;
}

/**
 * Secure create directory handler
 */
if (isset($_POST['create_directory'])) {
    header('Content-Type: application/json');
    
    // Check permissions
    if (!hasPermission('create_directory')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied: create directory access required']);
        exit;
    }
    
    // Check if create directory is enabled (admins can bypass this when auth is enabled)
    if (!$enableCreateDirectory && !($authEnabled && isAdmin())) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Create directory functionality is disabled']);
        exit;
    }
    
    $dirName = isset($_POST['directory_name']) ? (string)$_POST['directory_name'] : '';
    $targetPath = isset($_POST['target_path']) ? (string)$_POST['target_path'] : '';
    
    // Validate inputs
    if (empty($dirName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // Sanitize directory name - prevent path traversal
    $dirName = str_replace(['/', '\\', "\0"], '', $dirName);
    $dirName = trim($dirName);
    
    // Validate directory name is not empty after sanitization
    if (empty($dirName) || $dirName === '.' || $dirName === '..') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid directory name']);
        exit;
    }
    
    // Prevent creating hidden directories (check after ensuring name is not empty)
    if (strlen($dirName) > 0 && $dirName[0] === '.') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot create hidden directory']);
        exit;
    }
    
    // Prevent creating reserved/protected directories
    if (in_array($dirName, RESERVED_NAMES, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot use this directory name']);
        exit;
    }
    
    // Validate target path
    $basePath = $targetPath ? './' . str_replace('\\', '/', $targetPath) : '.';
    $realBase = realpath($basePath);
    
    // Validate path is within root
    if ($realBase === false || strpos($realBase . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invalid target directory']);
        exit;
    }
    
    // Ensure target is a directory
    if (!is_dir($realBase)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Target is not a directory']);
        exit;
    }
    
    // Build full path for new directory
    $fullNewPath = $realBase . DIRECTORY_SEPARATOR . $dirName;
    
    // Check if directory already exists
    if (file_exists($fullNewPath)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A file or directory with this name already exists']);
        exit;
    }
    
    // Create the directory with secure permissions (0755)
    // Clear any previous errors to ensure error_get_last() captures only mkdir errors
    error_clear_last();
    $result = mkdir($fullNewPath, 0755);
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        // Get last error for more detailed logging if available
        $error = error_get_last();
        error_log('Failed to create directory: ' . ($error ? $error['message'] : 'unknown error'));
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create directory']);
    }
    exit;
}

/**
 * Secure batch download as zip handler
 */
if (isset($_GET['download_batch_zip'])) {
    // Check permissions
    if (!hasPermission('download')) {
        http_response_code(403);
        exit('Permission denied: download access required');
    }
    
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
            addToZip($zip, $fullPath, $baseName, $fileCount, $realRoot, $zipCompressionLevel, $includeHiddenFiles);
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
    // Check permissions
    if (!hasPermission('download')) {
        http_response_code(403);
        exit('Permission denied: download access required');
    }
    
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
    
    // Add files and directories to zip
    $fileCount = 0;
    addToZip($zip, $basePath, '', $fileCount, $realRoot, $zipCompressionLevel, $includeHiddenFiles);
    
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
    // Check permissions
    if (!hasPermission('download')) {
        http_response_code(403);
        exit('Permission denied: download access required');
    }
    
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

// Check if login is required
$requireLogin = $authEnabled && !canAccessFileLister();

// Process current path
$currentPath = isset($_GET['path']) ? rtrim((string)$_GET['path'], '/') : '';
$basePath = $currentPath ? './' . str_replace('\\', '/', $currentPath) : '.';
$realBase = realpath($basePath);

$isValidPath = $realBase !== false && strpos($realBase . DIRECTORY_SEPARATOR, $realRoot) === 0;

// Pagination: Get current page from query string, default to 1
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Pagination amount: Get from query string or use default
$paginationAmount = isset($_GET['per_page']) ? $_GET['per_page'] : (string)$defaultPaginationAmount;
// Validate pagination amount against allowed values
$allowedAmounts = ['5', '10', '20', '30', '50', 'all'];
if (!in_array($paginationAmount, $allowedAmounts, true)) {
    $paginationAmount = (string)$defaultPaginationAmount;
}
// Convert to integer for calculations (except 'all')
$itemsPerPageActual = ($paginationAmount === 'all') ? PHP_INT_MAX : (int)$paginationAmount;

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
        /* Purple Theme (Default) */
        :root,
        [data-theme="purple"] { 
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
            --footer-text: rgba(255, 255, 255, 0.95);
            --footer-shadow: rgba(0, 0, 0, 0.3);
            --stats-bg: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --stats-border: rgba(0, 0, 0, 0.08);
            --stats-divider: rgba(0, 0, 0, 0.06);
            --stats-shadow: rgba(0, 0, 0, 0.06);
            --info-btn-bg: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }
        
        /* Blue Theme */
        [data-theme="blue"] {
            --bg: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card: #ffffff;
            --accent: #4facfe;
            --accent-hover: #3b8fdb;
            --text: #1a202c;
            --muted: #718096;
            --hover: #f7fafc;
            --border: #e2e8f0;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 50px rgba(0, 0, 0, 0.15);
            --footer-text: rgba(255, 255, 255, 0.95);
            --footer-shadow: rgba(0, 0, 0, 0.3);
            --stats-bg: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --stats-border: rgba(0, 0, 0, 0.08);
            --stats-divider: rgba(0, 0, 0, 0.06);
            --stats-shadow: rgba(0, 0, 0, 0.06);
            --info-btn-bg: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }
        
        /* Green Theme */
        [data-theme="green"] {
            --bg: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --card: #ffffff;
            --accent: #11998e;
            --accent-hover: #0d7a71;
            --text: #1a202c;
            --muted: #718096;
            --hover: #f7fafc;
            --border: #e2e8f0;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 50px rgba(0, 0, 0, 0.15);
            --footer-text: rgba(255, 255, 255, 0.95);
            --footer-shadow: rgba(0, 0, 0, 0.3);
            --stats-bg: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --stats-border: rgba(0, 0, 0, 0.08);
            --stats-divider: rgba(0, 0, 0, 0.06);
            --stats-shadow: rgba(0, 0, 0, 0.06);
            --info-btn-bg: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }
        
        /* Dark Theme */
        [data-theme="dark"] {
            --bg: linear-gradient(135deg, #232526 0%, #414345 100%);
            --card: #2d3748;
            --accent: #4299e1;
            --accent-hover: #3182ce;
            --text: #f7fafc;
            --muted: #a0aec0;
            --hover: #1a202c;
            --border: #4a5568;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 15px 50px rgba(0, 0, 0, 0.4);
            --footer-text: rgba(255, 255, 255, 0.95);
            --footer-shadow: rgba(0, 0, 0, 0.3);
            --stats-bg: #1a202c;
            --stats-border: rgba(255, 255, 255, 0.1);
            --stats-divider: rgba(255, 255, 255, 0.1);
            --stats-shadow: rgba(0, 0, 0, 0.3);
            --info-btn-bg: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
        }
        
        /* Light Theme */
        [data-theme="light"] {
            --bg: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            --card: #ffffff;
            --accent: #5a67d8;
            --accent-hover: #4c51bf;
            --text: #1a202c;
            --muted: #718096;
            --hover: #f7fafc;
            --border: #cbd5e0;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 15px 50px rgba(0, 0, 0, 0.12);
            --footer-text: #2d3748;
            --footer-shadow: rgba(255, 255, 255, 0.8);
            --stats-bg: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --stats-border: rgba(0, 0, 0, 0.08);
            --stats-divider: rgba(0, 0, 0, 0.06);
            --stats-shadow: rgba(0, 0, 0, 0.06);
            --info-btn-bg: linear-gradient(135deg, #718096 0%, #4a5568 100%);
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
            padding: 0;
            min-height: 100vh;
            line-height: 1.6;
        }
        
        /* ================================================================
           LAYOUT COMPONENTS
           ================================================================ */
        
        /* Add top margin to container when auth bar is present */
        <?php if ($authEnabled && isAuthenticated()): ?>
        .container {
            margin-top: 32px;
        }
        <?php endif; ?>
        
        .container { 
            max-width: 85%; 
            margin: 0 auto; 
        }
        
        .card { 
            background: var(--card); 
            border-radius: 16px; 
            box-shadow: var(--shadow); 
            padding: 32px;
            margin-top: 50px;
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
            margin-bottom: 0; 
            color: var(--muted); 
            font-size: clamp(0.875rem, 2vw, 1rem);
            font-weight: 400;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        
        .header-left {
            flex: 1;
            min-width: 0;
        }
        
        .header-right {
            display: flex;
            align-items: flex-start;
            flex-shrink: 0;
        }
        
        /* ================================================================
           NAVIGATION & BREADCRUMBS
           ================================================================ */
        .breadcrumbs-container {
            margin-bottom: 24px;
        }
        
        .breadcrumbs { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
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
        
        .pagination-amount-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            white-space: nowrap;
        }
        
        .pagination-amount-selector label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text);
            margin: 0;
        }
        
        .pagination-amount-selector select {
            padding: 6px 28px 6px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background-color: var(--card);
            color: var(--text);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 12px;
        }
        
        .pagination-amount-selector select:hover {
            border-color: var(--accent);
        }
        
        .pagination-amount-selector select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            margin-top: 8px; 
        }
        
        .file-list a { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            padding: 10px 14px; 
            border: 2px solid var(--border); 
            border-radius: 12px; 
            text-decoration: none; 
            color: var(--text); 
            background: var(--card);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-height: 48px;
        }
        
        /* Add right padding on hover when action buttons appear to prevent overlap with file size */
        <?php if ($enableRename && $enableDelete): ?>
        .file-list li:hover a {
            padding-right: 104px; /* Space for both rename (36px) + delete (36px) + gaps */
        }
        <?php elseif ($enableRename || $enableDelete): ?>
        .file-list li:hover a {
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
        
        /* Always show rename/delete buttons on touch-only devices */
        @media (hover: none) and (pointer: coarse) {
            .rename-btn,
            .delete-btn {
                opacity: 1;
            }
            
            /* Add padding to prevent file size from being hidden by always-visible buttons */
            <?php if ($enableRename && $enableDelete): ?>
            .file-list li a {
                padding-right: 104px; /* Space for both rename (36px) + delete (36px) + gaps */
            }
            <?php elseif ($enableRename || $enableDelete): ?>
            .file-list li a {
                padding-right: 60px; /* Space for one button (36px) + gap */
            }
            <?php endif; ?>
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
        
        .selected-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            color: white;
            font-weight: 700;
            padding: 8px 14px;
            background: var(--info-btn-bg);
            border-radius: 0;
            box-shadow: none;
            letter-spacing: 0.01em;
            white-space: nowrap;
        }
        
        .batch-actions-container {
            display: flex;
            align-items: center;
            gap: 0;
            flex-wrap: wrap;
        }
        
        /* Style all elements in batch group (selected count and buttons) when visible */
        .batch-actions-container > .selected-count:not(.batch-btn-hidden),
        .batch-actions-container > .batch-download-btn:not(.batch-btn-hidden),
        .batch-actions-container > .batch-delete-btn:not(.batch-btn-hidden) {
            border-radius: 0;
            box-shadow: none;
        }
        
        /* First visible element gets left rounded corners */
        .batch-actions-container > .selected-count:not(.batch-btn-hidden):first-child,
        .batch-actions-container > .batch-download-btn:not(.batch-btn-hidden):first-child,
        .batch-actions-container > .batch-delete-btn:not(.batch-btn-hidden):first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        
        /* Last visible element gets right rounded corners */
        .batch-actions-container > :is(.selected-count, .batch-download-btn, .batch-delete-btn):not(.batch-btn-hidden):last-of-type {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        
        /* Add visual separator between visible elements in button group */
        .batch-actions-container > .selected-count:not(.batch-btn-hidden):not(:last-of-type),
        .batch-actions-container > .batch-download-btn:not(.batch-btn-hidden):not(:last-of-type),
        .batch-actions-container > .batch-delete-btn:not(.batch-btn-hidden):not(:last-of-type) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
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
            background: var(--stats-bg);
            border-radius: 10px;
            border: 1px solid var(--stats-border);
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 2px 12px var(--stats-shadow);
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
            border-top: 1px solid var(--stats-divider);
        }
        
        /* ================================================================
           FOOTER & BRANDING
           ================================================================ */
        footer { 
            margin-top: 20px; 
            text-align: center; 
            font-size: clamp(0.75rem, 2vw, 0.875rem);
            color: var(--footer-text);
            font-weight: 400;
            text-shadow: 0 1px 3px var(--footer-shadow);
        }
        
        footer a {
            display: inline-block;
            transition: transform 0.2s ease;
            color: var(--footer-text);
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
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            font-weight: 700; 
            text-decoration: none; 
            cursor: pointer; 
            transition: all 0.25s ease; 
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
        }
        
        .download-all-btn:hover { 
            background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
            filter: brightness(1.1);
        }
        
        .download-all-btn:active {
            filter: brightness(0.95);
        }
        
        .download-all-btn i { 
            font-size: 0.9rem; 
        }
        
        /* ================================================================
           AUTHENTICATION STYLES
           ================================================================ */
        
        /* Authentication Bar - Docked at top */
        .auth-bar {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 12px 0;
        }
        
        .auth-bar-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        
        .auth-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 14px;
            font-weight: 500;
        }
        
        .auth-username {
            font-weight: 600;
        }
        
        .auth-badge {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .auth-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .auth-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .auth-btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(4px);
        }
        
        .auth-btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .auth-btn-primary:active {
            transform: translateY(0);
        }
        
        .auth-btn-secondary {
            background: white;
            color: var(--accent);
        }
        
        .auth-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .auth-btn-secondary:active {
            transform: translateY(0);
        }
        
        .auth-btn i {
            font-size: 14px;
        }
        
        /* Login form button hover */
        #loginBtn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
        }
        
        #loginBtn:active {
            transform: translateY(0);
        }
        
        /* User management button hover */
        #userManagementBtn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        #userManagementBtn:active {
            transform: translateY(0);
            filter: brightness(0.95);
        }
        
        /* Settings button hover */
        #settingsBtn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        #settingsBtn:active {
            transform: translateY(0);
            filter: brightness(0.95);
        }
        
        /* ================================================================
           RESPONSIVE DESIGN - MEDIA QUERIES
           ================================================================ */
        
        /* Mobile phones (portrait) */
        @media (max-width: 480px) {
            /* Auth bar mobile adjustments */
            .auth-bar-content {
                flex-direction: column;
                gap: 12px;
                padding: 0 16px;
            }
            
            .auth-user-info {
                width: 100%;
                justify-content: center;
                font-size: 13px;
            }
            
            .auth-actions {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .auth-btn {
                flex: 1;
                min-width: 0;
                justify-content: center;
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .auth-btn span {
                display: none;
            }
            
            .auth-btn i {
                font-size: 16px;
            }
            
            /* Add top margin to container when auth bar present */
            <?php if ($authEnabled && isAuthenticated()): ?>
            .container {
                margin-top: 20px;
            }
            <?php endif; ?>
            
            .card {
                padding: 16px 16px 20px 16px;
                margin-top: 50px;
                border-radius: 12px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .subtitle {
                font-size: 0.875rem;
                margin-bottom: 0;
            }
            
            .header-container {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
                margin-bottom: 20px;
            }
            
            .header-right {
                width: 100%;
            }
            
            .pagination-amount-selector {
                width: 100%;
                justify-content: space-between;
            }
            
            .pagination-amount-selector select {
                flex: 1;
                max-width: 100px;
            }
            
            .breadcrumbs {
                padding: 12px 14px;
                gap: 6px;
                font-size: 0.813rem;
            }
            
            .file-list a {
                padding: 12px 10px;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .file-icon {
                font-size: 1.25rem;
                width: 28px;
            }
            
            /* On mobile: icon and size on first row (left side), name wraps below */
            .file-name {
                font-size: 0.875rem;
                flex: 1 1 100%;
                order: 2;
            }
            
            .file-size {
                font-size: 0.75rem;
                padding-left: 8px;
                margin-left: 0;
                flex: 0 0 auto;
                text-align: left;
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
            .file-list li a {
                padding-right: 92px; /* Smaller buttons on mobile: 32px + 32px + gaps */
            }
            <?php elseif ($enableRename || $enableDelete): ?>
            .file-list li a {
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
            
            .batch-actions-container {
                width: 100%;
                flex-direction: column;
            }
            
            .selected-count,
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
            /* Add top margin to container when auth bar present */
            <?php if ($authEnabled && isAuthenticated()): ?>
            .container {
                margin-top: 24px;
            }
            <?php endif; ?>
            
            .card {
                padding: 20px 20px 24px 20px;
                margin-top: 50px;
            }
            
            .file-list a {
                padding: 12px 14px;
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
            
            .selected-count,
            .batch-download-btn,
            .batch-delete-btn {
                width: 100%;
                justify-content: center;
                padding: 11px 16px;
            }
        }
        
        /* Tablets (portrait) */
        @media (min-width: 769px) and (max-width: 1024px) {
            /* Auth bar tablet adjustments */
            .auth-bar-content {
                padding: 0 20px;
            }
            
            /* Add top margin to container when auth bar present */
            <?php if ($authEnabled && isAuthenticated()): ?>
            .container {
                margin-top: 28px;
            }
            <?php endif; ?>
            
            .card {
                padding: 24px 24px 28px 24px;
                margin-top: 50px;
            }
        }
        
        /* Large screens */
        @media (min-width: 1025px) {
            .card {
                padding: 40px 40px 36px 40px;
                margin-top: 50px;
            }
            
            .file-list a:hover {
                transform: translateX(12px);
            }
        }
        
        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .file-list a {
                min-height: 52px;
                padding: 14px 12px;
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
        .preview-tooltip video {
            max-width: 100%;
            max-height: 380px;
            display: block;
            border-radius: 4px;
        }
        
        .preview-tooltip video {
            background: #000;
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
           UPLOAD MODAL & DRAG-DROP
           ================================================================ */
        .upload-modal {
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
        
        .upload-modal.active {
            display: flex;
        }
        
        .upload-modal-content {
            background: white;
            border-radius: 12px;
            padding: 28px;
            max-width: 600px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        .upload-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .upload-modal-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 20px;
        }
        
        .upload-modal-error,
        .upload-modal-success {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            display: none;
        }
        
        .upload-modal-error {
            background: #fee;
            color: #c00;
        }
        
        .upload-modal-success {
            background: #d4edda;
            color: #155724;
        }
        
        .upload-modal-error.active,
        .upload-modal-success.active {
            display: block;
        }
        
        .upload-drop-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-drop-zone:hover,
        .upload-drop-zone.drag-over {
            border-color: var(--accent);
            background: rgba(102, 126, 234, 0.05);
        }
        
        .upload-drop-zone i {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 16px;
            display: block;
        }
        
        .upload-drop-zone p {
            color: var(--muted);
            margin-bottom: 16px;
            font-size: 0.95rem;
        }
        
        .upload-select-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .upload-select-btn:hover {
            background: var(--accent-hover);
        }
        
        .upload-file-list {
            margin-bottom: 20px;
        }
        
        .upload-file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: var(--hover);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .upload-file-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }
        
        .upload-file-info i {
            color: var(--accent);
            font-size: 1.2rem;
        }
        
        .upload-file-details {
            flex: 1;
            min-width: 0;
        }
        
        .upload-file-name {
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .upload-file-size {
            font-size: 0.85rem;
            color: var(--muted);
        }
        
        .upload-file-remove {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 4px 8px;
            font-size: 1.2rem;
            transition: color 0.2s;
        }
        
        .upload-file-remove:hover {
            color: #c0392b;
        }
        
        .upload-file-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 8px;
        }
        
        .upload-file-status i {
            font-size: 1.2rem;
        }
        
        .upload-file-status.pending i {
            color: var(--muted);
        }
        
        .upload-file-status.uploading i {
            color: var(--accent);
            animation: spin 1s linear infinite;
        }
        
        .upload-file-status.completed i {
            color: #27ae60;
        }
        
        .upload-file-status.failed i {
            color: #e74c3c;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .upload-progress-container {
            margin-bottom: 20px;
            display: none;
        }
        
        .upload-progress-container.active {
            display: block;
        }
        
        .upload-progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .upload-progress-fill {
            height: 100%;
            background: var(--accent);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .upload-progress-text {
            font-size: 0.85rem;
            color: var(--muted);
            text-align: center;
        }
        
        .upload-modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .upload-modal-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .upload-modal-btn-cancel {
            background: #e0e0e0;
            color: #333;
        }
        
        .upload-modal-btn-cancel:hover {
            background: #d0d0d0;
        }
        
        .upload-modal-btn-confirm {
            background: var(--accent);
            color: white;
        }
        
        .upload-modal-btn-confirm:hover:not(:disabled) {
            background: var(--accent-hover);
        }
        
        .upload-modal-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .upload-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .upload-btn:hover {
            background: var(--accent-hover);
        }
        
        .upload-btn i {
            font-size: 1rem;
        }
        
        /* Create directory button */
        .create-dir-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .create-dir-btn:hover {
            background: var(--accent-hover);
        }
        
        .create-dir-btn i {
            font-size: 1rem;
        }
        
        /* Drag and drop overlay (full screen) */
        .drag-drop-overlay {
            position: fixed;
            inset: 0;
            background: rgba(102, 126, 234, 0.95);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10002;
            pointer-events: none;
        }
        
        .drag-drop-overlay.active {
            display: flex;
        }
        
        .drag-drop-content {
            text-align: center;
            color: white;
            pointer-events: none;
        }
        
        .drag-drop-content i {
            font-size: 6rem;
            margin-bottom: 24px;
            display: block;
            animation: bounce 1s infinite;
        }
        
        .drag-drop-content p {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        /* ================================================================
           CREATE DIRECTORY MODAL
           ================================================================ */
        .create-dir-modal {
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
        
        .create-dir-modal.active {
            display: flex;
        }
        
        .create-dir-modal-content {
            background: white;
            border-radius: 12px;
            padding: 28px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        .create-dir-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .create-dir-modal-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 20px;
        }
        
        .create-dir-modal-error {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            background: #fee;
            color: #c00;
            display: none;
        }
        
        .create-dir-modal-error.active {
            display: block;
        }
        
        .create-dir-modal-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 20px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        .create-dir-modal-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .create-dir-modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .create-dir-modal-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .create-dir-modal-btn-cancel {
            background: #e0e0e0;
            color: #333;
        }
        
        .create-dir-modal-btn-cancel:hover {
            background: #d0d0d0;
        }
        
        .create-dir-modal-btn-confirm {
            background: var(--accent);
            color: white;
        }
        
        .create-dir-modal-btn-confirm:hover:not(:disabled) {
            background: var(--accent-hover);
        }
        
        .create-dir-modal-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* ================================================================
           TOAST NOTIFICATION SYSTEM
           ================================================================ */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10001;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }
        
        .toast {
            min-width: 300px;
            max-width: 500px;
            padding: 16px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: all;
            border-left: 4px solid;
        }
        
        .toast.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .toast.removing {
            opacity: 0;
            transform: translateX(400px);
        }
        
        .toast-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .toast-message {
            flex: 1;
            color: #333;
            font-size: 0.95rem;
            line-height: 1.4;
            word-wrap: break-word;
            white-space: pre-line;
        }
        
        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            font-size: 1.2rem;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
            flex-shrink: 0;
        }
        
        .toast-close:hover {
            color: #666;
        }
        
        /* Toast types */
        .toast.error {
            border-left-color: #e74c3c;
        }
        
        .toast.error .toast-icon {
            color: #e74c3c;
        }
        
        .toast.success {
            border-left-color: #27ae60;
        }
        
        .toast.success .toast-icon {
            color: #27ae60;
        }
        
        .toast.info {
            border-left-color: #3498db;
        }
        
        .toast.info .toast-icon {
            color: #3498db;
        }
        
        .toast.warning {
            border-left-color: #f39c12;
        }
        
        .toast.warning .toast-icon {
            color: #f39c12;
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
                align-items: stretch;
            }
            
            .toast {
                min-width: auto;
                max-width: none;
            }
        }
        
        /* ================================================================
           THEME SETTINGS BUTTON & MODAL
           ================================================================ */
        .theme-settings-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 999;
        }
        
        .theme-settings-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }
        
        .theme-settings-btn:active {
            transform: scale(1.05) rotate(90deg);
        }
        
        .theme-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .theme-modal.active {
            display: flex;
        }
        
        .theme-modal-content {
            background: var(--card);
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-hover);
            animation: modalSlideIn 0.3s ease;
        }
        
        .theme-modal-title {
            margin: 0 0 8px 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }
        
        .theme-modal-subtitle {
            margin-bottom: 24px;
            color: var(--muted);
            font-size: 0.95rem;
        }
        
        .theme-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .theme-option {
            position: relative;
            border: 3px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--card);
            text-align: center;
        }
        
        .theme-option:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
            border-color: var(--accent);
        }
        
        .theme-option.selected {
            border-color: var(--accent);
            background: var(--hover);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .theme-option.selected::after {
            content: '✓';
            position: absolute;
            top: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .theme-option-preview {
            width: 100%;
            height: 60px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            gap: 4px;
            padding: 8px;
        }
        
        .theme-option-preview-color {
            flex: 1;
            border-radius: 4px;
        }
        
        /* Theme preview colors */
        .theme-option[data-theme="purple"] .theme-option-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .theme-option[data-theme="blue"] .theme-option-preview {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .theme-option[data-theme="green"] .theme-option-preview {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .theme-option[data-theme="dark"] .theme-option-preview {
            background: linear-gradient(135deg, #232526 0%, #414345 100%);
        }
        
        .theme-option[data-theme="light"] .theme-option-preview {
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            border: 1px solid #cbd5e0;
        }
        
        .theme-option-name {
            font-weight: 600;
            color: var(--text);
            font-size: 0.95rem;
        }
        
        .theme-modal-close-btn {
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--accent);
            color: white;
        }
        
        .theme-modal-close-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 480px) {
            .theme-settings-btn {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
                bottom: 16px;
                right: 16px;
            }
            
            .theme-modal-content {
                padding: 24px;
            }
            
            .theme-options {
                grid-template-columns: 1fr;
            }
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 20px;
            }
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
        
        /* ================================================================
           MUSIC PLAYER STYLES
           ================================================================ */
        /* Play/Pause button overlay on audio file icons */
        li.audio-file {
            position: relative;
        }
        
        li.audio-file .file-icon {
            position: relative;
        }
        
        .audio-play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 32px;
            height: 32px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease, background 0.2s ease;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        li.audio-file:hover .audio-play-btn,
        .audio-play-btn.playing {
            opacity: 1;
        }
        
        .audio-play-btn:hover {
            background: var(--accent-hover);
            transform: translate(-50%, -50%) scale(1.1);
        }
        
        .audio-play-btn i {
            font-size: 14px;
            margin-left: 2px; /* Offset play icon to center visually */
        }
        
        .audio-play-btn.playing i {
            margin-left: 0; /* Pause icon is already centered */
        }
        
        /* Progress bar effect on list item background */
        li.audio-playing a {
            position: relative;
            overflow: hidden;
            background: rgba(102, 126, 234, 0.15); /* Distinctive background for playing file */
        }
        
        /* Override the accent bar when audio is playing to show progress bar instead */
        li.audio-playing a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: var(--audio-progress, 0%);
            background: linear-gradient(90deg, 
                rgba(102, 126, 234, 0.35) 0%, 
                rgba(102, 126, 234, 0.20) 100%
            );
            transition: width 0.1s linear;
            transform: scaleY(1);  /* Override the accent bar transform */
            z-index: 0;
            pointer-events: none;
        }
        
        li.audio-playing a > * {
            position: relative;
            z-index: 1;
        }
        
        /* Dark theme adjustments for progress bar */
        [data-theme="dark"] li.audio-playing a {
            background: rgba(102, 126, 234, 0.20); /* More visible in dark theme */
        }
        
        [data-theme="dark"] li.audio-playing a::before {
            background: linear-gradient(90deg, 
                rgba(102, 126, 234, 0.45) 0%, 
                rgba(102, 126, 234, 0.25) 100%
            );
        }
        
        /* Light theme adjustments for progress bar */
        [data-theme="light"] li.audio-playing a {
            background: rgba(102, 126, 234, 0.12); /* Lighter for light theme */
        }
        
        [data-theme="light"] li.audio-playing a::before {
            background: linear-gradient(90deg, 
                rgba(102, 126, 234, 0.25) 0%, 
                rgba(102, 126, 234, 0.15) 100%
            );
        }
        
        /* Time counter for playing audio */
        .audio-time-counter {
            font-size: clamp(0.7rem, 2vw, 0.8rem);
            color: var(--muted);
            white-space: nowrap;
            margin-left: 8px;
            padding-left: 8px;
            border-left: 1px solid var(--border);
            font-weight: 500;
            font-family: 'Courier New', monospace; /* Monospace for better time display */
        }
        
        /* Mobile: Always show play button for audio files */
        @media (max-width: 768px) {
            .audio-play-btn {
                opacity: 0.8;
            }
        }
        
        /* ================================================================
           VIDEO PLAYER STYLES
           ================================================================ */
        /* Play button overlay on video file icons */
        li.video-file {
            position: relative;
        }
        
        li.video-file .file-icon {
            position: relative;
        }
        
        .video-play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 32px;
            height: 32px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease, background 0.2s ease;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        li.video-file:hover .video-play-btn {
            opacity: 1;
        }
        
        .video-play-btn:hover {
            background: var(--accent-hover);
            transform: translate(-50%, -50%) scale(1.1);
        }
        
        .video-play-btn i {
            font-size: 14px;
            margin-left: 2px; /* Offset play icon to center visually */
        }
        
        /* Mobile: Always show play button for video files */
        @media (max-width: 768px) {
            .video-play-btn {
                opacity: 0.8;
            }
        }
        
        /* Video player lightbox modal */
        .video-player-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .video-player-modal.active {
            display: flex;
        }
        
        .video-player-container {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            width: auto;
            height: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        
        .video-player-wrapper {
            position: relative;
            width: 100%;
            max-width: 1200px;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }
        
        .video-player-wrapper video {
            width: 100%;
            height: auto;
            display: block;
            max-height: 80vh;
        }
        
        .video-player-title {
            color: white;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            max-width: 100%;
            word-break: break-word;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .video-player-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            color: white;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
            z-index: 10001;
        }
        
        .video-player-close:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.1);
        }
        
        .video-player-error {
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 16px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .video-player-container {
                max-width: 95%;
                max-height: 95%;
                gap: 15px;
            }
            
            .video-player-title {
                font-size: 16px;
            }
            
            .video-player-close {
                top: 10px;
                right: 10px;
                width: 36px;
                height: 36px;
                font-size: 18px;
            }
        }
    </style>
</head>

<body>
    <?php if ($authEnabled && isAuthenticated() && isset($_SESSION['user'])): ?>
    <!-- Authentication Bar -->
    <div class="auth-bar">
        <div class="auth-bar-content">
            <div class="auth-user-info">
                <i class="fa-solid fa-circle-user" style="font-size: 18px;"></i>
                <span class="auth-username"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
                <?php if (isAdmin()): ?>
                <span class="auth-badge">ADMIN</span>
                <?php endif; ?>
            </div>
            <div class="auth-actions">
                <?php if (isAdmin()): ?>
                <button id="userManagementBtn" class="auth-btn auth-btn-primary" title="User Management">
                    <i class="fa-solid fa-users"></i>
                    <span>Manage Users</span>
                </button>
                <button id="settingsBtn" class="auth-btn auth-btn-primary" title="Feature Settings">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Settings</span>
                </button>
                <?php endif; ?>
                <a href="?logout=1" class="auth-btn auth-btn-secondary">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($requireLogin): ?>
    <!-- Login Form -->
    <div class="container">
        <div class="card" style="max-width: 450px; margin: 100px auto;">
            <div class="header-container">
                <div class="header-left">
                    <h1><?php echo htmlspecialchars($title); ?></h1>
                    <div class="subtitle">Authentication Required</div>
                </div>
            </div>
            <div style="padding: 30px;">
                <form id="loginForm" style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <label for="username" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text);">Username</label>
                        <input type="text" id="username" name="username" required 
                               style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); font-size: 14px; box-sizing: border-box;"
                               autocomplete="username">
                    </div>
                    <div>
                        <label for="password" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text);">Password</label>
                        <input type="password" id="password" name="password" required 
                               style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); font-size: 14px; box-sizing: border-box;"
                               autocomplete="current-password">
                    </div>
                    <div id="loginError" style="display: none; color: #ef4444; background: #fee; padding: 12px; border-radius: 8px; font-size: 14px;"></div>
                    <button type="submit" id="loginBtn" 
                            style="width: 100%; padding: 14px; background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="container">
        <div class="card">
            <div class="header-container">
                <div class="header-left">
                    <h1><?php echo htmlspecialchars($title); ?></h1>
                    <?php if (!empty($subtitle)): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
                </div>
                <?php if ($enablePaginationAmountSelector && !($authEnabled && isAuthenticated())): ?>
                <div class="header-right">
                    <div class="pagination-amount-selector">
                        <label for="paginationAmount">Items per page:</label>
                        <select id="paginationAmount" aria-label="Items per page">
                            <option value="5" <?php echo $paginationAmount === '5' ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $paginationAmount === '10' ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $paginationAmount === '20' ? 'selected' : ''; ?>>20</option>
                            <option value="30" <?php echo $paginationAmount === '30' ? 'selected' : ''; ?>>30</option>
                            <option value="50" <?php echo $paginationAmount === '50' ? 'selected' : ''; ?>>50</option>
                            <option value="all" <?php echo $paginationAmount === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($breadcrumbs)): ?>
            <div class="breadcrumbs-container">
                <div class="breadcrumbs">
                    <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>">Home</a>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        &gt;
                        <a href="?path=<?php echo rawurlencode($breadcrumb['path']); ?>" class="dir-link">
                            <?php echo $breadcrumb['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
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
                            // Skip hidden files, index.php, and config files
                            if (in_array($entry, ['.', '..', 'index.php', 'SPFL-Config.json', 'SPFL-Users.json'], true) || (!$includeHiddenFiles && $entry[0] === '.')) {
                                continue;
                            }

                            $fullPath = $basePath . '/' . $entry;
                            $realPath = realpath($fullPath);

                            if (is_link($fullPath) || $realPath === false || strpos($realPath, $realRoot) !== 0) {
                                continue;
                            }

                            if (is_dir($fullPath)) {
                                // Calculate directory size
                                $dirSize = calculateDirectorySize($fullPath, $realRoot, $includeHiddenFiles);
                                $dirs[] = ['name' => $entry, 'size' => $dirSize];
                                $totalSize += $dirSize;
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

                    // Sort directories by name (now $dirs is an array of ['name' => string, 'size' => int])
                    usort($dirs, function($a, $b) {
                        return strnatcasecmp($a['name'], $b['name']);
                    });
                    usort($files, function($a, $b) {
                        return strnatcasecmp($a['name'], $b['name']);
                    });

                    // Pagination logic: combine dirs and files for pagination
                    // Note: $dirs is an array of ['name' => string, 'size' => int] arrays
                    // Note: $files is an array of ['name' => string, 'size' => int] arrays
                    $totalItems = count($dirs) + count($files);
                    
                    // Store if we have items for later use in stats container
                    $hasItemsToSelect = $totalItems > 0;
                    
                    // Calculate total pages based on selected pagination amount
                    if ($paginationAmount === 'all') {
                        $totalPages = 1;
                    } else {
                        // Only show pagination if items exceed the selected amount
                        $totalPages = ($totalItems > $itemsPerPageActual) ? (int)ceil($totalItems / $itemsPerPageActual) : 1;
                    }
                    
                    // Ensure current page is within valid range
                    $currentPage = max(1, min($currentPage, $totalPages));
                    
                    // Calculate pagination offsets
                    $itemsPerPage = $itemsPerPageActual;
                    $offset = ($currentPage - 1) * $itemsPerPage;
                    
                    // Merge dirs and files into a single array for pagination
                    // Convert dirs and files to standardized format: ['type' => 'dir'/'file', 'name' => string, 'size' => int]
                    $allItems = [];
                    foreach ($dirs as $dir) {
                        $allItems[] = ['type' => 'dir', 'name' => $dir['name'], 'size' => $dir['size']];
                    }
                    foreach ($files as $file) {
                        $allItems[] = ['type' => 'file', 'name' => $file['name'], 'size' => $file['size']];
                    }
                    
                    // Get items for current page
                    $itemsToDisplay = array_slice($allItems, $offset, $itemsPerPage);

                    // Check user permissions for UI elements
                    // Admins can bypass global feature toggles when auth is enabled
                    $canRename = ($enableRename || ($authEnabled && isAdmin())) && hasPermission('rename');
                    $canDelete = ($enableDelete || ($authEnabled && isAdmin())) && hasPermission('delete');
                    $canDownload = ($enableIndividualDownload || ($authEnabled && isAdmin())) && hasPermission('download');
                    $canShowCheckbox = (($enableBatchDownload || ($authEnabled && isAdmin())) && hasPermission('download')) || (($enableDelete || ($authEnabled && isAdmin())) && hasPermission('delete'));

                    foreach ($itemsToDisplay as $item) {
                        if ($item['type'] === 'dir') {
                            renderItem($item['name'], true, $currentPath, $item['size'], $canRename, $canDelete, $canShowCheckbox, $showFileSize, $canDownload);
                        } else {
                            renderItem($item['name'], false, $currentPath, $item['size'], $canRename, $canDelete, $canShowCheckbox, $showFileSize, $canDownload);
                        }
                    }
                }
                ?>
            </ul>

            <?php
            // Display pagination controls if needed
            if ($isValidPath && $totalPages > 1) {
                // Build base URL for pagination links (preserve current path and per_page)
                $baseUrl = '?';
                if ($currentPath) {
                    $baseUrl .= 'path=' . rawurlencode($currentPath) . '&';
                }
                if (isset($_GET['per_page'])) {
                    $baseUrl .= 'per_page=' . rawurlencode($_GET['per_page']) . '&';
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
                $hasBatchActions = isset($hasItemsToSelect) && $hasItemsToSelect && ((($enableBatchDownload || ($authEnabled && isAdmin())) && hasPermission('download')) || (($enableDelete || ($authEnabled && isAdmin())) && hasPermission('delete')));
                $hasDownloadAll = ($enableDownloadAll || ($authEnabled && isAdmin())) && hasPermission('download') && hasDownloadableContent($basePath, $realRoot, $includeHiddenFiles);
                $showStatsContainer = $hasStatsToShow || $hasBatchActions || $hasDownloadAll;
                
                if ($showStatsContainer) {
                    echo '<div class="stats-container">';
                    
                    // First row: folder/file count and Select All checkbox
                    echo '<div class="stats-top-row">';
                    if (!empty($statsHtml)) {
                        echo '<div class="folder-file-count">' . $statsHtml . '</div>';
                    }
                    
                    // Multi-select checkbox (when items are available and batch download or delete is enabled)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && (($enableBatchDownload || ($authEnabled && isAdmin())) || ($enableDelete || ($authEnabled && isAdmin())))) {
                        // Build list of all items (for select all across pagination)
                        $allItemsData = [];
                        foreach ($allItems as $item) {
                            $itemPath = $currentPath ? $currentPath . '/' . $item['name'] : $item['name'];
                            $allItemsData[] = [
                                'path' => $itemPath,
                                'name' => $item['name'],
                                'isDir' => $item['type'] === 'dir',
                                'size' => $item['size']
                            ];
                        }
                        
                        echo '<label class="select-all-container">';
                        echo '<input type="checkbox" id="selectAllCheckbox" aria-label="Select all items">';
                        echo '<span>Select All</span>';
                        echo '</label>';
                        
                        // Hidden data element with all items for pagination support
                        echo '<script nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '" type="application/json" id="allItemsData">';
                        echo json_encode($allItemsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                        echo '</script>';
                    }
                    echo '</div>'; // end stats-top-row
                    
                    // Second row: action buttons (with selection count integrated into button group)
                    echo '<div class="stats-actions-row">';
                    
                    // Batch action buttons container (including selection count as first element)
                    echo '<div class="batch-actions-container">';
                    
                    // Selection count display as first element in button group (when items are available and batch download or delete is enabled)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && ((($enableBatchDownload || ($authEnabled && isAdmin())) && hasPermission('download')) || (($enableDelete || ($authEnabled && isAdmin())) && hasPermission('delete')))) {
                        echo '<span class="selected-count batch-btn-hidden" id="selectedCount">0 selected</span>';
                    }
                    
                    // Batch download button (hidden by default, shown when items selected)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && ($enableBatchDownload || ($authEnabled && isAdmin())) && hasPermission('download')) {
                        echo '<button class="batch-download-btn batch-btn-hidden" id="batchDownloadBtn" title="Download selected as ZIP">';
                        echo '<i class="fa-solid fa-download"></i> Download Selected';
                        echo '</button>';
                    }
                    
                    // Batch delete button (hidden by default, shown when items selected)
                    if (isset($hasItemsToSelect) && $hasItemsToSelect && ($enableDelete || ($authEnabled && isAdmin())) && hasPermission('delete')) {
                        echo '<button class="batch-delete-btn batch-btn-hidden" id="batchDeleteBtn" title="Delete selected items">';
                        echo '<i class="fa-solid fa-trash"></i> Delete Selected';
                        echo '</button>';
                    }
                    
                    echo '</div>'; // end batch-actions-container
                    
                    // Show download all button if enabled and content check already passed (separate from batch actions)
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
                    
                    // Show upload button if enabled and user has permission (admins can bypass toggle when auth is enabled)
                    if (($enableUpload || ($authEnabled && isAdmin())) && hasPermission('upload')) {
                        echo '<button class="upload-btn" id="uploadBtn" title="Upload files">';
                        echo '<i class="fa-solid fa-upload"></i>';
                        echo 'Upload Files';
                        echo '</button>';
                    }
                    
                    // Show create directory button if enabled and user has permission (admins can bypass toggle when auth is enabled)
                    if (($enableCreateDirectory || ($authEnabled && isAdmin())) && hasPermission('create_directory')) {
                        echo '<button class="create-dir-btn" id="createDirBtn" title="Create new folder">';
                        echo '<i class="fa-solid fa-folder-plus"></i>';
                        echo 'New Folder';
                        echo '</button>';
                    }
                    
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

    <!-- Upload Modal -->
    <div class="upload-modal" id="uploadModal" role="dialog" aria-labelledby="uploadModalTitle" aria-modal="true">
        <div class="upload-modal-content">
            <h2 class="upload-modal-title" id="uploadModalTitle">Upload Files</h2>
            <p class="upload-modal-subtitle">Select files to upload to this directory. Max file size: <?php echo htmlspecialchars(formatFileSize($uploadMaxFileSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            <div class="upload-modal-error" id="uploadModalError"></div>
            <div class="upload-modal-success" id="uploadModalSuccess"></div>
            <div class="upload-drop-zone" id="uploadDropZone">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p>Drag and drop files here or click to select</p>
                <input type="file" id="uploadFileInput" multiple hidden>
                <button class="upload-select-btn" id="uploadSelectBtn">Select Files</button>
            </div>
            <div class="upload-progress-container" id="uploadProgressContainer">
                <div class="upload-progress-bar">
                    <div class="upload-progress-fill" id="uploadProgressFill"></div>
                </div>
                <div class="upload-progress-text" id="uploadProgressText">Uploading files...</div>
            </div>
            <div class="upload-file-list" id="uploadFileList"></div>
            <div class="upload-modal-buttons">
                <button class="upload-modal-btn upload-modal-btn-cancel" id="uploadModalCancel">Cancel</button>
                <button class="upload-modal-btn upload-modal-btn-confirm" id="uploadModalConfirm" disabled>Upload</button>
            </div>
        </div>
    </div>

    <!-- Create Directory Modal -->
    <div class="create-dir-modal" id="createDirModal" role="dialog" aria-labelledby="createDirModalTitle" aria-modal="true">
        <div class="create-dir-modal-content">
            <h2 class="create-dir-modal-title" id="createDirModalTitle">Create New Folder</h2>
            <p class="create-dir-modal-subtitle">Enter a name for the new folder</p>
            <div class="create-dir-modal-error" id="createDirModalError"></div>
            <input type="text" class="create-dir-modal-input" id="createDirModalInput" placeholder="Folder name" maxlength="255" autocomplete="off">
            <div class="create-dir-modal-buttons">
                <button class="create-dir-modal-btn create-dir-modal-btn-cancel" id="createDirModalCancel">Cancel</button>
                <button class="create-dir-modal-btn create-dir-modal-btn-confirm" id="createDirModalConfirm">Create</button>
            </div>
        </div>
    </div>

    <!-- Drag and Drop Overlay -->
    <div class="drag-drop-overlay" id="dragDropOverlay">
        <div class="drag-drop-content">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <p>Drop files here to upload</p>
        </div>
    </div>

    <?php if ($allowThemeChange): ?>
    <!-- Theme Settings Button -->
    <button class="theme-settings-btn" id="themeSettingsBtn" title="Change Theme" aria-label="Change Theme">
        <i class="fa-solid fa-palette"></i>
    </button>

    <!-- Theme Settings Modal -->
    <div class="theme-modal" id="themeModal" role="dialog" aria-labelledby="themeModalTitle" aria-modal="true">
        <div class="theme-modal-content">
            <h2 class="theme-modal-title" id="themeModalTitle">Choose a Theme</h2>
            <p class="theme-modal-subtitle">Select your preferred color scheme</p>
            
            <div class="theme-options">
                <div class="theme-option" data-theme="purple" role="button" tabindex="0" aria-label="Purple theme">
                    <div class="theme-option-preview"></div>
                    <div class="theme-option-name">Purple</div>
                </div>
                <div class="theme-option" data-theme="blue" role="button" tabindex="0" aria-label="Blue theme">
                    <div class="theme-option-preview"></div>
                    <div class="theme-option-name">Blue</div>
                </div>
                <div class="theme-option" data-theme="green" role="button" tabindex="0" aria-label="Green theme">
                    <div class="theme-option-preview"></div>
                    <div class="theme-option-name">Green</div>
                </div>
                <div class="theme-option" data-theme="dark" role="button" tabindex="0" aria-label="Dark theme">
                    <div class="theme-option-preview"></div>
                    <div class="theme-option-name">Dark</div>
                </div>
                <div class="theme-option" data-theme="light" role="button" tabindex="0" aria-label="Light theme">
                    <div class="theme-option-preview"></div>
                    <div class="theme-option-name">Light</div>
                </div>
            </div>
            
            <button class="theme-modal-close-btn" id="themeModalCloseBtn">Done</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Video Player Modal -->
    <div class="video-player-modal" id="videoPlayerModal" role="dialog" aria-labelledby="videoPlayerTitle" aria-modal="true">
        <button class="video-player-close" id="videoPlayerClose" aria-label="Close video player">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="video-player-container">
            <div class="video-player-title" id="videoPlayerTitle"></div>
            <div class="video-player-wrapper" id="videoPlayerWrapper">
                <!-- Video element will be inserted here -->
            </div>
        </div>
    </div>

    <div class="loading-overlay" aria-hidden="true">
        <div role="status" aria-live="polite" aria-label="Loading">
            <div class="loading-spinner" aria-hidden="true"></div>
            <div class="loading-text">Loading directory…</div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <?php if (isAdmin()): ?>
    <!-- User Management Modal -->
    <div class="rename-modal" id="userManagementModal" role="dialog" aria-labelledby="userManagementModalTitle" aria-modal="true">
        <div class="rename-modal-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
            <h2 class="rename-modal-title" id="userManagementModalTitle">User Management</h2>
            <div class="rename-modal-error" id="userManagementError"></div>
            
            <!-- User List -->
            <div id="userListContainer" style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px; font-size: 16px; color: var(--text);">Existing Users</h3>
                <div id="userList" style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;"></div>
            </div>
            
            <!-- Add/Edit User Form -->
            <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                <h3 style="margin-bottom: 10px; font-size: 16px; color: var(--text);" id="userFormTitle">Add New User</h3>
                <form id="userForm" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" id="userFormAction" value="create">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--text);">Username</label>
                        <input type="text" id="userFormUsername" required style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--text);">Password <span id="passwordHint" style="font-weight: normal; font-size: 12px; color: var(--muted);">(leave blank to keep current)</span></label>
                        <input type="password" id="userFormPassword" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="userFormAdmin" style="width: 18px; height: 18px; cursor: pointer;">
                            <span style="font-weight: 500; color: var(--text);">Administrator</span>
                        </label>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--text);">Permissions</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 6px;">
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" class="permission-checkbox" value="view" style="cursor: pointer;"> View
                            </label>
                            <?php if ($enableIndividualDownload || $enableDownloadAll || $enableBatchDownload): ?>
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" class="permission-checkbox" value="download" style="cursor: pointer;"> Download
                            </label>
                            <?php endif; ?>
                            <?php if ($enableUpload): ?>
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" class="permission-checkbox" value="upload" style="cursor: pointer;"> Upload
                            </label>
                            <?php endif; ?>
                            <?php if ($enableDelete): ?>
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" class="permission-checkbox" value="delete" style="cursor: pointer;"> Delete
                            </label>
                            <?php endif; ?>
                            <?php if ($enableRename): ?>
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" class="permission-checkbox" value="rename" style="cursor: pointer;"> Rename
                            </label>
                            <?php endif; ?>
                            <?php if ($enableCreateDirectory): ?>
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" class="permission-checkbox" value="create_directory" style="cursor: pointer;"> Create Directory
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="rename-modal-buttons">
                        <button type="button" class="rename-modal-btn rename-modal-btn-cancel" id="userFormCancel">Cancel</button>
                        <button type="submit" class="rename-modal-btn rename-modal-btn-confirm" id="userFormSubmit">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Settings Modal -->
    <div class="rename-modal" id="settingsModal" role="dialog" aria-labelledby="settingsModalTitle" aria-modal="true">
        <div class="rename-modal-content" style="max-width: 600px;">
            <h2 class="rename-modal-title" id="settingsModalTitle">Feature Settings</h2>
            <p style="margin-bottom: 20px; color: var(--muted); font-size: 14px;">Control which features are available to users with appropriate permissions.</p>
            <div class="rename-modal-error" id="settingsError"></div>
            
            <form id="settingsForm" style="display: flex; flex-direction: column; gap: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: var(--surface); border-radius: 6px;">
                    <input type="checkbox" id="enableRename" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text);">Enable Rename</div>
                        <div style="font-size: 12px; color: var(--muted);">Allow users to rename files and directories</div>
                    </div>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: var(--surface); border-radius: 6px;">
                    <input type="checkbox" id="enableDelete" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text);">Enable Delete</div>
                        <div style="font-size: 12px; color: var(--muted);">Allow users to delete files and directories</div>
                    </div>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: var(--surface); border-radius: 6px;">
                    <input type="checkbox" id="enableUpload" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text);">Enable Upload</div>
                        <div style="font-size: 12px; color: var(--muted);">Allow users to upload files</div>
                    </div>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: var(--surface); border-radius: 6px;">
                    <input type="checkbox" id="enableCreateDirectory" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text);">Enable Create Directory</div>
                        <div style="font-size: 12px; color: var(--muted);">Allow users to create new directories</div>
                    </div>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: var(--surface); border-radius: 6px;">
                    <input type="checkbox" id="enableDownloadAll" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text);">Enable Download All</div>
                        <div style="font-size: 12px; color: var(--muted);">Allow downloading entire directories as ZIP</div>
                    </div>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: var(--surface); border-radius: 6px;">
                    <input type="checkbox" id="enableBatchDownload" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text);">Enable Batch Download</div>
                        <div style="font-size: 12px; color: var(--muted);">Allow downloading selected items as ZIP</div>
                    </div>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: var(--surface); border-radius: 6px;">
                    <input type="checkbox" id="enableIndividualDownload" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text);">Enable Individual Download</div>
                        <div style="font-size: 12px; color: var(--muted);">Allow downloading individual files</div>
                    </div>
                </label>
                
                <div class="rename-modal-buttons">
                    <button type="button" class="rename-modal-btn rename-modal-btn-cancel" id="settingsCancel">Cancel</button>
                    <button type="submit" class="rename-modal-btn rename-modal-btn-confirm" id="settingsSave">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; // Close the else for requireLogin ?>

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

            // ================================================================
            // TOAST NOTIFICATION SYSTEM
            // ================================================================
            const toastContainer = document.getElementById('toastContainer');
            let toastIdCounter = 0;

            /**
             * Show a toast notification
             * @param {string} message - The message to display
             * @param {string} type - Type of toast: 'error', 'success', 'info', 'warning'
             * @param {number} duration - Duration in milliseconds (default: 5000, use 0 for no auto-dismiss)
             */
            window.showToast = function(message, type = 'info', duration = 5000) {
                if (!toastContainer) return;

                // Create toast element
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.setAttribute('role', 'alert');
                toast.id = `toast-${toastIdCounter++}`;

                // Determine icon based on type
                let iconClass = 'fa-circle-info';
                if (type === 'error') iconClass = 'fa-circle-exclamation';
                else if (type === 'success') iconClass = 'fa-circle-check';
                else if (type === 'warning') iconClass = 'fa-triangle-exclamation';

                // Build toast using DOM manipulation (safer than innerHTML)
                const icon = document.createElement('i');
                icon.className = `fa-solid ${iconClass} toast-icon`;
                
                const messageDiv = document.createElement('div');
                messageDiv.className = 'toast-message';
                messageDiv.textContent = message; // textContent automatically escapes
                
                const closeBtn = document.createElement('button');
                closeBtn.className = 'toast-close';
                closeBtn.setAttribute('aria-label', 'Close notification');
                
                const closeIcon = document.createElement('i');
                closeIcon.className = 'fa-solid fa-xmark';
                closeBtn.appendChild(closeIcon);
                
                // Assemble toast
                toast.appendChild(icon);
                toast.appendChild(messageDiv);
                toast.appendChild(closeBtn);

                // Add close button handler
                closeBtn.addEventListener('click', function() {
                    removeToast(toast);
                });

                // Add to container
                toastContainer.appendChild(toast);

                // Trigger animation
                setTimeout(() => {
                    toast.classList.add('active');
                }, 10);

                // Auto-remove after duration
                if (duration > 0) {
                    setTimeout(() => {
                        removeToast(toast);
                    }, duration);
                }
            };

            /**
             * Remove a toast notification
             * @param {HTMLElement} toast - The toast element to remove
             */
            function removeToast(toast) {
                if (!toast) return;
                
                toast.classList.add('removing');
                toast.classList.remove('active');
                
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
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
                
                // Clear selection storage when navigating to a different directory
                if (isDirLink) {
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentPath = urlParams.get('path') || '';
                    const currentStorageKey = 'selectedItems_' + currentPath;
                    
                    // Clear current directory's selection when navigating away
                    try {
                        sessionStorage.removeItem(currentStorageKey);
                    } catch (e) {
                        console.error('Failed to clear selection storage:', e);
                    }
                }
                
                if (isDirLink || isPaginationLink) showOverlay();
            }, { capture: true });

            window.addEventListener('beforeunload', showOverlay);
            
            // Pagination amount selector functionality
            (function() {
                const paginationAmountSelect = document.getElementById('paginationAmount');
                
                if (!paginationAmountSelect) return;
                
                paginationAmountSelect.addEventListener('change', function() {
                    const selectedAmount = this.value;
                    
                    // Get current URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    
                    // Update or add the per_page parameter
                    urlParams.set('per_page', selectedAmount);
                    
                    // Reset to page 1 when changing pagination amount
                    urlParams.delete('page');
                    
                    // Build new URL
                    const newUrl = window.location.pathname + '?' + urlParams.toString();
                    
                    // Show loading overlay and navigate
                    showOverlay();
                    window.location.href = newUrl;
                });
            })();
            
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
                    
                    // Skip audio files - they have their own player
                    if (previewType === 'audio') {
                        console.log('[Preview] Skipping preview for audio file - music player handles this');
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
            
            // Music player functionality
            (function() {
                console.log('[Music Player] Initializing music player functionality');
                
                let currentAudio = null;
                let currentListItem = null;
                let currentLink = null;
                let currentButton = null;
                
                // Find all audio file items and add play buttons
                function initAudioButtons() {
                    const audioLinks = document.querySelectorAll('a[data-preview="audio"]');
                    console.log('[Music Player] Found ' + audioLinks.length + ' audio files');
                    
                    audioLinks.forEach(link => {
                        const fileIcon = link.querySelector('.file-icon');
                        if (!fileIcon) return;
                        
                        // Check if button already exists
                        if (fileIcon.querySelector('.audio-play-btn')) return;
                        
                        // Add audio-file class to the list item for CSS styling
                        const listItem = link.closest('li');
                        if (listItem) {
                            listItem.classList.add('audio-file');
                        }
                        
                        // Create play button
                        const playBtn = document.createElement('button');
                        playBtn.className = 'audio-play-btn';
                        playBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
                        playBtn.setAttribute('aria-label', 'Play audio');
                        playBtn.setAttribute('title', 'Play');
                        
                        // Add click handler
                        playBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            toggleAudio(link, playBtn);
                        });
                        
                        fileIcon.appendChild(playBtn);
                        
                        // Create time counter element (hidden by default)
                        const timeCounter = document.createElement('span');
                        timeCounter.className = 'audio-time-counter';
                        timeCounter.style.display = 'none';
                        timeCounter.textContent = '0:00 / 0:00';
                        
                        // Insert time counter after file size (or file name if no size)
                        const fileSize = link.querySelector('.file-size');
                        if (fileSize) {
                            fileSize.parentNode.insertBefore(timeCounter, fileSize.nextSibling);
                        } else {
                            link.appendChild(timeCounter);
                        }
                    });
                }
                
                // Toggle audio playback
                function toggleAudio(link, button) {
                    const filePath = link.dataset.filePath;
                    const listItem = link.closest('li');
                    
                    console.log('[Music Player] Toggle audio:', filePath);
                    
                    // If clicking the same audio that's playing, pause it
                    if (currentAudio && currentListItem === listItem) {
                        if (currentAudio.paused) {
                            playAudio(filePath, listItem, link, button);
                        } else {
                            pauseAudio();
                        }
                    } else {
                        // Stop current audio if playing
                        if (currentAudio) {
                            stopAudio();
                        }
                        // Start new audio
                        playAudio(filePath, listItem, link, button);
                    }
                }
                
                // Play audio
                function playAudio(filePath, listItem, link, button) {
                    console.log('[Music Player] Playing:', filePath);
                    
                    // Create audio element if needed
                    if (!currentAudio || currentListItem !== listItem) {
                        if (currentAudio) {
                            currentAudio.pause();
                            currentAudio = null;
                        }
                        
                        const previewUrl = '?preview=' + encodeURIComponent(filePath);
                        currentAudio = new Audio(previewUrl);
                        
                        // Set up event listeners
                        currentAudio.addEventListener('ended', function() {
                            console.log('[Music Player] Audio ended');
                            stopAudio();
                        });
                        
                        currentAudio.addEventListener('error', function() {
                            console.error('[Music Player] Audio load error');
                            stopAudio();
                            showToast('Failed to load audio file', 'error');
                        });
                        
                        // Wait for metadata to load before showing duration
                        currentAudio.addEventListener('loadedmetadata', function() {
                            console.log('[Music Player] Metadata loaded, duration:', currentAudio.duration);
                            updateProgress();
                        });
                        
                        // Handle duration change (fires when duration changes from Infinity to actual value)
                        currentAudio.addEventListener('durationchange', function() {
                            console.log('[Music Player] Duration changed, duration:', currentAudio.duration);
                            updateProgress();
                        });
                        
                        // Start progress tracking
                        currentAudio.addEventListener('timeupdate', updateProgress);
                    }
                    
                    // Play audio
                    currentAudio.play().catch(err => {
                        console.error('[Music Player] Playback error:', err);
                        showToast('Failed to play audio file', 'error');
                        stopAudio();
                    });
                    
                    // Update UI
                    currentListItem = listItem;
                    currentLink = link;
                    currentButton = button;
                    button.classList.add('playing');
                    button.innerHTML = '<i class="fa-solid fa-pause"></i>';
                    button.setAttribute('aria-label', 'Pause audio');
                    button.setAttribute('title', 'Pause');
                    listItem.classList.add('audio-playing');
                    
                    // Show time counter
                    const timeCounter = link.querySelector('.audio-time-counter');
                    if (timeCounter) {
                        timeCounter.style.display = 'inline-block';
                    }
                }
                
                // Pause audio
                function pauseAudio() {
                    if (!currentAudio) return;
                    
                    console.log('[Music Player] Pausing audio');
                    currentAudio.pause();
                    
                    // Update UI
                    if (currentButton) {
                        currentButton.classList.remove('playing');
                        currentButton.innerHTML = '<i class="fa-solid fa-play"></i>';
                        currentButton.setAttribute('aria-label', 'Play audio');
                        currentButton.setAttribute('title', 'Play');
                    }
                }
                
                // Stop audio and clean up
                function stopAudio() {
                    if (!currentAudio) return;
                    
                    console.log('[Music Player] Stopping audio');
                    currentAudio.pause();
                    currentAudio.currentTime = 0;
                    currentAudio = null;
                    
                    // Clean up UI
                    if (currentButton) {
                        currentButton.classList.remove('playing');
                        currentButton.innerHTML = '<i class="fa-solid fa-play"></i>';
                        currentButton.setAttribute('aria-label', 'Play audio');
                        currentButton.setAttribute('title', 'Play');
                        currentButton = null;
                    }
                    
                    if (currentListItem) {
                        currentListItem.classList.remove('audio-playing');
                        currentListItem = null;
                    }
                    
                    if (currentLink) {
                        // Reset progress bar
                        currentLink.style.removeProperty('--audio-progress');
                        
                        // Hide time counter
                        const timeCounter = currentLink.querySelector('.audio-time-counter');
                        if (timeCounter) {
                            timeCounter.style.display = 'none';
                            timeCounter.textContent = '0:00 / 0:00';
                        }
                        
                        currentLink = null;
                    }
                }
                
                // Update progress bar and time counter
                function updateProgress() {
                    if (!currentAudio || !currentLink) return;
                    if (isNaN(currentAudio.currentTime)) return;
                    
                    // Check if duration is valid
                    const hasDuration = currentAudio.duration && currentAudio.duration > 0 && isFinite(currentAudio.duration);
                    
                    // Update progress bar only if we have a valid duration
                    if (hasDuration) {
                        const progress = Math.min(100, Math.max(0, (currentAudio.currentTime / currentAudio.duration) * 100));
                        currentLink.style.setProperty('--audio-progress', `${progress}%`);
                    }
                    
                    // Always update time counter (show current time even if duration is unknown)
                    const timeCounter = currentLink.querySelector('.audio-time-counter');
                    if (timeCounter) {
                        const currentTime = formatTime(currentAudio.currentTime);
                        const duration = hasDuration ? formatTime(currentAudio.duration) : '?:??';
                        timeCounter.textContent = `${currentTime} / ${duration}`;
                    }
                }
                
                // Format time in MM:SS or H:MM:SS format
                function formatTime(seconds) {
                    if (!isFinite(seconds) || seconds < 0) return '0:00';
                    
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = Math.floor(seconds % 60);
                    
                    // Helper to pad numbers with leading zero
                    const pad = (num) => String(num).padStart(2, '0');
                    
                    if (hours > 0) {
                        return `${hours}:${pad(minutes)}:${pad(secs)}`;
                    } else {
                        return `${minutes}:${pad(secs)}`;
                    }
                }
                
                // Initialize on page load
                initAudioButtons();
                
                console.log('[Music Player] Music player functionality initialized');
            })();
            
            // Video player functionality
            (function() {
                console.log('[Video Player] Initializing video player functionality');
                
                const modal = document.getElementById('videoPlayerModal');
                const wrapper = document.getElementById('videoPlayerWrapper');
                const title = document.getElementById('videoPlayerTitle');
                const closeBtn = document.getElementById('videoPlayerClose');
                
                if (!modal || !wrapper || !title || !closeBtn) {
                    console.warn('[Video Player] Missing required elements');
                    return;
                }
                
                let currentVideo = null;
                
                // Find all video file items and add play buttons
                function initVideoButtons() {
                    const videoLinks = document.querySelectorAll('a[data-preview="video"]');
                    console.log('[Video Player] Found ' + videoLinks.length + ' video files');
                    
                    videoLinks.forEach(link => {
                        const fileIcon = link.querySelector('.file-icon');
                        if (!fileIcon) return;
                        
                        // Check if button already exists
                        if (fileIcon.querySelector('.video-play-btn')) return;
                        
                        // Add video-file class to the list item for CSS styling
                        const listItem = link.closest('li');
                        if (listItem) {
                            listItem.classList.add('video-file');
                        }
                        
                        // Create play button
                        const playBtn = document.createElement('button');
                        playBtn.className = 'video-play-btn';
                        playBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
                        playBtn.setAttribute('aria-label', 'Play video');
                        playBtn.setAttribute('title', 'Play video');
                        
                        // Add click handler
                        playBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const fileName = link.querySelector('.file-name')?.textContent || 'Video';
                            const filePath = link.dataset.filePath;
                            openVideoPlayer(filePath, fileName);
                        });
                        
                        fileIcon.appendChild(playBtn);
                    });
                }
                
                // Open video player modal
                function openVideoPlayer(filePath, fileName) {
                    console.log('[Video Player] Opening video player for:', filePath);
                    
                    // Set title
                    title.textContent = fileName;
                    
                    // Clear wrapper first
                    wrapper.innerHTML = '';
                    
                    // Create video element
                    const video = document.createElement('video');
                    video.controls = true;
                    video.autoplay = true;
                    video.preload = 'auto';
                    video.style.width = '100%';
                    video.style.height = 'auto';
                    video.setAttribute('aria-label', 'Video player for ' + fileName);
                    
                    // Handle errors
                    video.addEventListener('error', function(e) {
                        console.error('[Video Player] Video failed to load:', filePath);
                        
                        // Get file extension to provide better error message
                        const fileExt = fileName.toLowerCase().split('.').pop();
                        let errorMessage = '❌ Unable to load video. ';
                        
                        // Provide specific guidance for known problematic formats
                        if (fileExt === 'mpg' || fileExt === 'mpeg') {
                            errorMessage += 'MPEG files (.mpg/.mpeg) have limited browser support. ' +
                                          'For best compatibility, consider converting to MP4, WebM, or OGV format. ' +
                                          'You can try downloading the file to play it with a dedicated video player like VLC.';
                        } else {
                            errorMessage += 'The file may be corrupted or use a codec unsupported by your browser. ' +
                                          'Try downloading the file or use a different browser.';
                        }
                        
                        wrapper.innerHTML = '<div class="video-player-error">' + errorMessage + '</div>';
                    });
                    
                    // Build preview URL and set source
                    const previewUrl = '?preview=' + encodeURIComponent(filePath);
                    video.src = previewUrl;
                    
                    // Add video to wrapper
                    wrapper.appendChild(video);
                    currentVideo = video;
                    
                    // Show modal
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                    
                    // Focus on close button for accessibility
                    setTimeout(() => closeBtn.focus(), 100);
                }
                
                // Close video player modal
                function closeVideoPlayer() {
                    console.log('[Video Player] Closing video player');
                    
                    // Stop and remove video
                    if (currentVideo) {
                        currentVideo.pause();
                        currentVideo.src = '';
                        currentVideo = null;
                    }
                    
                    // Clear wrapper
                    wrapper.innerHTML = '';
                    
                    // Hide modal
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                }
                
                // Close button click handler
                closeBtn.addEventListener('click', closeVideoPlayer);
                
                // Close on background click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeVideoPlayer();
                    }
                });
                
                // Keyboard support
                modal.addEventListener('keydown', function(e) {
                    // Escape key closes modal
                    if (e.key === 'Escape') {
                        closeVideoPlayer();
                    }
                    // Space key toggles play/pause (only if not in an input field)
                    if (e.key === ' ' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                        if (currentVideo) {
                            e.preventDefault();
                            if (currentVideo.paused) {
                                currentVideo.play();
                            } else {
                                currentVideo.pause();
                            }
                        }
                    }
                });
                
                // Initialize on page load
                initVideoButtons();
                
                console.log('[Video Player] Video player functionality initialized');
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
                const selectedCountEl = document.getElementById('selectedCount');
                const batchDownloadBtn = document.getElementById('batchDownloadBtn');
                const batchDeleteBtn = document.getElementById('batchDeleteBtn');
                
                if (!selectAllCheckbox) return;
                
                // Get current path for storage key
                const urlParams = new URLSearchParams(window.location.search);
                const currentPath = urlParams.get('path') || '';
                const storageKey = 'selectedItems_' + currentPath;
                
                let selectedItems = new Set();
                
                // Load all items data (for select all across pagination)
                let allItemsData = [];
                let allItemsMap = new Map(); // For O(1) lookup by path
                const allItemsDataEl = document.getElementById('allItemsData');
                if (allItemsDataEl) {
                    try {
                        allItemsData = JSON.parse(allItemsDataEl.textContent);
                        // Build map for efficient lookup
                        allItemsData.forEach(item => {
                            allItemsMap.set(item.path, item);
                        });
                    } catch (e) {
                        console.error('Failed to parse all items data:', e);
                    }
                }
                
                // Load selected items from sessionStorage
                try {
                    const storedSelection = sessionStorage.getItem(storageKey);
                    if (storedSelection) {
                        const storedPaths = JSON.parse(storedSelection);
                        // Validate that parsed data is an array
                        if (Array.isArray(storedPaths)) {
                            // Only restore selections that still exist in current directory
                            storedPaths.forEach(path => {
                                if (allItemsMap.has(path)) {
                                    selectedItems.add(path);
                                }
                            });
                        }
                    }
                } catch (e) {
                    console.error('Failed to load selected items from storage:', e);
                }
                
                // Save selected items to sessionStorage
                function saveSelection() {
                    try {
                        sessionStorage.setItem(storageKey, JSON.stringify(Array.from(selectedItems)));
                    } catch (e) {
                        console.error('Failed to save selected items to storage:', e);
                    }
                }
                
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
                    
                    // Calculate total size from selectedItems using map for O(1) lookup
                    let totalSize = 0;
                    selectedItems.forEach(path => {
                        const item = allItemsMap.get(path);
                        if (item) {
                            totalSize += item.size || 0;
                        }
                    });
                    
                    // Update visible checkboxes to match selectedItems
                    const allCheckboxes = document.querySelectorAll('.item-checkbox');
                    allCheckboxes.forEach(checkbox => {
                        const path = checkbox.dataset.itemPath;
                        const shouldBeChecked = selectedItems.has(path);
                        if (checkbox.checked !== shouldBeChecked) {
                            checkbox.checked = shouldBeChecked;
                            const listItem = checkbox.closest('li');
                            if (listItem) {
                                if (shouldBeChecked) {
                                    listItem.classList.add('selected');
                                } else {
                                    listItem.classList.remove('selected');
                                }
                            }
                        }
                    });
                    
                    if (count > 0) {
                        // Update text with count and total size
                        let displayText = count + ' selected';
                        if (totalSize > 0) {
                            displayText += ' (' + formatFileSize(totalSize) + ')';
                        }
                        selectedCountEl.textContent = displayText;
                        
                        // Show all elements in batch button group by removing hidden class
                        if (selectedCountEl) selectedCountEl.classList.remove('batch-btn-hidden');
                        if (batchDownloadBtn) batchDownloadBtn.classList.remove('batch-btn-hidden');
                        if (batchDeleteBtn) batchDeleteBtn.classList.remove('batch-btn-hidden');
                    } else {
                        // Hide all elements in batch button group by adding hidden class
                        if (selectedCountEl) selectedCountEl.classList.add('batch-btn-hidden');
                        if (batchDownloadBtn) batchDownloadBtn.classList.add('batch-btn-hidden');
                        if (batchDeleteBtn) batchDeleteBtn.classList.add('batch-btn-hidden');
                    }
                    
                    // Update select all checkbox state based on all items (not just visible)
                    const totalItems = allItemsData.length;
                    selectAllCheckbox.checked = count > 0 && count === totalItems;
                    selectAllCheckbox.indeterminate = count > 0 && count < totalItems;
                }
                
                function getSelectedPaths() {
                    return Array.from(selectedItems);
                }
                
                // Select all/deselect all - now works across all pages
                selectAllCheckbox.addEventListener('change', function() {
                    const shouldCheck = this.checked;
                    
                    selectedItems.clear();
                    
                    if (shouldCheck) {
                        // Add all items from allItemsData
                        allItemsData.forEach(item => {
                            selectedItems.add(item.path);
                        });
                    }
                    
                    saveSelection();
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
                        
                        saveSelection();
                        updateUI();
                    }
                });
                
                // Batch download
                if (batchDownloadBtn) {
                    batchDownloadBtn.addEventListener('click', function() {
                        const paths = getSelectedPaths();
                        
                        if (paths.length === 0) {
                            showToast('Please select at least one item', 'warning');
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
                            showToast('Please select at least one item', 'warning');
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
                                    showToast(data.message + '\n\nFailed items:\n' + data.failed.join('\n'), 'warning', 7000);
                                }
                                
                                // Reload page to show updated list
                                window.location.reload();
                            } else {
                                let errorMsg = data.error || 'Failed to delete items';
                                if (data.failed && data.failed.length > 0) {
                                    errorMsg += '\n\nFailed items:\n' + data.failed.join('\n');
                                }
                                showToast(errorMsg, 'error', 7000);
                                batchDeleteBtn.disabled = false;
                                batchDeleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Selected';
                            }
                        })
                        .catch(err => {
                            showToast(err.message || 'An error occurred. Please try again.', 'error');
                            batchDeleteBtn.disabled = false;
                            batchDeleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Selected';
                            console.error('Batch delete error:', err);
                        });
                    });
                }
                
                // Initialize UI on page load
                updateUI();
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
            
            // Upload functionality
            (function() {
                const uploadBtn = document.getElementById('uploadBtn');
                const uploadModal = document.getElementById('uploadModal');
                const uploadModalCancel = document.getElementById('uploadModalCancel');
                const uploadModalConfirm = document.getElementById('uploadModalConfirm');
                const uploadDropZone = document.getElementById('uploadDropZone');
                const uploadFileInput = document.getElementById('uploadFileInput');
                const uploadSelectBtn = document.getElementById('uploadSelectBtn');
                const uploadFileList = document.getElementById('uploadFileList');
                const uploadModalError = document.getElementById('uploadModalError');
                const uploadModalSuccess = document.getElementById('uploadModalSuccess');
                const dragDropOverlay = document.getElementById('dragDropOverlay');
                const uploadProgressContainer = document.getElementById('uploadProgressContainer');
                const uploadProgressFill = document.getElementById('uploadProgressFill');
                const uploadProgressText = document.getElementById('uploadProgressText');
                
                if (!uploadBtn || !uploadModal) return;
                
                let selectedFiles = [];
                let dragCounter = 0;
                
                // Get current path for upload
                function getCurrentPath() {
                    const urlParams = new URLSearchParams(window.location.search);
                    return urlParams.get('path') || '';
                }
                
                // Format file size
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 B';
                    // Ensure bytes is non-negative
                    bytes = Math.max(bytes, 0);
                    const units = ['B', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(1024));
                    const actualI = Math.min(i, units.length - 1);
                    const size = bytes / Math.pow(1024, actualI);
                    return actualI === 0 ? Math.floor(size) + ' B' : size.toFixed(2) + ' ' + units[actualI];
                }
                
                // Show/hide messages
                function showError(message) {
                    uploadModalError.textContent = message;
                    uploadModalError.classList.add('active');
                    uploadModalSuccess.classList.remove('active');
                }
                
                function showSuccess(message) {
                    uploadModalSuccess.textContent = message;
                    uploadModalSuccess.classList.add('active');
                    uploadModalError.classList.remove('active');
                }
                
                function hideMessages() {
                    uploadModalError.classList.remove('active');
                    uploadModalSuccess.classList.remove('active');
                }
                
                // Render file list
                function renderFileList() {
                    if (selectedFiles.length === 0) {
                        uploadFileList.innerHTML = '';
                        uploadModalConfirm.disabled = true;
                        return;
                    }
                    
                    uploadModalConfirm.disabled = false;
                    
                    const html = selectedFiles.map((file, index) => {
                        const status = file.uploadStatus || 'pending';
                        let statusIcon = '';
                        
                        if (status === 'pending') {
                            statusIcon = '<div class="upload-file-status pending"><i class="fa-solid fa-clock"></i></div>';
                        } else if (status === 'uploading') {
                            statusIcon = '<div class="upload-file-status uploading"><i class="fa-solid fa-spinner"></i></div>';
                        } else if (status === 'completed') {
                            statusIcon = '<div class="upload-file-status completed"><i class="fa-solid fa-check-circle"></i></div>';
                        } else if (status === 'failed') {
                            statusIcon = '<div class="upload-file-status failed"><i class="fa-solid fa-times-circle"></i></div>';
                        }
                        
                        const removeButton = status === 'pending' ? 
                            '<button class="upload-file-remove" data-index="' + index + '" title="Remove"><i class="fa-solid fa-times"></i></button>' :
                            '';
                        
                        return '<div class="upload-file-item" data-file-index="' + index + '">' +
                            '<div class="upload-file-info">' +
                            '<i class="fa-solid fa-file"></i>' +
                            '<div class="upload-file-details">' +
                            '<div class="upload-file-name">' + escapeHtml(file.name) + '</div>' +
                            '<div class="upload-file-size">' + formatFileSize(file.size) + '</div>' +
                            '</div>' +
                            '</div>' +
                            statusIcon +
                            removeButton +
                            '</div>';
                    }).join('');
                    
                    uploadFileList.innerHTML = html;
                }
                
                // HTML escape helper
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                // Add files to selection with folder detection
                function addFiles(files) {
                    let folderDetected = false;
                    let filesAdded = 0;
                    
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        
                        // Check if this is a folder (folders have size 0 and type "" and webkitRelativePath)
                        // Or check if it's a folder by checking if size is 0 and type is empty
                        // However, the browser's file input with multiple doesn't actually allow folder selection
                        // We need to check if the file has certain properties that indicate it might be part of a folder
                        
                        // For drag and drop, we can detect folders by checking DataTransferItem
                        // But for FileList, we check if file.type is empty and size is 0 (might be a folder)
                        // Actually, standard file input doesn't allow folders, but we should check the webkitRelativePath
                        if (file.webkitRelativePath && file.webkitRelativePath.includes('/')) {
                            folderDetected = true;
                            continue; // Skip this file
                        }
                        
                        // Check if file already exists
                        const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
                        if (!exists) {
                            selectedFiles.push(file);
                            filesAdded++;
                        }
                    }
                    
                    // Show warning if folders were detected
                    if (folderDetected) {
                        showError('Folders cannot be uploaded. Please select individual files only.');
                    }
                    
                    renderFileList();
                }
                
                // Remove file from selection
                function removeFile(index) {
                    selectedFiles.splice(index, 1);
                    renderFileList();
                }
                
                // Open modal
                function openModal() {
                    selectedFiles = [];
                    renderFileList();
                    hideMessages();
                    uploadProgressContainer.classList.remove('active');
                    uploadModal.classList.add('active');
                    uploadModal.removeAttribute('aria-hidden');
                }
                
                // Close modal
                function closeModal() {
                    uploadModal.classList.remove('active');
                    uploadModal.setAttribute('aria-hidden', 'true');
                    selectedFiles = [];
                    uploadFileInput.value = '';
                    uploadProgressContainer.classList.remove('active');
                }
                
                // Perform immediate upload (for drag and drop)
                function performImmediateUpload(files) {
                    if (!files || files.length === 0) return;
                    
                    // Show loading overlay
                    const loadingOverlay = document.getElementById('loadingOverlay');
                    const loadingText = document.getElementById('loadingText');
                    if (loadingOverlay) {
                        loadingOverlay.classList.add('active');
                        if (loadingText) {
                            loadingText.textContent = 'Uploading ' + files.length + ' file' + (files.length > 1 ? 's' : '') + '...';
                        }
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('upload', '1');
                        formData.append('target_path', getCurrentPath());
                        
                        files.forEach((file, index) => {
                            console.log('Adding file to FormData:', index, file.name, file.size);
                            formData.append('files[]', file);
                        });
                        
                        console.log('FormData created successfully, uploading', files.length, 'files');
                    
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(data => {
                                    // Create more descriptive error message
                                    let errorMsg = data.error || 'Upload failed';
                                    if (response.status === 403) {
                                        errorMsg = 'Upload forbidden: ' + errorMsg;
                                    } else if (response.status === 413) {
                                        errorMsg = 'File too large: The uploaded file(s) exceed the maximum allowed size';
                                    } else if (response.status === 500) {
                                        errorMsg = 'Server error: ' + errorMsg;
                                    } else if (response.status === 400) {
                                        errorMsg = 'Invalid request: ' + errorMsg;
                                    }
                                    throw new Error(errorMsg);
                                }).catch(err => {
                                    if (err.message) {
                                        throw err;
                                    }
                                    // If JSON parsing failed, provide generic error with status
                                    throw new Error('Upload failed with status ' + response.status + ': ' + response.statusText);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (loadingOverlay) {
                                loadingOverlay.classList.remove('active');
                            }
                            
                            if (data.success) {
                                let message = data.uploaded + ' file' + (data.uploaded > 1 ? 's' : '') + ' uploaded successfully';
                                if (data.failed && data.failed.length > 0) {
                                    message += '\n\nFailed:\n' + data.failed.join('\n');
                                    showToast(message, 'warning', 7000);
                                }
                                
                                // Reload page to show uploaded files
                                window.location.reload();
                            } else {
                                let errorMsg = data.error || 'Upload failed';
                                if (data.failed && data.failed.length > 0) {
                                    errorMsg += '\n\nFailed:\n' + data.failed.join('\n');
                                }
                                showToast(errorMsg, 'error', 7000);
                            }
                        })
                        .catch(err => {
                            if (loadingOverlay) {
                                loadingOverlay.classList.remove('active');
                            }
                            
                            // Provide more descriptive error message
                            let errorMsg = err.message || 'Upload failed due to a network error';
                            if (!navigator.onLine) {
                                errorMsg = 'Upload failed: No internet connection detected';
                            } else if (err.message && err.message.includes('NetworkError')) {
                                errorMsg = 'Upload failed: Network error occurred. Please check your connection';
                            }
                            
                            showToast(errorMsg, 'error');
                            console.error('Upload error:', err);
                        });
                    } catch (err) {
                        // Handle FormData creation errors
                        console.error('Error creating FormData:', err);
                        if (loadingOverlay) {
                            loadingOverlay.classList.remove('active');
                        }
                        showToast('Failed to prepare upload: ' + err.message, 'error');
                    }
                }
                
                // Perform upload
                function performUpload() {
                    if (selectedFiles.length === 0) return;
                    
                    hideMessages();
                    uploadModalConfirm.disabled = true;
                    uploadModalCancel.disabled = true;
                    uploadModalConfirm.textContent = 'Uploading...';
                    
                    // Show progress container
                    uploadProgressContainer.classList.add('active');
                    uploadProgressFill.style.width = '0%';
                    uploadProgressText.textContent = 'Uploading files...';
                    
                    // Mark all files as uploading
                    selectedFiles.forEach(file => {
                        file.uploadStatus = 'uploading';
                    });
                    renderFileList();
                    
                    try {
                        const formData = new FormData();
                        formData.append('upload', '1');
                        formData.append('target_path', getCurrentPath());
                        
                        selectedFiles.forEach((file, index) => {
                            console.log('Adding file to FormData:', index, file.name, file.size);
                            formData.append('files[]', file);
                        });
                        
                        console.log('FormData created successfully, uploading', selectedFiles.length, 'files');
                    
                    // Simulate progress for better UX (since we can't track individual file uploads on server)
                    let progress = 0;
                    const progressInterval = setInterval(() => {
                        if (progress < 90) {
                            progress += 10;
                            uploadProgressFill.style.width = progress + '%';
                            uploadProgressText.textContent = 'Uploading files... ' + progress + '%';
                        }
                    }, 200);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        clearInterval(progressInterval);
                        if (!response.ok) {
                            return response.json().then(data => {
                                // Create more descriptive error message
                                let errorMsg = data.error || 'Upload failed';
                                if (response.status === 403) {
                                    errorMsg = 'Upload forbidden: ' + errorMsg;
                                } else if (response.status === 413) {
                                    errorMsg = 'File too large: The uploaded file(s) exceed the maximum allowed size';
                                } else if (response.status === 500) {
                                    errorMsg = 'Server error: ' + errorMsg;
                                } else if (response.status === 400) {
                                    errorMsg = 'Invalid request: ' + errorMsg;
                                }
                                throw new Error(errorMsg);
                            }).catch(err => {
                                if (err.message) {
                                    throw err;
                                }
                                // If JSON parsing failed, provide generic error with status
                                throw new Error('Upload failed with status ' + response.status + ': ' + response.statusText);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        clearInterval(progressInterval);
                        uploadProgressFill.style.width = '100%';
                        uploadProgressText.textContent = 'Upload complete!';
                        
                        if (data.success) {
                            // Mark uploaded files as completed
                            if (data.files && Array.isArray(data.files)) {
                                selectedFiles.forEach(file => {
                                    if (data.files.includes(file.name)) {
                                        file.uploadStatus = 'completed';
                                    }
                                });
                            }
                            
                            // Mark failed files
                            if (data.failed && Array.isArray(data.failed)) {
                                data.failed.forEach(failedMsg => {
                                    // Extract filename from error message (format: "filename (error)")
                                    const match = failedMsg.match(/^(.+?)\s*\(/);
                                    if (match) {
                                        const failedName = match[1];
                                        const file = selectedFiles.find(f => f.name === failedName);
                                        if (file) {
                                            file.uploadStatus = 'failed';
                                        }
                                    }
                                });
                            }
                            
                            renderFileList();
                            
                            let message = data.uploaded + ' file' + (data.uploaded > 1 ? 's' : '') + ' uploaded successfully';
                            if (data.failed && data.failed.length > 0) {
                                message += '\n\nFailed:\n' + data.failed.join('\n');
                            }
                            showSuccess(message);
                            
                            // Reset and reload after 2 seconds
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            // Mark all as failed
                            selectedFiles.forEach(file => {
                                file.uploadStatus = 'failed';
                            });
                            renderFileList();
                            
                            let errorMsg = data.error || 'Upload failed';
                            if (data.failed && data.failed.length > 0) {
                                errorMsg += '\n\nFailed:\n' + data.failed.join('\n');
                            }
                            showError(errorMsg);
                            uploadModalConfirm.disabled = false;
                            uploadModalCancel.disabled = false;
                            uploadModalConfirm.textContent = 'Upload';
                            uploadProgressContainer.classList.remove('active');
                        }
                    })
                    .catch(err => {
                        clearInterval(progressInterval);
                        uploadProgressContainer.classList.remove('active');
                        
                        // Mark all as failed
                        selectedFiles.forEach(file => {
                            file.uploadStatus = 'failed';
                        });
                        renderFileList();
                        
                        // Provide more descriptive error message
                        let errorMsg = err.message || 'Upload failed due to a network error';
                        if (!navigator.onLine) {
                            errorMsg = 'Upload failed: No internet connection detected';
                        } else if (err.message && err.message.includes('NetworkError')) {
                            errorMsg = 'Upload failed: Network error occurred. Please check your connection';
                        }
                        
                        showError(errorMsg);
                        uploadModalConfirm.disabled = false;
                        uploadModalCancel.disabled = false;
                        uploadModalConfirm.textContent = 'Upload';
                        console.error('Upload error:', err);
                    });
                    } catch (err) {
                        // Handle FormData creation errors
                        console.error('Error creating FormData:', err);
                        showError('Failed to prepare upload: ' + err.message);
                        uploadModalConfirm.disabled = false;
                        uploadModalCancel.disabled = false;
                        uploadModalConfirm.textContent = 'Upload';
                        uploadProgressContainer.classList.remove('active');
                        selectedFiles.forEach(file => {
                            file.uploadStatus = 'failed';
                        });
                        renderFileList();
                    }
                }
                
                // Event listeners
                uploadBtn.addEventListener('click', openModal);
                uploadModalCancel.addEventListener('click', closeModal);
                uploadModalConfirm.addEventListener('click', performUpload);
                
                uploadSelectBtn.addEventListener('click', function() {
                    uploadFileInput.click();
                });
                
                uploadFileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        addFiles(this.files);
                    }
                });
                
                // Drop zone events
                uploadDropZone.addEventListener('click', function(e) {
                    // Trigger file input when clicking on the drop zone
                    if (e.target === uploadDropZone || e.target.tagName === 'I' || e.target.tagName === 'P') {
                        uploadFileInput.click();
                    }
                });
                
                uploadDropZone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('drag-over');
                });
                
                uploadDropZone.addEventListener('dragleave', function() {
                    this.classList.remove('drag-over');
                });
                
                uploadDropZone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                    
                    // Check for folders using DataTransferItem API
                    if (e.dataTransfer.items) {
                        let hasFolders = false;
                        const validFiles = [];
                        
                        for (let i = 0; i < e.dataTransfer.items.length; i++) {
                            const item = e.dataTransfer.items[i];
                            
                            // Check if item is a directory
                            if (item.kind === 'file') {
                                const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
                                if (entry && entry.isDirectory) {
                                    hasFolders = true;
                                } else {
                                    // It's a file, add it to valid files
                                    const file = item.getAsFile();
                                    if (file) {
                                        validFiles.push(file);
                                    }
                                }
                            }
                        }
                        
                        // Show error if folders were detected
                        if (hasFolders) {
                            showError('Folders cannot be uploaded. Please drop individual files only.');
                        }
                        
                        // Add valid files
                        if (validFiles.length > 0) {
                            // Convert array to FileList-like object
                            const fileList = validFiles;
                            addFiles(fileList);
                        }
                    } else if (e.dataTransfer.files.length > 0) {
                        // Fallback for browsers that don't support DataTransferItem API
                        addFiles(e.dataTransfer.files);
                    }
                });
                
                // Remove file button
                uploadFileList.addEventListener('click', function(e) {
                    const removeBtn = e.target.closest('.upload-file-remove');
                    if (removeBtn) {
                        const index = parseInt(removeBtn.dataset.index);
                        if (!isNaN(index)) {
                            removeFile(index);
                        }
                    }
                });
                
                // Close modal on outside click
                uploadModal.addEventListener('click', function(e) {
                    if (e.target === uploadModal) {
                        closeModal();
                    }
                });
                
                // Keyboard support
                uploadModal.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeModal();
                    }
                });
                
                // Global drag and drop overlay
                document.addEventListener('dragenter', function(e) {
                    // Ignore if modal is already open
                    if (uploadModal.classList.contains('active')) return;
                    
                    // Check if it's a file drag (not text or other data)
                    if (e.dataTransfer.types && e.dataTransfer.types.indexOf('Files') !== -1) {
                        dragCounter++;
                        if (dragCounter === 1) {
                            dragDropOverlay.classList.add('active');
                        }
                    }
                });
                
                document.addEventListener('dragleave', function() {
                    dragCounter--;
                    if (dragCounter === 0) {
                        dragDropOverlay.classList.remove('active');
                    }
                });
                
                document.addEventListener('dragover', function(e) {
                    // Prevent default to allow drop
                    if (e.dataTransfer.types && e.dataTransfer.types.indexOf('Files') !== -1) {
                        e.preventDefault();
                    }
                });
                
                document.addEventListener('drop', function(e) {
                    e.preventDefault();
                    dragCounter = 0;
                    dragDropOverlay.classList.remove('active');
                    
                    // Don't handle if modal is already open or if not in file lister
                    if (uploadModal.classList.contains('active')) return;
                    
                    // Check for folders using DataTransferItem API
                    if (e.dataTransfer.items) {
                        let hasFolders = false;
                        const validFiles = [];
                        
                        for (let i = 0; i < e.dataTransfer.items.length; i++) {
                            const item = e.dataTransfer.items[i];
                            
                            // Check if item is a directory
                            if (item.kind === 'file') {
                                const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
                                if (entry && entry.isDirectory) {
                                    hasFolders = true;
                                } else {
                                    // It's a file, add it to valid files
                                    const file = item.getAsFile();
                                    if (file) {
                                        validFiles.push(file);
                                    }
                                }
                            }
                        }
                        
                        // Show error if folders were detected
                        if (hasFolders) {
                            showToast('Folders cannot be uploaded. Please drop individual files only.', 'warning');
                        }
                        
                        // Upload valid files immediately
                        if (validFiles.length > 0) {
                            performImmediateUpload(validFiles);
                        }
                    } else if (e.dataTransfer.files.length > 0) {
                        // Fallback for browsers that don't support DataTransferItem API
                        // Convert FileList to array
                        const filesArray = Array.from(e.dataTransfer.files);
                        performImmediateUpload(filesArray);
                    }
                });
            })();
            
            // Create directory functionality
            (function() {
                const createDirBtn = document.getElementById('createDirBtn');
                const modal = document.getElementById('createDirModal');
                const input = document.getElementById('createDirModalInput');
                const error = document.getElementById('createDirModalError');
                const cancelBtn = document.getElementById('createDirModalCancel');
                const confirmBtn = document.getElementById('createDirModalConfirm');
                
                if (!createDirBtn || !modal) return;
                
                function showError(message) {
                    error.textContent = message;
                    error.classList.add('active');
                }
                
                function hideError() {
                    error.classList.remove('active');
                }
                
                function openModal() {
                    input.value = '';
                    hideError();
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                    
                    // Focus input
                    setTimeout(() => {
                        input.focus();
                    }, 50);
                }
                
                function closeModal() {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                }
                
                function getCurrentPath() {
                    const urlParams = new URLSearchParams(window.location.search);
                    return urlParams.get('path') || '';
                }
                
                function performCreate() {
                    const dirName = input.value.trim();
                    
                    if (!dirName) {
                        showError('Please enter a folder name');
                        return;
                    }
                    
                    // Disable buttons during operation
                    confirmBtn.disabled = true;
                    cancelBtn.disabled = true;
                    hideError();
                    
                    // Send create request
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'create_directory=1&directory_name=' + encodeURIComponent(dirName) + '&target_path=' + encodeURIComponent(getCurrentPath())
                    })
                    .then(response => {
                        // Store response status for error handling
                        const status = response.status;
                        // Check if response is ok before parsing JSON
                        if (!response.ok) {
                            // Try to parse JSON error, but handle cases where response is not JSON
                            return response.text().then(text => {
                                try {
                                    const data = JSON.parse(text);
                                    throw new Error(data.error || 'Failed to create folder (HTTP ' + status + ')');
                                } catch (e) {
                                    // If JSON parsing fails, throw a generic error with status code
                                    if (e instanceof SyntaxError) {
                                        throw new Error('Failed to create folder (HTTP ' + status + ')');
                                    }
                                    // Re-throw if it's already an Error from JSON parsing
                                    throw e;
                                }
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Reload page to show new folder
                            window.location.reload();
                        } else {
                            showError(data.error || 'Failed to create folder');
                            confirmBtn.disabled = false;
                            cancelBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        showError(err.message || 'An error occurred. Please try again.');
                        confirmBtn.disabled = false;
                        cancelBtn.disabled = false;
                        console.error('Create directory error:', err);
                    });
                }
                
                // Event listeners
                createDirBtn.addEventListener('click', openModal);
                cancelBtn.addEventListener('click', closeModal);
                confirmBtn.addEventListener('click', performCreate);
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        performCreate();
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
            
            // Theme functionality
            (function() {
                const themeSettingsBtn = document.getElementById('themeSettingsBtn');
                const themeModal = document.getElementById('themeModal');
                const themeModalCloseBtn = document.getElementById('themeModalCloseBtn');
                const themeOptions = document.querySelectorAll('.theme-option');
                
                if (!themeSettingsBtn || !themeModal) return;
                
                // Get default theme from server
                const defaultTheme = '<?php echo htmlspecialchars($defaultTheme, ENT_QUOTES, 'UTF-8'); ?>';
                
                // Load saved theme or use default
                function loadTheme() {
                    try {
                        const savedTheme = localStorage.getItem('selectedTheme');
                        // Return saved theme if it exists and is not null/empty, otherwise use default
                        return (savedTheme !== null && savedTheme !== '') ? savedTheme : defaultTheme;
                    } catch (e) {
                        console.error('Failed to load theme from localStorage:', e);
                        return defaultTheme;
                    }
                }
                
                // Save theme to localStorage
                function saveTheme(theme) {
                    try {
                        localStorage.setItem('selectedTheme', theme);
                    } catch (e) {
                        console.error('Failed to save theme to localStorage:', e);
                    }
                }
                
                // Apply theme to document
                function applyTheme(theme) {
                    document.documentElement.setAttribute('data-theme', theme);
                    
                    // Update selected state in modal
                    themeOptions.forEach(option => {
                        if (option.dataset.theme === theme) {
                            option.classList.add('selected');
                        } else {
                            option.classList.remove('selected');
                        }
                    });
                }
                
                // Initialize theme on page load
                const currentTheme = loadTheme();
                applyTheme(currentTheme);
                
                // Open theme modal
                themeSettingsBtn.addEventListener('click', function() {
                    themeModal.classList.add('active');
                    themeModal.setAttribute('aria-hidden', 'false');
                });
                
                // Close theme modal
                function closeThemeModal() {
                    themeModal.classList.remove('active');
                    themeModal.setAttribute('aria-hidden', 'true');
                }
                
                themeModalCloseBtn.addEventListener('click', closeThemeModal);
                
                // Close modal when clicking outside
                themeModal.addEventListener('click', function(e) {
                    if (e.target === themeModal) {
                        closeThemeModal();
                    }
                });
                
                // Handle theme selection
                themeOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        const selectedTheme = this.dataset.theme;
                        applyTheme(selectedTheme);
                        saveTheme(selectedTheme);
                    });
                    
                    // Keyboard support
                    option.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            const selectedTheme = this.dataset.theme;
                            applyTheme(selectedTheme);
                            saveTheme(selectedTheme);
                        }
                    });
                });
                
                // Keyboard support for closing modal
                themeModal.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeThemeModal();
                    }
                });
            })();
            
            // ================================================================
            // LOGIN FUNCTIONALITY
            // ================================================================
            <?php if ($requireLogin): ?>
            (function() {
                const loginForm = document.getElementById('loginForm');
                const loginBtn = document.getElementById('loginBtn');
                const loginError = document.getElementById('loginError');
                
                if (!loginForm) return;
                
                loginForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const username = document.getElementById('username').value;
                    const password = document.getElementById('password').value;
                    
                    loginBtn.disabled = true;
                    loginBtn.textContent = 'Logging in...';
                    loginError.style.display = 'none';
                    
                    try {
                        const formData = new FormData();
                        formData.append('login', '1');
                        formData.append('username', username);
                        formData.append('password', password);
                        
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            window.location.reload();
                        } else {
                            loginError.textContent = data.message || 'Login failed';
                            loginError.style.display = 'block';
                            loginBtn.disabled = false;
                            loginBtn.textContent = 'Login';
                        }
                    } catch (err) {
                        loginError.textContent = 'An error occurred. Please try again.';
                        loginError.style.display = 'block';
                        loginBtn.disabled = false;
                        loginBtn.textContent = 'Login';
                    }
                });
            })();
            <?php endif; ?>
            
            // ================================================================
            // USER MANAGEMENT FUNCTIONALITY
            // ================================================================
            <?php if (isAdmin()): ?>
            (function() {
                const userManagementBtn = document.getElementById('userManagementBtn');
                const userManagementModal = document.getElementById('userManagementModal');
                const userForm = document.getElementById('userForm');
                const userFormCancel = document.getElementById('userFormCancel');
                const userManagementError = document.getElementById('userManagementError');
                const userList = document.getElementById('userList');
                
                if (!userManagementBtn || !userManagementModal) return;
                
                // Show modal
                userManagementBtn.addEventListener('click', function() {
                    userManagementModal.classList.add('active');
                    loadUsers();
                });
                
                // Close modal
                userFormCancel.addEventListener('click', function() {
                    userManagementModal.classList.remove('active');
                    resetForm();
                });
                
                // Click outside to close
                userManagementModal.addEventListener('click', function(e) {
                    if (e.target === userManagementModal) {
                        userManagementModal.classList.remove('active');
                        resetForm();
                    }
                });
                
                // Load users list
                async function loadUsers() {
                    try {
                        const formData = new FormData();
                        formData.append('user_management', '1');
                        formData.append('action', 'list');
                        
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success && data.users) {
                            renderUserList(data.users);
                        } else {
                            showUserError(data.message || 'Failed to load users');
                        }
                    } catch (err) {
                        showUserError('Failed to load users');
                    }
                }
                
                // Render user list
                function renderUserList(users) {
                    if (!users || users.length === 0) {
                        userList.innerHTML = '<div style="padding: 15px; text-align: center; color: var(--muted);">No users found</div>';
                        return;
                    }
                    
                    let html = '';
                    users.forEach(user => {
                        const permissionsStr = user.permissions && user.permissions.length > 0 
                            ? user.permissions.join(', ') 
                            : 'None';
                        html += `
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid var(--border); background: var(--surface);">
                                <div>
                                    <strong style="color: var(--text);">${escapeHtml(user.username)}</strong>
                                    ${user.admin ? '<span style="background: var(--accent); color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 8px;">ADMIN</span>' : ''}
                                    <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Permissions: ${escapeHtml(permissionsStr)}</div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button onclick="editUser('${escapeHtml(user.username)}', ${user.admin}, ${JSON.stringify(user.permissions || [])})" style="padding: 6px 12px; background: var(--accent); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Edit</button>
                                    <button onclick="deleteUser('${escapeHtml(user.username)}')" style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Delete</button>
                                </div>
                            </div>
                        `;
                    });
                    userList.innerHTML = html;
                }
                
                // Edit user
                window.editUser = function(username, isAdmin, permissions) {
                    document.getElementById('userFormAction').value = 'update';
                    document.getElementById('userFormTitle').textContent = 'Edit User';
                    document.getElementById('userFormUsername').value = username;
                    document.getElementById('userFormUsername').readOnly = true;
                    document.getElementById('userFormPassword').required = false;
                    document.getElementById('passwordHint').style.display = 'inline';
                    document.getElementById('userFormAdmin').checked = isAdmin;
                    
                    // Set permissions
                    document.querySelectorAll('.permission-checkbox').forEach(cb => {
                        cb.checked = permissions.includes(cb.value);
                    });
                    
                    document.getElementById('userFormSubmit').textContent = 'Update User';
                };
                
                // Delete user
                window.deleteUser = async function(username) {
                    if (!confirm(`Are you sure you want to delete user "${username}"?`)) {
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('user_management', '1');
                        formData.append('action', 'delete');
                        formData.append('username', username);
                        
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            loadUsers();
                            resetForm();
                        } else {
                            showUserError(data.message || 'Failed to delete user');
                        }
                    } catch (err) {
                        showUserError('Failed to delete user');
                    }
                };
                
                // Submit form
                userForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const action = document.getElementById('userFormAction').value;
                    const username = document.getElementById('userFormUsername').value;
                    const password = document.getElementById('userFormPassword').value;
                    const isAdmin = document.getElementById('userFormAdmin').checked;
                    
                    // Get selected permissions
                    const permissions = Array.from(document.querySelectorAll('.permission-checkbox:checked'))
                        .map(cb => cb.value);
                    
                    // Validate
                    if (!username) {
                        showUserError('Username is required');
                        return;
                    }
                    
                    if (action === 'create' && !password) {
                        showUserError('Password is required for new users');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('user_management', '1');
                        formData.append('action', action);
                        formData.append('username', username);
                        if (password) {
                            formData.append('password', password);
                        }
                        formData.append('admin', isAdmin ? 'true' : 'false');
                        formData.append('permissions', JSON.stringify(permissions));
                        
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            loadUsers();
                            resetForm();
                            showUserError(data.message || 'User saved successfully', 'success');
                        } else {
                            showUserError(data.message || 'Failed to save user');
                        }
                    } catch (err) {
                        showUserError('Failed to save user');
                    }
                });
                
                // Reset form
                function resetForm() {
                    document.getElementById('userFormAction').value = 'create';
                    document.getElementById('userFormTitle').textContent = 'Add New User';
                    document.getElementById('userFormUsername').value = '';
                    document.getElementById('userFormUsername').readOnly = false;
                    document.getElementById('userFormPassword').value = '';
                    document.getElementById('userFormPassword').required = true;
                    document.getElementById('passwordHint').style.display = 'none';
                    document.getElementById('userFormAdmin').checked = false;
                    document.querySelectorAll('.permission-checkbox').forEach(cb => {
                        cb.checked = false;
                    });
                    document.getElementById('userFormSubmit').textContent = 'Save User';
                    userManagementError.style.display = 'none';
                }
                
                // Show error
                function showUserError(message, type = 'error') {
                    userManagementError.textContent = message;
                    userManagementError.style.display = 'block';
                    userManagementError.style.background = type === 'success' ? '#d1fae5' : '#fee';
                    userManagementError.style.color = type === 'success' ? '#065f46' : '#ef4444';
                    setTimeout(() => {
                        if (type === 'success') {
                            userManagementError.style.display = 'none';
                        }
                    }, 3000);
                }
                
                // HTML escape utility
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
            })();
            
            // ================================================================
            // SETTINGS MANAGEMENT FUNCTIONALITY
            // ================================================================
            (function() {
                const settingsBtn = document.getElementById('settingsBtn');
                const settingsModal = document.getElementById('settingsModal');
                const settingsForm = document.getElementById('settingsForm');
                const settingsCancel = document.getElementById('settingsCancel');
                const settingsError = document.getElementById('settingsError');
                
                if (!settingsBtn || !settingsModal) return;
                
                // Show modal
                settingsBtn.addEventListener('click', function() {
                    settingsModal.classList.add('active');
                    loadSettings();
                });
                
                // Close modal
                settingsCancel.addEventListener('click', function() {
                    settingsModal.classList.remove('active');
                });
                
                // Click outside to close
                settingsModal.addEventListener('click', function(e) {
                    if (e.target === settingsModal) {
                        settingsModal.classList.remove('active');
                    }
                });
                
                // Load current settings
                async function loadSettings() {
                    try {
                        const formData = new FormData();
                        formData.append('settings_management', '1');
                        formData.append('action', 'get');
                        
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success && data.settings) {
                            document.getElementById('enableRename').checked = data.settings.enableRename || false;
                            document.getElementById('enableDelete').checked = data.settings.enableDelete || false;
                            document.getElementById('enableUpload').checked = data.settings.enableUpload || false;
                            document.getElementById('enableCreateDirectory').checked = data.settings.enableCreateDirectory || false;
                            document.getElementById('enableDownloadAll').checked = data.settings.enableDownloadAll || false;
                            document.getElementById('enableBatchDownload').checked = data.settings.enableBatchDownload || false;
                            document.getElementById('enableIndividualDownload').checked = data.settings.enableIndividualDownload || false;
                        } else {
                            showSettingsError(data.message || 'Failed to load settings');
                        }
                    } catch (err) {
                        showSettingsError('Failed to load settings');
                    }
                }
                
                // Save settings
                settingsForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    try {
                        const formData = new FormData();
                        formData.append('settings_management', '1');
                        formData.append('action', 'save');
                        formData.append('enableRename', document.getElementById('enableRename').checked ? 'true' : 'false');
                        formData.append('enableDelete', document.getElementById('enableDelete').checked ? 'true' : 'false');
                        formData.append('enableUpload', document.getElementById('enableUpload').checked ? 'true' : 'false');
                        formData.append('enableCreateDirectory', document.getElementById('enableCreateDirectory').checked ? 'true' : 'false');
                        formData.append('enableDownloadAll', document.getElementById('enableDownloadAll').checked ? 'true' : 'false');
                        formData.append('enableBatchDownload', document.getElementById('enableBatchDownload').checked ? 'true' : 'false');
                        formData.append('enableIndividualDownload', document.getElementById('enableIndividualDownload').checked ? 'true' : 'false');
                        
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            settingsModal.classList.remove('active');
                            if (typeof window.showToast === 'function') {
                                window.showToast('Settings saved successfully! The page will reload to apply changes.', 'success', 3000);
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                alert('Settings saved successfully! The page will reload.');
                                window.location.reload();
                            }
                        } else {
                            showSettingsError(data.message || 'Failed to save settings');
                        }
                    } catch (err) {
                        showSettingsError('Failed to save settings: ' + err.message);
                    }
                });
                
                // Show error/success message
                function showSettingsError(message, type = 'error') {
                    settingsError.textContent = message;
                    settingsError.style.display = 'block';
                    settingsError.style.background = type === 'success' ? '#d1fae5' : '#fee';
                    settingsError.style.color = type === 'success' ? '#065f46' : '#ef4444';
                    settingsError.style.padding = '12px';
                    settingsError.style.borderRadius = '8px';
                    settingsError.style.marginBottom = '15px';
                    
                    if (type === 'success') {
                        setTimeout(() => {
                            settingsError.style.display = 'none';
                        }, 3000);
                    }
                }
            })();
            <?php endif; ?>
        })();
    </script>
</body>
</html>
