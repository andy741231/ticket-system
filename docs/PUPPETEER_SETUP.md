# Puppeteer Setup and Configuration

## Cache Directory Location

Puppeteer browser binaries are stored in:
```
storage/app/puppeteer/
```

This location is:
- **Private** (not publicly accessible via web)
- **Persistent** (not cleared with temporary files)
- **Separate** (isolated from user-uploaded content)

## Installation

To install or update Chrome browser for Puppeteer:

```bash
PUPPETEER_CACHE_DIR=/path/to/project/storage/app/puppeteer npx puppeteer browsers install chrome
```

Or from the project root:

```bash
PUPPETEER_CACHE_DIR=$(pwd)/storage/app/puppeteer npx puppeteer browsers install chrome
```

## How It Works

### Screenshot Storage vs Browser Cache

**Browser Cache** (`storage/app/puppeteer/`):
- Contains Chrome/Chromium browser binaries
- Puppeteer dependencies
- ~165MB per browser version
- Persistent across requests

**Screenshot Output** (separate locations):
- Ticket images: `storage/app/public/tickets/{id}/images/`
- Temp images: `storage/app/public/temp/{userId}/images/`

### Services Using Puppeteer

1. **`ImageProcessingService`** - Handles ticket-attached proof images
2. **`TempImageProcessingService`** - Handles temporary proof images during ticket creation

Both services set the `PUPPETEER_CACHE_DIR` environment variable to point to `storage/app/puppeteer/`.

## Troubleshooting

### Error: "Could not find Chrome"

If you see this error:
```
Could not find Chrome (ver. X.X.X). This can occur if either
1. you did not perform an installation before running the script
2. your cache path is incorrectly configured
```

**Solution:**
```bash
cd /path/to/project
PUPPETEER_CACHE_DIR=$(pwd)/storage/app/puppeteer npx puppeteer browsers install chrome
```

### Verify Installation

Check if Chrome is installed:
```bash
ls -la storage/app/puppeteer/chrome/
```

You should see a directory like `mac_arm-140.0.7339.82` (version may vary).

### Disk Space

Each browser version takes approximately 165MB. To check current usage:
```bash
du -sh storage/app/puppeteer/
```

## Migration Notes

**Previous Location:** `storage/app/public/temp/puppeteer/` (deprecated)
**Current Location:** `storage/app/puppeteer/`

The old location was moved to:
- Improve security (not publicly accessible)
- Prevent accidental deletion during temp cleanup
- Follow Laravel storage conventions

If upgrading from the old location, remove the old cache:
```bash
rm -rf storage/app/public/temp/puppeteer/
```

Then install to the new location as shown above.
