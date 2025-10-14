# Temp Directory Fix for Screenshot Capture

## Problem
When adding proof (screenshots) in the development server, the following error occurred:
```
Screenshot capture failed: A temporary file could not be opened to write the process output: 
fopen(C:\Windows\TEMP\sf_proc_00.out.lock): Failed to open stream: Permission denied
```

## Root Cause
The Symfony Process component was attempting to use a Windows temp path (`C:\Windows\TEMP`) on a macOS development environment. This happened because:

1. `sys_get_temp_dir()` in `TempImageProcessingService` was returning an incorrect cross-platform path
2. The Process environment variables weren't being set early enough to override system defaults

## Solution Applied

### Files Modified
- `/app/Services/TempImageProcessingService.php`

### Changes Made

#### 1. Fixed `writeHtmlToTempFile()` Method
**Before:**
```php
protected function writeHtmlToTempFile(string $html, int $tempImageId): string
{
    $tempDir = sys_get_temp_dir(); // Could return Windows path
    $tempFile = $tempDir . '/temp_newsletter_' . $tempImageId . '_' . time() . '.html';
    file_put_contents($tempFile, $html);
    return $tempFile;
}
```

**After:**
```php
protected function writeHtmlToTempFile(string $html, int $tempImageId): string
{
    // Use our own temp directory instead of system temp to avoid cross-platform issues
    $tempDir = base_path('public/storage/temp/' . floor($tempImageId / 100));
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $tempFile = $tempDir . '/temp_newsletter_' . $tempImageId . '_' . time() . '.html';
    file_put_contents($tempFile, $html);
    return $tempFile;
}
```

#### 2. Enhanced Process Environment Setup
Added explicit environment variable setting before Process instantiation:

```php
// Set environment variables to ensure process uses our temp directory
putenv('TEMP=' . $tempDir);
putenv('TMP=' . $tempDir);
putenv('TMPDIR=' . $tempDir);
$_ENV['TEMP'] = $tempDir;
$_ENV['TMP'] = $tempDir;
$_ENV['TMPDIR'] = $tempDir;
$_SERVER['TEMP'] = $tempDir;
$_SERVER['TMP'] = $tempDir;
$_SERVER['TMPDIR'] = $tempDir;

$env = array_merge($_SERVER, [
    'PUPPETEER_CACHE_DIR' => $puppeteerCacheDir,
    'TMPDIR' => $tempDir,
    'TEMP' => $tempDir,
    'TMP' => $tempDir,
    'PATH' => $this->computedPath ?? getenv('PATH'),
]);

$process = new Process([...], base_path(), $env, null, 120);
```

## Benefits
1. **Cross-platform compatibility**: Uses application-controlled temp directory
2. **Consistent behavior**: Same approach as `ImageProcessingService`
3. **Proper permissions**: Ensures writable directory with correct permissions
4. **No system dependencies**: Doesn't rely on system temp directory configuration

## Testing
After applying this fix, test by:
1. Creating a new ticket
2. Adding proof via URL screenshot
3. Adding proof via newsletter campaign
4. Verify screenshots are captured successfully without permission errors

## Related Files
- `/app/Services/ImageProcessingService.php` - Already uses this pattern correctly
- `/scripts/screenshot-capture.js` - Puppeteer script that respects TEMP env vars
