# Simple PHP File Lister

A lightweight, zero-configuration PHP directory lister with a modern interface. Just drop `index.php` into any directory on a PHP-enabled server—it works immediately with no setup required.

<img width="842" height="942" alt="image" src="https://github.com/user-attachments/assets/a88b8572-6204-4d8b-9e4d-effaa9b9cf37" />

## Features

- ✅ **Zero configuration** — works immediately
- 🔐 **Optional authentication** — user management with per-user permissions (NEW in v2.0)
- 📁 **Directory navigation** with breadcrumbs and pagination
- ☑️ **Multi-select** — batch download or delete multiple files
- 🔒 **Security-hardened** — protects against path traversal and code execution
- 🎨 **5 themes** — Purple, Blue, Green, Dark, Light (user-switchable)
- 🖼 **File previews** — hover to preview images/videos
- 🎵 **Built-in players** — play audio and video files in-browser
- 📤 **File upload** — drag-and-drop or button upload (optional)
- ✏️ **File management** — rename, delete, create folders (optional)
- 📦 **ZIP downloads** — download directories as ZIP files
- 📱 **Responsive design** — works on desktop, tablet, and mobile
- ⚡ **Single file** — no external dependencies except Font Awesome CDN

## Quick Start

**Requirements:**
- PHP 7.0+ (7.4+ recommended)
- Web server with PHP support
- Optional: ZipArchive extension for ZIP downloads

