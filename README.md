# Simple PHP File Lister

A lightweight, no-configuration PHP file directory lister with a clean, modern interface and file-type icons.

Just drop the `index.php` file into any directory on a PHP-enabled server and it will automatically list all files in that folder. No setup, no config files, no dependencies.

Perfect for sharing downloads, documents, or quick internal file access.

![Screen Shot](screenshot.png)

![alt text](image.png)

---

## Features

- ✅ **Zero configuration** — works immediately
- 📁 **Automatically lists files and subdirectories** with breadcrumb navigation
- 🔒 **Security-hardened** — protects against path traversal, code execution, and other vulnerabilities
- 🚫 **Smart exclusions** — hides hidden files (starting with `.`), system files, and dangerous executables
- 🎨 **Modern, responsive design** — works beautifully on desktop, tablet, and mobile
- 🖼 **File-type icons & color coding** powered by Font Awesome
- 👁️ **Hover previews** — see thumbnails of images, videos, audio, and PDFs before downloading
- 📥 **Secure downloads** — individual file downloads with proper content-type headers
- 📦 **Download All as ZIP** — bundle entire directories into a single ZIP file
- 📊 **File statistics** — displays folder/file counts and total size
- 📏 **Human-readable file sizes** — automatically formats bytes to KB, MB, GB, etc.
- ⚡ **Single self-contained file** — no external dependencies except Font Awesome CDN

---

## Security

SimplePhpFileLister is designed with security as a top priority. Here's why you can trust it in production environments:

### Path Traversal Protection
- Uses `realpath()` to resolve and validate all file paths
- Strictly enforces access within the configured root directory
- Prevents `../` directory traversal attacks and symlink exploits
- Validates paths with `DIRECTORY_SEPARATOR` suffix to prevent edge-case bypasses

### Code Execution Prevention
- Blocks download of dangerous file extensions (`.php`, `.phar`, `.sh`, `.exe`, `.bat`, etc.)
- Prevents direct execution of server-side scripts through the file lister
- Hides these files from directory listings entirely

### Input Sanitization
- All user inputs are properly escaped using `htmlspecialchars()` with `ENT_QUOTES | ENT_SUBSTITUTE`
- File paths are validated before any file system operations
- Download filenames are sanitized to prevent header injection attacks

### Security Headers
- **Content Security Policy (CSP)** — prevents XSS attacks with strict script/style policies
- **X-Content-Type-Options: nosniff** — prevents MIME type sniffing
- **X-Frame-Options: DENY** — prevents clickjacking attacks
- **Referrer-Policy: no-referrer** — prevents referrer information leakage
- **Permissions-Policy** — restricts access to sensitive browser features

### Privacy & Information Disclosure
- Hidden files (starting with `.`) are automatically excluded from listings
- The `index.php` file itself is never shown or downloadable
- Symlinks are ignored to prevent unintended access
- No directory listing is exposed for invalid paths

### Secure Download & Preview Handlers
- Downloads use `Content-Disposition: attachment` to force save-as dialog
- Preview handler only allows whitelisted MIME types (images, videos, audio, PDF)
- Streaming uses `fpassthru()` to efficiently handle large files without loading into memory
- Temporary files (ZIP downloads) are securely cleaned up after use
- Cryptographically secure nonces (`random_bytes()`) for CSP inline scripts/styles

### Additional Safeguards
- Natural case-insensitive sorting prevents directory structure leakage patterns
- File operations fail safely without exposing error details
- No database or persistent storage reduces attack surface
- All PHP code is contained in a single auditable file

---

## Requirements

- PHP 7.0 or later (PHP 7.4+ recommended)
- A web server capable of running PHP (Apache, Nginx, etc.)
- Optional: ZipArchive PHP extension for "Download All as ZIP" feature (typically included in standard PHP installations)

---

## Installation

1. Copy the `index.php` file into the directory you want to list.
2. Upload the directory to your PHP-enabled web server.
3. Visit the directory in your browser.

That’s it - the file list will render automatically.

---

## How It Works

- Uses PHP's `opendir()` and `readdir()` functions to scan the current directory
- Validates all paths using `realpath()` to prevent directory traversal attacks
- Supports subdirectory navigation with breadcrumb trails for easy navigation
- Files are naturally sorted (case-insensitive) for better organization
- Individual file downloads are handled through a secure download handler
- Preview functionality loads images, videos, and audio files on hover (desktop only)
- "Download All as ZIP" feature recursively bundles directory contents
- All styling and logic are embedded directly in the file — only Font Awesome is loaded from CDN
- Responsive CSS adapts the layout for desktop, tablet, and mobile screens
- JavaScript provides smooth loading overlays and preview tooltips

---

## Customization

You can easily tailor the lister by editing the `index.php` file:

- **Title, subtitle & footer**  
  Change the `$title`, `$subtitle`, and `$footer` variables at the top of the file.

- **Styling**  
  Modify the CSS variables in the `<style>` block (in the `:root` selector) to adjust colors, spacing, or fonts:
  - `--bg` — Background gradient
  - `--card` — Card background color
  - `--accent` — Primary accent color
  - `--text` — Main text color
  - `--muted` — Secondary text color

- **Blocked file extensions**  
  Edit the `BLOCKED_EXTENSIONS` constant (near the top of the file, after the preview handler) to add or remove file types that should be hidden and blocked from download.

- **Preview file types**  
  Modify the `getPreviewableFileTypes()` function and the MIME type arrays in both the fast-path preview handler and the `getPreviewMimeType()` function to support additional preview formats.

- **Root directory**  
  By default, files are listed from the directory where `index.php` resides. To change this, modify the `$realRoot` variable near the top of the file:
  ```php
  $realRoot = rtrim(realpath('.'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  ```

---

## Notes

- Files and directories are sorted naturally (case-insensitive) for better organization
- No pagination is included by default (by design, to keep it simple)
- No authentication is built-in — use web server authentication (`.htaccess`, HTTP Basic Auth) if needed
- Hover previews only work on desktop devices with mouse support (disabled on touch-only devices)
- ZIP download feature requires the ZipArchive PHP extension (enabled by default on most PHP installations)
- Preview handler is optimized for performance — it's placed at the top of the script and exits immediately
- Hidden files (starting with `.`) and dangerous executables are automatically excluded from listings

---

## License

Free to use, modify, and redistribute.

---

**Simple PHP File Lister**  
© Andrew Samuel 2026