<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shrek the Musical – Band Parts</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        :root {
            --bg: #f2f4f8;
            --card: #ffffff;
            --accent: #4f46e5;
            --text: #1f2933;
            --muted: #6b7280;
            --hover: #eef2ff;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 40px 16px;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            padding: 28px;
        }

        h1 {
            margin: 0 0 8px 0;
            font-size: 1.6rem;
        }

        .subtitle {
            margin-bottom: 24px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .file-list li + li {
            margin-top: 10px;
        }

        .file-list a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            background: #fafafa;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.1s ease;
        }

        .file-list a:hover {
            background: var(--hover);
            border-color: var(--accent);
            transform: translateX(4px);
        }

        .file-icon {
            font-size: 1.25rem;
            color: var(--accent);
            flex-shrink: 0;
        }

        .file-name {
            font-weight: 500;
            word-break: break-all;
        }

        footer {
            margin-top: 16px;
            text-align: centre;
            font-size: 0.85rem;
            color: var(--muted);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <h1>Simple PHP File Lister</h1> 
            <div class="subtitle">
                Click a file to download. 
            </div>

            <ul class="file-list">
                <?php
				
                function fa_icon_class_for_file(string $path): string
                {
                    // Color palette
                    $colors = [
                        'pdf'       => '#e74c3c',
                        'word'      => '#2980b9',
                        'text'      => '#7f8c8d',
                        'excel'     => '#27ae60',
                        'powerpoint'=> '#e67e22',
                        'archive'   => '#8e44ad',
                        'image'     => '#f39c12',
                        'audio'     => '#c0392b',
                        'video'     => '#16a085',
                        'code'      => '#34495e',
                        'html'      => '#e74c3c',
                        'css'       => '#2980b9',
                        'js'        => '#f1c40f',
                        'ts'        => '#2b5bae',
                        'python'    => '#3776ab',
                        'php'       => '#777bb4',
                        'powershell'=> '#0078d4',
                        'sql'       => '#336791',
                        'yaml'      => '#cb171e',
                        'markdown'  => '#083fa1',
                        'default'   => '#95a5a6',
                    ];

                    $realPath = $path;
                    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

                    $extMap = [
                        'pdf'  => ['fa-regular fa-file-pdf', $colors['pdf']],
                        'doc'  => ['fa-regular fa-file-word', $colors['word']],
                        'docx' => ['fa-regular fa-file-word', $colors['word']],
                        'rtf'  => ['fa-regular fa-file-lines', $colors['text']],
                        'xls'  => ['fa-regular fa-file-excel', $colors['excel']],
                        'xlsx' => ['fa-regular fa-file-excel', $colors['excel']],
                        'csv'  => ['fa-solid fa-file-csv', $colors['excel']],
                        'ppt'  => ['fa-regular fa-file-powerpoint', $colors['powerpoint']],
                        'pptx' => ['fa-regular fa-file-powerpoint', $colors['powerpoint']],
                        'zip'  => ['fa-solid fa-file-zipper', $colors['archive']],
                        'rar'  => ['fa-solid fa-file-zipper', $colors['archive']],
                        '7z'   => ['fa-solid fa-file-zipper', $colors['archive']],
                        'tar'  => ['fa-solid fa-file-zipper', $colors['archive']],
                        'gz'   => ['fa-solid fa-file-zipper', $colors['archive']],
                        'jpg'  => ['fa-regular fa-file-image', $colors['image']],
                        'jpeg' => ['fa-regular fa-file-image', $colors['image']],
                        'png'  => ['fa-regular fa-file-image', $colors['image']],
                        'gif'  => ['fa-regular fa-file-image', $colors['image']],
                        'svg'  => ['fa-regular fa-file-image', $colors['image']],
                        'webp' => ['fa-regular fa-file-image', $colors['image']],
                        'mp3'  => ['fa-regular fa-file-audio', $colors['audio']],
                        'wav'  => ['fa-regular fa-file-audio', $colors['audio']],
                        'flac' => ['fa-regular fa-file-audio', $colors['audio']],
                        'ogg'  => ['fa-regular fa-file-audio', $colors['audio']],
                        'm4a'  => ['fa-regular fa-file-audio', $colors['audio']],
                        'mp4'  => ['fa-regular fa-file-video', $colors['video']],
                        'mov'  => ['fa-regular fa-file-video', $colors['video']],
                        'avi'  => ['fa-regular fa-file-video', $colors['video']],
                        'mkv'  => ['fa-regular fa-file-video', $colors['video']],
                        'webm' => ['fa-regular fa-file-video', $colors['video']],
                        'json' => ['fa-regular fa-file-code', $colors['code']],
                        'xml'  => ['fa-regular fa-file-code', $colors['code']],
                        'html' => ['fa-regular fa-file-code', $colors['html']],
                        'htm'  => ['fa-regular fa-file-code', $colors['html']],
                        'css'  => ['fa-regular fa-file-code', $colors['css']],
                        'js'   => ['fa-regular fa-file-code', $colors['js']],
                        'ts'   => ['fa-regular fa-file-code', $colors['ts']],
                        'py'   => ['fa-regular fa-file-code', $colors['python']],
                        'php'  => ['fa-regular fa-file-code', $colors['php']],
                        'sh'   => ['fa-regular fa-file-code', $colors['code']],
                        'bat'  => ['fa-regular fa-file-code', $colors['code']],
                        'ps1'  => ['fa-regular fa-file-code', $colors['powershell']],
                        'sql'  => ['fa-regular fa-file-code', $colors['sql']],
                        'yml'  => ['fa-regular fa-file-code', $colors['yaml']],
                        'yaml' => ['fa-regular fa-file-code', $colors['yaml']],
                        'txt'  => ['fa-regular fa-file-lines', $colors['text']],
                        'log'  => ['fa-regular fa-file-lines', $colors['text']],
                        'md'   => ['fa-regular fa-file-lines', $colors['markdown']],
                        'rtx'  => ['fa-regular fa-file-lines', $colors['text']],
                    ];

                    if ($ext !== '' && isset($extMap[$ext])) {
                        return json_encode($extMap[$ext]);
                    }

                    $mime = '';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo) {
                            $detected = @finfo_file($finfo, $realPath);
                            if (is_string($detected)) {
                                $mime = strtolower(trim($detected));
                            }
                            finfo_close($finfo);
                        }
                    } else {
                        $detected = @mime_content_type($realPath);
                        if (is_string($detected)) {
                            $mime = strtolower(trim($detected));
                        }
                    }

                    if ($mime) {
                        if ($mime === 'application/pdf') return json_encode(['fa-regular fa-file-pdf', $colors['pdf']]);
                        if (str_contains($mime, 'msword') || str_contains($mime, 'wordprocessingml')) return json_encode(['fa-regular fa-file-word', $colors['word']]);
                        if (str_contains($mime, 'vnd.ms-excel') || str_contains($mime, 'spreadsheetml')) return json_encode(['fa-regular fa-file-excel', $colors['excel']]);
                        if (str_contains($mime, 'vnd.ms-powerpoint') || str_contains($mime, 'presentationml')) return json_encode(['fa-regular fa-file-powerpoint', $colors['powerpoint']]);
                        if (str_contains($mime, 'zip') || str_contains($mime, 'x-7z-compressed') || str_contains($mime, 'x-rar-compressed') || str_contains($mime, 'x-zip')) {
                            return json_encode(['fa-solid fa-file-zipper', $colors['archive']]);
                        }
                        if (str_starts_with($mime, 'image/')) return json_encode(['fa-regular fa-file-image', $colors['image']]);
                        if (str_starts_with($mime, 'audio/')) return json_encode(['fa-regular fa-file-audio', $colors['audio']]);
                        if (str_starts_with($mime, 'video/')) return json_encode(['fa-regular fa-file-video', $colors['video']]);
                        if ($mime === 'text/plain' || str_contains($mime, 'json') || str_contains($mime, 'xml') || str_contains($mime, 'javascript') || str_contains($mime, 'css') || str_contains($mime, 'yaml') || str_contains($mime, 'yml') || str_contains($mime, 'x-shellscript')) {
                            return json_encode([$mime === 'text/plain' ? 'fa-regular fa-file-lines' : 'fa-regular fa-file-code', $colors['code']]);
                        }
                    }

                    return json_encode(['fa-regular fa-file', $colors['default']]);
                }
                
                if ($handle = opendir('.')) {
                    while (false !== ($entry = readdir($handle))) {
                        if ($entry !== "." && $entry !== ".." && $entry !== "index.php") {
                            echo "<li>";
                            echo "<a href=\"$entry\" download>";
                            $iconData = json_decode(fa_icon_class_for_file($entry), true);
                            echo '<span class="file-icon" style="color: ' . $iconData[1] . ';" aria-hidden="true"><i class="' . $iconData[0] . '"></i></span>';
                            echo "<span class=\"file-name\">$entry</span>";
                            echo "</a>";
                            echo "</li>";
                        }
                    }
                    closedir($handle);
                }
                ?>
            </ul>
        </div>

        <footer>
            Simple PHP File Lister - &copy; Andrew Samuel 2026
        </footer>
    </div>
</body>
</html>
