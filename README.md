# Simple PHP File Lister

A lightweight, no-configuration PHP file directory lister with a clean, modern interface and file-type icons.

Just drop the `index.php` file into any directory on a PHP-enabled server and it will automatically list all files in that folder. No setup, no config files, no dependencies.

Perfect for sharing downloads, documents, or quick internal file access.

![Screen Shot](screenshot.png)

---

## Features

- ✅ **Zero configuration** — works immediately
- 📁 **Automatically lists files** in the current directory
- 🚫 **Excludes** `.`, `..`, and the `index.php` file itself
- 🎨 **Modern, responsive design** using pure HTML & CSS
- 🖼 **File-type icons & color coding** powered by Font Awesome
- 📄 **Click-to-download** file links
- ⚡ **Single self-contained file**

---

## Requirements

- PHP 7.0 or later
- A web server capable of running PHP (Apache, Nginx, etc.)

---

## Installation

1. Copy the `index.php` file into the directory you want to list.
2. Upload the directory to your PHP-enabled web server.
3. Visit the directory in your browser.

That’s it - the file list will render automatically.

---

## How It Works

- Uses PHP’s `opendir()` and `readdir()` functions to scan the current directory.
- Outputs each file as a styled clickable link with an icon based on file type.
- Files are displayed using a card-based layout for clarity and readability.
- All styling and logic are embedded directly in the file - no external dependencies except Font Awesome CDN.

---

## Customisation

You can easily tailor the lister by editing the `index.php` file:

- **Title & subtitle**  
  Change the `$title`, `$subtitle` and `$footer` variables at the top of the file.

- **Styling**  
  Modify the CSS variables in the `<style>` block to adjust colors, spacing, or fonts.

- **Excluded files**  
  Add additional filenames to the PHP exclusion check if needed.

---

## Notes

- Files are listed in the order returned by the filesystem.
- No sorting or pagination is included by default (by design, to keep it simple).
- Intended for trusted environments — no authentication is included.

---

## License

Free to use, modify, and redistribute.

---

**Simple PHP File Lister**  
© Andrew Samuel 2026