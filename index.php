<?php
    $title = "Simple PHP File Lister";
    $subtitle = "The Easy Way To List Files In A Directory";
    $footer = "Made with ❤️ by Blind Trevor";
    
    // Security: prevent directory traversal
    $realRoot = rtrim(realpath('.'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
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
        $blocked = ['php', 'phtml', 'phar', 'cgi', 'pl', 'sh', 'bat', 'exe', 
                    'jsp', 'asp', 'aspx', 'py', 'rb', 'ps1', 'vbs', 'htaccess'];
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if (in_array($ext, $blocked, true)) {
            http_response_code(403);
            exit('Forbidden');
        }
        
        // Set secure download headers with properly escaped filename
        $filename = basename($full);
        // Remove control characters and characters that could enable header injection
        // while preserving Unicode characters for international filenames
        $safeFilename = preg_replace('/[\x00-\x1F\x7F"\\\\]/', '', $filename);
        $safeFilename = str_replace(["\r", "\n"], '', $safeFilename);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
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

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self';
        style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline';
        script-src 'self';
        img-src 'self' https://img.shields.io;
        font-src https://cdnjs.cloudflare.com;
        object-src 'none';
        base-uri 'self';
        frame-ancestors 'none'");
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

    function getFileIcon(string $path): array {
        $colors = [
            'pdf' => '#e74c3c', 'word' => '#2980b9', 'text' => '#7f8c8d',
            'excel' => '#27ae60', 'powerpoint' => '#e67e22', 'archive' => '#8e44ad',
            'image' => '#f39c12', 'audio' => '#c0392b', 'video' => '#16a085',
            'code' => '#34495e', 'html' => '#e74c3c', 'css' => '#2980b9',
            'js' => '#f1c40f', 'ts' => '#2b5bae', 'python' => '#3776ab',
            'php' => '#777bb4', 'powershell' => '#0078d4', 'sql' => '#336791',
            'yaml' => '#cb171e', 'markdown' => '#083fa1', 'default' => '#95a5a6',
        ];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extMap = [
            'pdf' => ['fa-regular fa-file-pdf', $colors['pdf']],
            'doc' => ['fa-regular fa-file-word', $colors['word']],
            'docx' => ['fa-regular fa-file-word', $colors['word']],
            'xlsx' => ['fa-regular fa-file-excel', $colors['excel']],
            'pptx' => ['fa-regular fa-file-powerpoint', $colors['powerpoint']],
            'zip' => ['fa-solid fa-file-zipper', $colors['archive']],
            'jpg' => ['fa-regular fa-file-image', $colors['image']],
            'png' => ['fa-regular fa-file-image', $colors['image']],
            'mp3' => ['fa-regular fa-file-audio', $colors['audio']],
            'mp4' => ['fa-regular fa-file-video', $colors['video']],
            'html' => ['fa-regular fa-file-code', $colors['html']],
            'css' => ['fa-regular fa-file-code', $colors['css']],
            'js' => ['fa-regular fa-file-code', $colors['js']],
            'php' => ['fa-regular fa-file-code', $colors['php']],
            'md' => ['fa-regular fa-file-lines', $colors['markdown']],
        ];

        return $extMap[$ext] ?? ['fa-regular fa-file', $colors['default']];
    }

    function renderItem(string $entry, bool $isDir, string $currentPath): void {
        if ($isDir) {
            $href = '?path=' . rawurlencode($currentPath ? $currentPath . '/' . $entry : $entry);
            $iconClass = 'fa-solid fa-folder';
            $iconColor = '#f6a623';
            $linkClass = 'class="dir-link"';
        } else {
            // Use secure download handler for files
            $filePath = $currentPath ? $currentPath . '/' . $entry : $entry;
            $href = '?download=' . rawurlencode($filePath);
            [$iconClass, $iconColor] = getFileIcon($entry);
            $linkClass = '';
        }

        $label = htmlspecialchars($entry, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        printf(
            '<li><a href="%s" %s><span class="file-icon" style="color:%s;"><i class="%s"></i></span><span class="file-name">%s</span></a></li>' . PHP_EOL,
            htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $linkClass,
            htmlspecialchars($iconColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($iconClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $label
        );
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
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
        .file-name { font-weight: 500; word-break: break-all; }
        .folder-file-count { margin-top: 20px; padding: 12px 16px; background: #f0f0f0; border-radius: 8px; text-align: center; color: var(--muted); }
        footer { margin-top: 16px; text-align: center; font-size: 0.85rem; color: var(--muted); }
        .loading-overlay { position: fixed; inset: 0; background: rgba(255, 255, 255, 0.85); backdrop-filter: saturate(180%) blur(2px); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-spinner { width: 56px; height: 56px; border: 6px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
        .loading-text { margin-top: 14px; color: var(--muted); font-size: 0.95rem; text-align: center; }
        .loading-overlay.is-active { display: flex; }
        @media (prefers-reduced-motion: reduce) { .loading-spinner { animation: none; border-top-color: var(--border); } }
        @keyframes spin { to { transform: rotate(360deg); } }
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
                        printf('<li><a href="?path=%s" class="dir-link"><span class="file-icon" style="color:#f6a623;"><i class="fa-solid fa-arrow-up"></i></span><span class="file-name">..</span></a></li>' . PHP_EOL, $parentPath ? rawurlencode($parentPath) : '');
                    }

                    $dirs = [];
                    $files = [];

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
                                $files[] = $entry;
                            }
                        }
                        closedir($handle);
                    }

                    natcasesort($dirs);
                    natcasesort($files);

                    foreach ($dirs as $entry) {
                        renderItem($entry, true, $currentPath);
                    }
                    foreach ($files as $entry) {
                        renderItem($entry, false, $currentPath);
                    }

                    echo '<li class="folder-file-count">';
                    echo count($dirs) . ' folder' . (count($dirs) !== 1 ? 's' : '') . ', ';
                    echo count($files) . ' file' . (count($files) !== 1 ? 's' : '');
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

    <script>
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