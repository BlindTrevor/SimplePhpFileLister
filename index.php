<?php
    $title = "Simple PHP File Lister";
    $subtitle = "Click a file to download";
    $footer = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
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

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 40px 16px;
        }

        .container { max-width: 720px; margin: 0 auto; }
        .card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            padding: 28px;
        }

        h1 { margin: 0 0 8px 0; font-size: 1.6rem; }
        .subtitle { margin-bottom: 24px; color: var(--muted); font-size: 0.95rem; }

        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-list li + li { margin-top: 10px; }

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

        .file-icon { font-size: 1.25rem; color: var(--accent); flex-shrink: 0; }
        .file-name { font-weight: 500; word-break: break-all; }

        footer { margin-top: 16px; text-align: center; font-size: 0.85rem; color: var(--muted); }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div>

            <ul class="file-list">
                <?php
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

                if ($handle = opendir('.')) {
                    while ($entry = readdir($handle)) {
                        if (!in_array($entry, ['.', '..', 'index.php'])) {
                            $icon = getFileIcon($entry);
                            echo sprintf(
                                '<li><a href="%s" download><span class="file-icon" style="color:%s;"><i class="%s"></i></span><span class="file-name">%s</span></a></li>',
                                htmlspecialchars($entry),
                                $icon[1],
                                $icon[0],
                                htmlspecialchars($entry)
                            );
                        }
                    }
                    closedir($handle);
                }
                ?>
            </ul>
        </div>
		<?php if(!empty($footer)){ ?>
        <footer>
			<?php echo $footer; ?>
		</footer>
		<?php } ?>
		<footer>
			<a href="https://github.com/BlindTrevor/SimplePhpFileLister/" target="_blank"><img src="https://img.shields.io/badge/Simple_PHP-File_Lister-magenta"/></a>
			<img src="https://img.shields.io/github/last-commit/BlindTrevor/SimplePhpFileLister"/>
			<img src="https://img.shields.io/github/issues/BlindTrevor/SimplePhpFileLister"/>
			<img src="https://img.shields.io/github/repo-size/BlindTrevor/SimplePhpFileLister"/>
		</footer>
    </div>
</body>
</html>