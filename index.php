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
        
        // Open file for reading before sending headers
        $fp = fopen($full, 'rb');
        if ($fp === false) {
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

    function hasDownloadableContent(string $dir, string $realRoot): bool {
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
        
        /* File type icon colors */
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
        
        /* Responsive breakpoints */
        
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
        
        /* Print styles */
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

                    foreach ($dirs as $entry) {
                        renderItem($entry, true, $currentPath);
                    }
                    foreach ($files as $file) {
                        renderItem($file['name'], false, $currentPath, $file['size']);
                    }
                }
                ?>
            </ul>

            <?php
            // Unified stats container with folder/file count and download button
            if ($isValidPath) {
                $statsHtml = '';
                $statsHtml .= count($dirs) . ' folder' . (count($dirs) !== 1 ? 's' : '') . ', ';
                $statsHtml .= count($files) . ' file' . (count($files) !== 1 ? 's' : '');
                if ($totalSize > 0) {
                    $statsHtml .= ' (' . htmlspecialchars(formatFileSize($totalSize), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' total)';
                }
                
                echo '<div class="stats-container">';
                echo '<div class="folder-file-count">' . $statsHtml . '</div>';
                
                // Show download button if there's any downloadable content
                if (hasDownloadableContent($basePath, $realRoot)) {
                    $downloadAllUrl = '?download_all_zip=1';
                    if ($currentPath) {
                        $downloadAllUrl .= '&path=' . rawurlencode($currentPath);
                    }
                    echo '<a href="' . htmlspecialchars($downloadAllUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="download-all-btn" target="_blank" rel="noopener noreferrer">';
                    echo '<i class="fa-solid fa-download"></i>';
                    echo 'Download All as ZIP';
                    echo '</a>';
                }
                
                echo '</div>';
            }
            ?>
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