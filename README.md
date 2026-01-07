# Simple PHP File Lister

A lightweight, no-configuration PHP file directory lister.

Just drop the `index.php` file into any directory on a PHP-enabled server and it will automatically list all files in that folder with a clean, modern interface. No setup, no config files, no dependencies.

Perfect for sharing downloads, band parts, documents, or quick internal file access.

## Features

-   ✅ Zero configuration -- works immediately\
-   📁 Automatically lists files in the current directory\
-   🚫 Excludes `.`, `..`, and the `index.php` file itself\
-   🎨 Modern, responsive design using pure HTML & CSS\
-   📄 Click-to-download file links\
-   ⚡ Single self-contained file

## Requirements

-   PHP 7.0 or later\
-   A web server capable of running PHP (Apache, Nginx, etc.)

## Installation

1.  Copy the `index.php` file into the directory you want to list.
2.  Upload the directory to your PHP-enabled web server.
3.  Visit the directory in your browser.

That's it --- the file list will render automatically.

## How It Works

-   Uses PHP's `opendir()` and `readdir()` functions to scan the current
    directory.
-   Outputs each file as a styled clickable link.
-   Files are displayed using a simple card-based layout for clarity and
    readability.
-   All styling is embedded directly in the file, so there are no
    external assets.

## Customisation

You can easily tailor the lister by editing the `index.php` file:

-   **Title & subtitle**\
    Change the `<h1>` and subtitle text to suit your project.

-   **Styling**\
    Modify the CSS variables at the top of the `<style>` block to adjust
    colours, spacing, or fonts.

-   **Excluded files**\
    Add additional filenames to the PHP exclusion check if needed.

## Notes

-   Files are listed in the order returned by the filesystem.
-   No sorting or icons by file type are included by default (by design,
    to keep it simple).
-   Intended for trusted environments --- no authentication is included.

## Licence

Free to use, modify, and redistribute.

------------------------------------------------------------------------

**Simple PHP File Lister**\
© Andrew Samuel 2026
