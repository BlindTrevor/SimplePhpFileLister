# Simple PHP File Lister

A lightweight, no-configuration PHP file directory lister with a clean, modern interface and file-type icons.

Just drop the `index.php` file into any directory on a PHP-enabled server and it will automatically list all files in that folder. No setup, no config files, no dependencies.

Perfect for sharing downloads, documents, or quick internal file access.

![Screen Shot](screenshot.png)

---

## Features

- ✅ **Zero configuration** — works immediately
- 📁 **Automatically lists files and subdirectories** with breadcrumb navigation
- 📄 **Pagination** — configurable threshold for large directories (default: 25 items per page)
- ☑️ **Multi-select with batch actions** — select multiple files/folders and download as ZIP or delete them all at once
- 🔒 **Security-hardened** — protects against path traversal, code execution, and other vulnerabilities
- 🚫 **Smart exclusions** — hides hidden files (starting with `.`), system files, and dangerous executables
- 🎨 **Modern, responsive design** — works beautifully on desktop, tablet, and mobile
- 🖼 **File-type icons & color coding** powered by Font Awesome
- 👁️ **Hover previews** — see thumbnails of images, videos, audio, and PDFs before downloading
- ✏️ **Rename files and folders** — easily rename items directly from the web interface (optional, configurable)
- 🗑️ **Delete files and folders** — remove items with confirmation dialog (optional, configurable)
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

- **Pagination threshold**  
  Change the `$paginationThreshold` variable at the top of the file to control when pagination appears. Default is 25 items (files + folders combined). Set to a higher number to show more items per page, or lower to paginate sooner.
  ```php
  $paginationThreshold = 25; // Show 25 items per page
  ```

- **Rename functionality**  
  Enable or disable the rename feature by changing the `$enableRename` variable at the top of the file:
  ```php
  $enableRename = true;  // Set to false to disable rename functionality
  ```
  When enabled, a rename button (pencil icon) appears when hovering over files and folders, allowing you to rename them directly from the interface.

- **Delete functionality**  
  Enable or disable the delete feature by changing the `$enableDelete` variable at the top of the file:
  ```php
  $enableDelete = true;  // Set to false to disable delete functionality
  ```
  When enabled, a delete button (trash icon) appears when hovering over files and folders, allowing you to delete them after confirmation. **Warning: Deleted files cannot be recovered.**

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

## Rename Feature

The rename feature allows you to rename files and folders directly from the web interface.

### How to Use

1. Hover over any file or folder in the list to reveal the rename button (pencil icon)
2. Click the rename button to open the rename dialog
3. Enter the new name for the file or folder
4. Click "Rename" to confirm, or "Cancel" to abort

### Security & Validation

The rename feature includes robust security measures:

