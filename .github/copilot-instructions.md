# Copilot Instructions for Simple PHP File Lister

## Project Overview

Simple PHP File Lister is a lightweight, single-file PHP directory listing application with zero external dependencies (except Font Awesome CDN). The entire application is contained in `index.php` (~6000 lines) with embedded HTML, CSS, and JavaScript.

## Architecture & Design Principles

- **Single-file architecture**: All code must remain in `index.php` - no separate files
- **Zero configuration**: Application works immediately with no setup required
- **No dependencies**: No Composer, no external libraries (only Font Awesome CDN for icons)
- **Security-first**: Path traversal protection, input sanitization, CSP headers are critical
- **Self-contained**: HTML, CSS, JavaScript, and PHP all embedded in one file

## Version Management

- Version is defined in two places that must be kept in sync:
  - `define('APP_VERSION', 'X.Y.Z');` constant (line ~15)
  - `@version X.Y.Z` in the file header docblock (line ~8)
- Version follows semantic versioning (MAJOR.MINOR.PATCH)
- PATCH version is auto-incremented by GitHub Actions on main branch merges
- Never manually edit version numbers unless updating MAJOR or MINOR versions

## Code Style & Structure

### PHP Conventions
- Use strict type comparisons (`===`, `!==`) for security
- Sanitize all user inputs with `htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`
- Use `realpath()` for all file path validations to prevent directory traversal
- Configuration variables are at the top of the file (lines 20-60)
- Helper functions follow configuration section (after line 90)
- All file operations must validate paths against `$realRoot`

### Security Requirements
- **CRITICAL**: Always validate file paths with `realpath()` and check they start with `$realRoot`
- Never allow access to blocked extensions (defined in `BLOCKED_EXTENSIONS` constant)
- All output must be escaped with `htmlspecialchars()` unless it's intentional HTML
- Use cryptographically secure nonces (`random_bytes()`) for CSP inline scripts/styles
- Validate all user inputs before file system operations
- Never expose error details or file system paths to users

### Configuration
- All user-configurable settings are centralized at the top (lines 20-60)
- Use descriptive variable names with comments explaining purpose
- Boolean feature toggles follow pattern: `$enableFeatureName = true/false;`
- Include inline comments for configuration options
- Validate and sanitize configuration values in the validation section (lines 61-89)

### HTML/CSS/JavaScript Embedded Code
- HTML is embedded using PHP heredoc syntax or echo statements
- CSS is in `<style>` tags with CSP nonce attributes
- JavaScript is in `<script>` tags with CSP nonce attributes
- Keep inline styles/scripts minimal - prefer CSS classes
- Use Font Awesome icons (classes like `fa-folder`, `fa-file`)

## Testing & Validation

- No automated test suite exists - manual testing required
- Test on PHP 7.0+ (but 7.4+ recommended)
- Always test security features: path traversal attempts, blocked extensions
- Test with various file types and directory structures
- Verify responsive design on desktop, tablet, and mobile viewports

## Common Tasks

### Adding New Features
1. Add configuration option at the top if user-configurable
2. Implement feature logic in appropriate section
3. Add security validations if touching file system
4. Update README.md if feature is user-facing
5. Test security implications thoroughly

### Security Fixes
- Security is paramount - always validate against path traversal
- Use `basename()` and `realpath()` for filename/path validation
- Check file extensions against `BLOCKED_EXTENSIONS`
- Escape all output with `htmlspecialchars()`
- Test with malicious inputs: `../`, `..%2F`, symlinks, null bytes

### Modifying Configuration
- Keep configuration section organized and well-commented
- Add validation in the configuration validation section
- Update README.md if adding user-facing configuration
- Use sensible defaults that prioritize security

## File Structure

```
index.php structure:
- Lines 1-10: File header and version
- Lines 11-60: User configuration
- Lines 61-89: Configuration validation
- Lines 90-500: Helper functions
- Lines 500-1000: Request handling & security logic
- Lines 1000-6000: HTML output with embedded CSS/JavaScript
```

## Common Patterns

### Safe File Path Construction
```php
$requestedPath = $_GET['path'] ?? '';
$fullPath = realpath($realRoot . $requestedPath);
if ($fullPath === false || strpos($fullPath, $realRoot) !== 0) {
    // Invalid path - deny access
}
```

### Output Escaping
```php
echo htmlspecialchars($userInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
```

### Extension Checking
```php
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
    // Blocked extension - deny access
}
```

## What NOT to Do

- ❌ Never add external dependencies or require Composer
- ❌ Never split code into multiple files
- ❌ Never expose file system paths in error messages
- ❌ Never trust user input without validation
- ❌ Never manually edit version numbers (except MAJOR/MINOR)
- ❌ Never bypass security checks for "convenience"
- ❌ Never use loose comparisons (`==`) for security checks

## Resources

- GitHub Repository: https://github.com/BlindTrevor/SimplePhpFileLister
- README.md contains full feature list and security documentation
- Version is auto-managed by `.github/workflows/auto-version.yml`
