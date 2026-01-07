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
					// Make sure we reference the actual path (defensive if you change dirs later)
					$realPath = $path;

					// Normalise extension
					$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

					// --- Known extension map first (trust extension for common types) ---
					$extMap = [
						// Documents
						'pdf'  => 'fa-regular fa-file-pdf',
						'doc'  => 'fa-regular fa-file-word',
						'docx' => 'fa-regular fa-file-word',
						'rtf'  => 'fa-regular fa-file-lines',

						// Spreadsheets
						'xls'  => 'fa-regular fa-file-excel',
						'xlsx' => 'fa-regular fa-file-excel',
						'csv'  => 'fa-solid fa-file-csv',

						// Presentations
						'ppt'  => 'fa-regular fa-file-powerpoint',
						'pptx' => 'fa-regular fa-file-powerpoint',

						// Archives
						'zip'  => 'fa-solid fa-file-zipper',
						'rar'  => 'fa-solid fa-file-zipper',
						'7z'   => 'fa-solid fa-file-zipper',
						'tar'  => 'fa-solid fa-file-zipper',
						'gz'   => 'fa-solid fa-file-zipper',

						// Images
						'jpg'  => 'fa-regular fa-file-image',
						'jpeg' => 'fa-regular fa-file-image',
						'png'  => 'fa-regular fa-file-image',
						'gif'  => 'fa-regular fa-file-image',
						'svg'  => 'fa-regular fa-file-image',
						'webp' => 'fa-regular fa-file-image',

						// Audio
						'mp3'  => 'fa-regular fa-file-audio',
						'wav'  => 'fa-regular fa-file-audio',
						'flac' => 'fa-regular fa-file-audio',
						'ogg'  => 'fa-regular fa-file-audio',
						'm4a'  => 'fa-regular fa-file-audio',

						// Video
						'mp4'  => 'fa-regular fa-file-video',
						'mov'  => 'fa-regular fa-file-video',
						'avi'  => 'fa-regular fa-file-video',
						'mkv'  => 'fa-regular fa-file-video',
						'webm' => 'fa-regular fa-file-video',

						// Code-ish
						'json' => 'fa-regular fa-file-code',
						'xml'  => 'fa-regular fa-file-code',
						'html' => 'fa-regular fa-file-code',
						'htm'  => 'fa-regular fa-file-code',
						'css'  => 'fa-regular fa-file-code',
						'js'   => 'fa-regular fa-file-code',
						'ts'   => 'fa-regular fa-file-code',
						'py'   => 'fa-regular fa-file-code',
						'php'  => 'fa-regular fa-file-code',
						'sh'   => 'fa-regular fa-file-code',
						'bat'  => 'fa-regular fa-file-code',
						'ps1'  => 'fa-regular fa-file-code',
						'sql'  => 'fa-regular fa-file-code',
						'yml'  => 'fa-regular fa-file-code',
						'yaml' => 'fa-regular fa-file-code',

						// Plain text / notes
						'txt'  => 'fa-regular fa-file-lines',
						'log'  => 'fa-regular fa-file-lines',
						'md'   => 'fa-regular fa-file-lines',
						'rtx'  => 'fa-regular fa-file-lines',
					];

					// If the extension is known, return immediately.
					if ($ext !== '' && isset($extMap[$ext])) {
						return $extMap[$ext];
					}

					// --- MIME-based detection only for unknown extensions ---
					// Prefer finfo over mime_content_type for better reliability
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
						// Fallback to mime_content_type if finfo isn't available
						$detected = @mime_content_type($realPath);
						if (is_string($detected)) {
							$mime = strtolower(trim($detected));
						}
					}

					if ($mime) {
						// Documents
						if ($mime === 'application/pdf') return 'fa-regular fa-file-pdf';
						if (str_contains($mime, 'msword') || str_contains($mime, 'wordprocessingml')) return 'fa-regular fa-file-word';
						if (str_contains($mime, 'vnd.ms-excel') || str_contains($mime, 'spreadsheetml')) return 'fa-regular fa-file-excel';
						if (str_contains($mime, 'vnd.ms-powerpoint') || str_contains($mime, 'presentationml')) return 'fa-regular fa-file-powerpoint';

						// Archives
						if (
							str_contains($mime, 'zip') ||
							str_contains($mime, 'x-7z-compressed') ||
							str_contains($mime, 'x-rar-compressed') ||
							str_contains($mime, 'x-zip')
						) {
							return 'fa-solid fa-file-zipper';
						}

						// Images / Audio / Video
						if (str_starts_with($mime, 'image/')) return 'fa-regular fa-file-image';
						if (str_starts_with($mime, 'audio/')) return 'fa-regular fa-file-audio';
						if (str_starts_with($mime, 'video/')) return 'fa-regular fa-file-video';

						// Code-ish / structured text
						if (
							$mime === 'text/plain' ||
							str_contains($mime, 'json') ||
							str_contains($mime, 'xml') ||
							str_contains($mime, 'javascript') ||
							str_contains($mime, 'css') ||
							str_contains($mime, 'yaml') ||
							str_contains($mime, 'yml') ||
							str_contains($mime, 'x-shellscript')
						) {
							return $mime === 'text/plain'
								? 'fa-regular fa-file-lines'
								: 'fa-regular fa-file-code';
						}
					}

					// Default
					return 'fa-regular fa-file';
				}
				
				
                if ($handle = opendir('.')) {

                    while (false !== ($entry = readdir($handle))) {

                        if ($entry !== "." && $entry !== ".." && $entry !== "index.php") {
                            echo "<li>";
                            echo "<a href=\"$entry\" download>";
							$iconClass = fa_icon_class_for_file($entry);
                            echo '<span class="file-icon" aria-hidden="true"><i class="' . $iconClass . '"></i></span>';
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