- **Path traversal prevention** — Cannot use `/`, `\`, or null bytes in names
- **Extension protection** — Prevents renaming files to dangerous extensions (`.php`, `.exe`, etc.)
- **Hidden file protection** — Cannot rename to hidden files (starting with `.`)
- **System file protection** — Cannot rename `index.php` or hidden files
- **Duplicate detection** — Prevents overwriting existing files or folders
- **Input sanitization** — All inputs are validated and sanitized

### Configuration

The rename feature can be enabled or disabled via the `$enableRename` configuration variable:

```php
$enableRename = true;  // Set to false to disable rename functionality
```

When disabled:
- Rename buttons are hidden from the UI
- Backend rename endpoint returns 403 Forbidden if accessed

### Error Handling

The rename dialog displays helpful error messages for common issues:
- "A file or folder with this name already exists" — when the target name conflicts
- "Invalid file name" — when the name contains invalid characters
- "Cannot rename to this file type" — when trying to rename to a blocked extension
- "Failed to rename item" — when the filesystem operation fails

---

## Delete Feature

The delete feature allows you to permanently delete files and folders directly from the web interface.

### How to Use

1. Hover over any file or folder in the list to reveal the delete button (trash icon)
2. Click the delete button to open the confirmation dialog
3. Review the warning message about permanent deletion
4. Click "Delete" to confirm, or "Cancel" to abort

### Security & Validation

The delete feature includes robust security measures:

- **Path traversal prevention** — Validates all paths to prevent unauthorized access
- **System file protection** — Cannot delete `index.php` or hidden files (starting with `.`)
- **Recursive deletion** — Automatically handles folder deletion with all contents
- **Confirmation required** — Always prompts for confirmation before deleting
- **Input validation** — All paths are validated before any deletion occurs

### Configuration

The delete feature can be enabled or disabled via the `$enableDelete` configuration variable:

```php
$enableDelete = true;  // Set to false to disable delete functionality
```

When disabled:
- Delete buttons are hidden from the UI
- Backend delete endpoint returns 403 Forbidden if accessed

### Important Warnings

⚠️ **IRREVERSIBLE ACTION**: Deleted files and folders cannot be recovered. They are permanently removed from the filesystem.

⚠️ **NO TRASH/RECYCLE BIN**: Unlike operating systems with a trash bin, deletions are immediate and permanent.

⚠️ **FOLDER DELETION**: When deleting a folder, all its contents (files and subfolders) are also permanently deleted.

### Best Practices

1. **Enable only when needed** — Keep delete functionality disabled unless actively required
2. **Use server backups** — Ensure regular backups are in place before enabling delete
3. **Limit access** — Use web server authentication (`.htaccess`, HTTP Basic Auth) to restrict access
4. **Test carefully** — Test in a safe environment before using in production
5. **Review permissions** — Ensure filesystem permissions match your security requirements

### Error Handling

The delete dialog displays helpful error messages:
- "File or folder not found" — when the target doesn't exist
- "Cannot delete this item" — when trying to delete protected files
- "Delete functionality is disabled" — when the feature is turned off
- "Failed to delete item" — when the filesystem operation fails

---

## Multi-Select Feature

The multi-select feature allows you to perform batch operations on multiple files and folders at once.

### How to Use

1. **Select items** — Click the checkboxes next to files or folders you want to select
2. **Select All** — Use the "Select All" checkbox to quickly select all items in the current directory
3. **View selection** — The selected count is displayed in the action bar (e.g., "2 selected")
4. **Batch actions** — Choose from the available batch operations:
   - **Download as ZIP** — Download all selected items as a single ZIP file
   - **Delete Selected** — Delete all selected items at once (only visible if deletion is enabled)

### Features

- **Individual selection** — Select specific files and folders using checkboxes
- **Select All/Deselect All** — Toggle selection of all items with a single click
- **Visual feedback** — Selected items are clearly indicated with checked boxes
- **Selection count** — Always know how many items are currently selected
- **Mixed state** — The "Select All" checkbox shows an indeterminate state when some (but not all) items are selected
- **Responsive design** — Works seamlessly on desktop, tablet, and mobile devices

### Batch Download

When you click "Download as ZIP":
- All selected files and folders are packaged into a single ZIP file
- The ZIP file is named `selected_files.zip`
- Folders are included with their complete directory structure
- Download opens in a new tab to avoid interrupting your browsing
- Blocked file extensions are automatically excluded for security

### Batch Delete

When you click "Delete Selected" (if deletion is enabled):
- A confirmation dialog appears showing the number of items to be deleted
- All selected files and folders are permanently deleted after confirmation
- Folders are deleted recursively with all their contents
- Protected files (index.php, hidden files) are skipped automatically
- Error messages indicate which items couldn't be deleted, if any
- Page reloads automatically after successful deletion

### Security

Batch operations maintain the same security standards as individual operations:
- **Path traversal protection** — All paths are validated before processing
- **Permission checks** — System files and protected items are automatically skipped
- **Extension blocking** — Dangerous file types are never included in ZIP downloads
- **Input validation** — All selections are validated on the server side
- **Safe deletion** — Recursive deletion includes safety checks at every level

### Notes

- The multi-select controls only appear when there are items to select
- Batch operations work across pagination — only visible items can be selected
- Selecting items does not interfere with single-item operations (rename, delete, download)
- The action bar appears/disappears automatically based on selection state
- On mobile devices, buttons stack vertically for better usability

---

## Notes

- Files and directories are sorted naturally (case-insensitive) for better organization
- Pagination automatically appears when the number of items exceeds the configured threshold (default: 25)
- Pagination preserves the current directory path when navigating between pages
- Multi-select controls automatically appear when there are files or folders to select
- No authentication is built-in — use web server authentication (`.htaccess`, HTTP Basic Auth) if needed
- Hover previews only work on desktop devices with mouse support (disabled on touch-only devices)
- Rename and delete buttons appear on hover on desktop; always visible on mobile/touch devices
- ZIP download feature requires the ZipArchive PHP extension (enabled by default on most PHP installations)
- Preview handler is optimized for performance — it's placed at the top of the script and exits immediately
- Hidden files (starting with `.`) and dangerous executables are automatically excluded from listings
- **Delete operations are permanent** — deleted files cannot be recovered, so use this feature carefully

---

## License

Free to use, modify, and redistribute.

---

**Simple PHP File Lister**  
© Andrew Samuel 2026