**Installation:**
1. Download `index.php` from [releases](https://github.com/BlindTrevor/SimplePhpFileLister/releases)
2. Copy it into the directory you want to list
3. Visit the directory in your browser

That's it! The file list renders automatically.

## Customization

All settings are in the **CONFIGURATION** section at the top of `index.php` (lines 18-60). Open the file in a text editor to customize:

**Display Settings:**
```php
$title = "Simple PHP File Lister";
$subtitle = "The Easy Way To List Files";
$footer = "Made with ❤️ by Blind Trevor";
```

**Feature Toggles:**
```php
$enableRename = false;              // Rename files/folders
$enableDelete = false;              // Delete files/folders
$enableUpload = true;               // Upload files
$enableCreateDirectory = true;      // Create folders
$enableDownloadAll = true;          // "Download All as ZIP" button
$enableIndividualDownload = true;   // Individual file downloads
```

**Themes:**
```php
$defaultTheme = 'purple';           // purple, blue, green, dark, light
$allowThemeChange = true;           // Let users change themes
```

**Pagination:**
```php
$defaultPaginationAmount = 30;                 // Items per page (5, 10, 20, 30, 50, 'all')
$enablePaginationAmountSelector = true;        // Show pagination dropdown
```

**Upload Settings:**
```php
$uploadMaxFileSize = 10 * 1024 * 1024;        // Max file size (10 MB)
$uploadMaxTotalSize = 50 * 1024 * 1024;       // Max batch size (50 MB)
$uploadAllowedExtensions = [];                 // [] = all except blocked
```

**Advanced:**
```php
$includeHiddenFiles = false;        // Show hidden files (starting with .)
$zipCompressionLevel = 6;           // ZIP compression (0-9)
$showFileSize = true;               // Display file sizes
$showFolderFileCount = true;        // Display folder/file counts
```

**Blocked Extensions:**
Edit the `BLOCKED_EXTENSIONS` constant to control which file types are hidden and blocked from download/upload (e.g., `.php`, `.exe`, `.sh`).

## Authentication (NEW in v2.0)

SimplePhpFileLister now supports optional user authentication with per-user permissions. The system operates in **standalone mode by default**—authentication is only enabled when a users file is present.

### Enabling Authentication

1. Create a file named `SPFL-Users.json` in the same directory as `index.php`
2. The file should contain JSON with user definitions (see example below)
3. When the file exists, login will be required to access the file lister

**Quick Start:** Use the provided `SPFL-Users.json.example` file as a template. It includes three pre-configured users with default passwords:
- **admin** / password: `admin` (full administrator access)
- **user** / password: `user` (view and download only)
- **editor** / password: `editor` (all file operations except user management)

⚠️ **Security Warning:** Change these default passwords immediately in production environments!

### Users File Format

Create `SPFL-Users.json` with the following JSON structure:

```json
{
  "users": [
    {
      "username": "admin",
      "password": "$2y$10$...", 
      "admin": true,
      "permissions": []
    },
    {
      "username": "viewer",
      "password": "$2y$10$...",
      "admin": false,
      "permissions": ["view", "download"]
    },
    {
      "username": "editor",
      "password": "$2y$10$...",
      "admin": false,
      "permissions": ["view", "download", "upload", "rename", "delete", "create_directory"]
    }
  ]
}
```

**Password Hashing:** Passwords must be bcrypt hashes. Generate them with:
```php
php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
```

**Available Permissions:**
- `view` — View file listings
- `download` — Download files
- `upload` — Upload files
- `delete` — Delete files and directories
- `rename` — Rename files and directories
- `create_directory` — Create new directories

**Admin Users:** Users with `"admin": true` have all permissions and can manage other users through the UI.

### Authentication Settings

Configure authentication in `index.php`:

```php
$usersFilePath = './SPFL-Users.json';      // Path to users file
$sessionTimeout = 3600;                // Session timeout in seconds (1 hour)
$enableReadOnlyMode = false;           // Allow unauthenticated users to view files read-only
```

### Read-Only Mode

Enable `$enableReadOnlyMode = true` to allow unauthenticated users to view and download files without logging in, while restricting modification operations (upload, delete, rename) to authenticated users only.

### User Management

Admin users can manage users through the **User Management** button (floating button in the bottom-right corner):
- Add new users with custom permissions
- Edit existing users (change permissions, reset passwords)
- Delete users (cannot delete yourself)

### Security Notes

- **Session Security:** Uses secure session settings (httponly, secure cookies when HTTPS, SameSite strict)
- **Password Security:** Passwords are stored as bcrypt hashes (never plain text)
- **Session Timeout:** Configurable automatic logout after inactivity
- **Permission Enforcement:** All actions verify user permissions server-side
- **Admin Protection:** Cannot delete your own admin account

### Disabling Authentication

To disable authentication, simply remove or rename the `SPFL-Users.json` file. The application will immediately return to standalone mode with all features accessible.

## Security

SimplePhpFileLister prioritizes security:

- **Optional Authentication** — User login with bcrypt password hashing and per-user permissions
- **Session Security** — Secure session settings, automatic timeout, session regeneration
- **Path Traversal Protection** — Uses `realpath()` validation, prevents `../` attacks
- **Code Execution Prevention** — Blocks dangerous file extensions (`.php`, `.exe`, `.sh`, etc.)
- **Input Sanitization** — All user inputs escaped with `htmlspecialchars()`
- **Security Headers** — CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- **Hidden Files** — Automatically excluded from listings (starting with `.`)
- **Secure Downloads** — Content-Disposition headers, MIME type whitelisting
- **No Database** — Single auditable file reduces attack surface

## Key Features

**Multi-Select:**
- Select multiple files/folders with checkboxes
- Batch download as ZIP or batch delete
- Displays selection count and total size

**File Upload:**
- Drag-and-drop files anywhere on the page
- Multi-file upload with progress feedback
- Automatic duplicate handling
- Size limits and file type restrictions

**Media Players:**
- **Audio** — MP3, WAV, OGG, M4A, FLAC, AAC
  - Visual progress bars for audio playback
  - <img width="335" height="301" alt="image" src="https://github.com/user-attachments/assets/678d1780-d6c2-4f3b-8592-26929d982d91" />
- **Video** — MP4, WebM, OGV (fullscreen lightbox)
- Hover over files to reveal play buttons
  - <img width="814" height="510" alt="image" src="https://github.com/user-attachments/assets/b470f8b5-122c-42ee-917e-1f9d3da9ae47" />

**File Management:**
- Rename files/folders (configurable)
- Delete files/folders (configurable, permanent)
- Create new folders (configurable)
- All operations include path validation and security checks

## Themes

5 built-in themes available:
- **Purple** (default) — Vibrant purple-magenta gradient
- **Blue** — Fresh cyan-blue gradient
- **Green** — Natural teal-green gradient
- **Dark** — Sleek dark mode
- **Light** — Minimal light theme

Users can switch themes via the floating palette icon (when `$allowThemeChange = true`). Preferences are saved in browser localStorage.

<img width="521" height="679" alt="image" src="https://github.com/user-attachments/assets/6ef3275f-e373-4b5f-b239-72b1d36c0d19" />

## Notes

- Built-in authentication available (optional, v2.0+)—alternatively use web server auth (`.htaccess`, HTTP Basic Auth)
- Hover previews work on desktop only (disabled on touch devices)
- Delete operations are permanent—no trash/recycle bin
- Upload requires proper PHP configuration (`upload_max_filesize`, `post_max_size`)
- Version info displayed in footer (auto-updated by GitHub Actions)

## Version Management

This project uses automated semantic versioning:
- **Version location:** `APP_VERSION` constant in `index.php` and footer
- **Auto-increment:** PATCH version bumped on each merge to `main`
- **Manual updates:** Edit `APP_VERSION` for MAJOR/MINOR changes
- **Releases:** Auto-created on [releases page](https://github.com/BlindTrevor/SimplePhpFileLister/releases)
- **Skip versioning:** Include `[skip-version]` in commit message

## Updating

To update to a newer version:
1. Download new `index.php` from [releases](https://github.com/BlindTrevor/SimplePhpFileLister/releases)
2. Replace existing file
3. Re-apply your configuration settings (document them first!)

## License

Free to use, modify, and redistribute.

---

**Simple PHP File Lister** • © Andrew Samuel 2026
