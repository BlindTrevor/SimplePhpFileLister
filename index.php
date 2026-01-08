<?php
    $title = "Simple PHP File Lister";
    $subtitle = "The Easy Way To List Files In A Directory";
    $footer = "Made with ❤️ by Blind Trevor";
    
    // Security: prevent directory traversal
    $realRoot = rtrim(realpath('.'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
    // Blocked file extensions to prevent code execution
    define('BLOCKED_EXTENSIONS', ['php', 'phtml', 'phar', 'cgi', 'pl', 'sh', 'bat', 'exe', 
                                   'jsp', 'asp', 'aspx', 'py', 'rb', 'ps1', 'vbs', 'htaccess',
                                   'scr', 'com', 'jar']);
    
    // Secure download all as zip handler
    if (isset($_GET['download_all_zip'])) {
        // Check if ZipArchive is available
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            exit('ZIP support is not available');
        }
        
        $currentPath = isset($_GET['path']) ? rtrim((string)$_GET['path'], '/') : '';
        $basePath = $currentPath ? './' . str_replace('\\', '/', $currentPath) : '.';
        $realBase = realpath($basePath);
        
        // Validate path is within root
        if ($realBase === false || strpos($realBase . DIRECTORY_SEPARATOR, $realRoot) !== 0) {
            http_response_code(404);
            exit('Not found');
        }
        
        // Create a temporary zip file
        $tempZip = tempnam(sys_get_temp_dir(), 'spfl_');
        $zip = new ZipArchive();
        
        if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            exit('Failed to create ZIP file');
        }
        
        // Add files to zip
        $fileCount = 0;
        if ($handle = opendir($basePath)) {
            while (($entry = readdir($handle)) !== false) {
                if (in_array($entry, ['.', '..', 'index.php'], true) || $entry[0] === '.') {
                    continue;
                }
                
                $fullPath = $basePath . '/' . $entry;
                $realPath = realpath($fullPath);
                
                // Skip invalid paths, symlinks, directories
                if (is_link($fullPath) || $realPath === false || 
                    strpos($realPath, $realRoot) !== 0 || is_dir($fullPath)) {
                    continue;
                }
                
                // Block dangerous extensions
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
                    continue;
                }
                
                // Add file to zip
                if ($zip->addFile($fullPath, $entry)) {
                    $fileCount++;
                }
            }
            closedir($handle);
        }
        
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
        
        // Send the zip file
        $safeFilename = preg_replace('/[\x00-\x1F\x7F"\\\\\\\\]|[\r\n]/', '', $zipFilename);
        $encodedFilename = rawurlencode($zipFilename);
        
        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename=\"{$safeFilename}\"; filename*=UTF-8''{$encodedFilename}");
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($tempZip));
        
        readfile($tempZip);
        @unlink($tempZip);
        exit;
    }
    
    // Secure download handler
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
        
        // Set secure download headers with properly escaped filename
        $filename = basename($full);
        // Remove control characters and dangerous chars for header injection prevention
        $safeFilename = preg_replace('/[\x00-\x1F\x7F"\\\\\\\\]|[\r\n]/', '', $filename);
        // Use RFC 2231 encoding for Unicode filename support
        $encodedFilename = rawurlencode($filename);
        
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"{$safeFilename}\"; filename*=UTF-8''{$encodedFilename}");
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($full));
        
        // Serve the file
        readfile($full);
        exit;
    }
    
    // Redirect if path=.
    if (isset($_GET['path']) && $_GET['path'] === '.') {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Generate a cryptographically secure nonce for CSP
    $cspNonce = base64_encode(random_bytes(16));
    
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdnjs.cloudflare.com 'nonce-{$cspNonce}'; script-src 'self' 'nonce-{$cspNonce}'; img-src 'self' https://img.shields.io; font-src https://cdnjs.cloudflare.com; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
    $currentPath = isset($_GET['path']) ? rtrim((string)$_GET['path'], '/') : '';
    $basePath = $currentPath ? './' . str_replace('\\', '/', $currentPath) : '.';
    $realBase = realpath($basePath);
    
    $isValidPath = $realBase !== false && strpos($realBase . DIRECTORY_SEPARATOR, $realRoot) === 0;

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

    function renderItem(string $entry, bool $isDir, string $currentPath, int $fileSize = 0): void {
        if ($isDir) {
            $href = '?path=' . rawurlencode($currentPath ? $currentPath . '/' . $entry : $entry);
            $iconClass = 'fa-solid fa-folder';
            $colorClass = 'icon-folder';
            $linkAttributes = 'class="dir-link"';
            $sizeHtml = '';
        } else {
            // Use secure download handler for files
            $filePath = $currentPath ? $currentPath . '/' . $entry : $entry;
            $href = '?download=' . rawurlencode($filePath);
            [$iconClass, $colorClass] = getFileIcon($entry);
            // Open downloads in new tab to prevent loading overlay on main page
            $linkAttributes = 'target="_blank" rel="noopener noreferrer"';
            $sizeHtml = '<span class="file-size">' . htmlspecialchars(formatFileSize($fileSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
        }

        $label = htmlspecialchars($entry, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        printf(
            '<li><a href="%s" %s><span class="file-icon %s"><i class="%s"></i></span><span class="file-name">%s</span>%s</a></li>' . PHP_EOL,
            htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $linkAttributes,
            htmlspecialchars($colorClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($iconClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $label,
            $sizeHtml
        );
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous">
    <style nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
        :root { --bg: #f2f4f8; --card: #ffffff; --accent: #4f46e5; --text: #1f2933; --muted: #6b7280; --hover: #eef2ff; --border: #e5e7eb; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg); color: var(--text); padding: 40px 16px; }
        .container { max-width: 720px; margin: 0 auto; }
        .card { background: var(--card); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); padding: 28px; }
        h1 { margin: 0 0 8px 0; font-size: 1.6rem; }
        .subtitle { margin-bottom: 24px; color: var(--muted); font-size: 0.95rem; }
        .breadcrumbs { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; padding: 12px 16px; background: #fafafa; border-radius: 8px; border: 1px solid var(--border); font-size: 0.9rem; flex-wrap: wrap; }
        .breadcrumbs a { color: var(--accent); text-decoration: none; transition: color 0.2s ease; }
        .breadcrumbs a:hover { color: #3f37c5; text-decoration: underline; }
        .breadcrumbs > a:first-child { font-weight: 500; }
        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-list li + li { margin-top: 10px; }
        .file-list a { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 8px; text-decoration: none; color: var(--text); background: #fafafa; transition: background 0.2s ease, border-color 0.2s ease, transform 0.1s ease; }
        .file-list a:hover { background: var(--hover); border-color: var(--accent); transform: translateX(4px); }
        .file-icon { font-size: 1.25rem; color: var(--accent); flex-shrink: 0; }
        .file-name { font-weight: 500; word-break: break-all; flex: 1; }
        .file-size { font-size: 0.85rem; color: var(--muted); white-space: nowrap; margin-left: auto; padding-left: 12px; }
        .folder-file-count { margin-top: 20px; padding: 12px 16px; background: #f0f0f0; border-radius: 8px; text-align: center; color: var(--muted); }
        footer { margin-top: 16px; text-align: center; font-size: 0.85rem; color: var(--muted); }
        .loading-overlay { position: fixed; inset: 0; background: rgba(255, 255, 255, 0.85); backdrop-filter: saturate(180%) blur(2px); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-spinner { width: 56px; height: 56px; border: 6px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
        .loading-text { margin-top: 14px; color: var(--muted); font-size: 0.95rem; text-align: center; }
        .loading-overlay.is-active { display: flex; }
        @media (prefers-reduced-motion: reduce) { .loading-spinner { animation: none; border-top-color: var(--border); } }
        @keyframes spin { to { transform: rotate(360deg); } }
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
        .download-all-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: var(--accent); color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; text-decoration: none; cursor: pointer; transition: background 0.2s ease, transform 0.1s ease; margin-bottom: 16px; }
        .download-all-btn:hover { background: #3f37c5; transform: translateY(-1px); }
        .download-all-btn i { font-size: 1.1rem; }
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

            <?php
                // Show download all button if there are files in current directory
                if ($isValidPath) {
                    $hasFiles = false;
                    if ($handle = opendir($basePath)) {
                        while (($entry = readdir($handle)) !== false) {
                            if (in_array($entry, ['.', '..', 'index.php'], true) || $entry[0] === '.') {
                                continue;
                            }
                            $fullPath = $basePath . '/' . $entry;
                            if (!is_dir($fullPath)) {
                                $hasFiles = true;
                                break;
                            }
                        }
                        closedir($handle);
                    }
                    
                    if ($hasFiles) {
                        $downloadAllUrl = '?download_all_zip=1';
                        if ($currentPath) {
                            $downloadAllUrl .= '&path=' . rawurlencode($currentPath);
                        }
                        echo '<a href="' . htmlspecialchars($downloadAllUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="download-all-btn" target="_blank" rel="noopener noreferrer">';
                        echo '<i class="fa-solid fa-download"></i>';
                        echo 'Download All as ZIP';
                        echo '</a>';
                    }
                }
            ?>

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

                    foreach ($dirs as $entry) {
                        renderItem($entry, true, $currentPath);
                    }
                    foreach ($files as $file) {
                        renderItem($file['name'], false, $currentPath, $file['size']);
                    }

                    echo '<li class="folder-file-count">';
                    echo count($dirs) . ' folder' . (count($dirs) !== 1 ? 's' : '') . ', ';
                    echo count($files) . ' file' . (count($files) !== 1 ? 's' : '');
                    if ($totalSize > 0) {
                        echo ' (' . htmlspecialchars(formatFileSize($totalSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' total)';
                    }
                    echo '</li>';
                }
                ?>
            </ul>
        </div>

        <?php if (!empty($footer)): ?>
        <footer><?php echo htmlspecialchars($footer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></footer>
        <?php endif; ?>

        <footer>
            <a href="https://github.com/BlindTrevor/SimplePhpFileLister/" target="_blank">
                <img src="https://img.shields.io/badge/Created_by_Blind_Trevor-Simple_PHP_File_Lister-magenta" alt="GitHub"/>
            </a>
        </footer>
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

                const isDirLink = a.classList.contains('dir-link') && !a.hasAttribute('download');
                if (isDirLink) showOverlay();
            }, { capture: true });

            window.addEventListener('beforeunload', showOverlay);
        })();
    </script>
</body>
</html>