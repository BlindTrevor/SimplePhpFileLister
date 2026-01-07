<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shrek the Musical – Band Parts</title>

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
                if ($handle = opendir('.')) {

                    while (false !== ($entry = readdir($handle))) {

                        if ($entry !== "." && $entry !== ".." && $entry !== "index.php") {
                            echo "<li>";
                            echo "<a href=\"$entry\" download>";
                            $icon = match(strtolower(pathinfo($entry, PATHINFO_EXTENSION))) {
                                'pdf' => '📕',
                                'doc', 'docx' => '📄',
                                'xls', 'xlsx' => '📊',
                                'ppt', 'pptx' => '🎯',
                                'zip', 'rar', '7z' => '📦',
                                'jpg', 'jpeg', 'png', 'gif' => '🖼️',
                                'mp3', 'wav', 'flac' => '🎵',
                                'mp4', 'mov', 'avi' => '🎬',
                                'txt' => '📝',
                                default => '📄'
                            };
                            echo "<span class=\"file-icon\">$icon</span>";
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
