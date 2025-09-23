import puppeteer from 'puppeteer';
import { promises as fs } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export async function captureScreenshot(url, outputPath, options = {}) {
    const {
        width = 1920,
        height = 1080,
        fullPage = true,
        waitFor = 2000,
        quality = 90,
        format = 'png'
    } = options;

    let browser;
    let lastPageError = null;
    let failedRequests = [];

    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-first-run',
                '--no-zygote',
                '--single-process'
            ]
        });

        const page = await browser.newPage();

        // Collect page-level errors and failed requests silently
        page.on('pageerror', (err) => {
            lastPageError = err?.stack || err?.message || String(err);
        });

        page.on('requestfailed', (req) => {
            failedRequests.push({
                url: req.url(),
                method: req.method(),
                failure: req.failure()?.errorText,
                resourceType: req.resourceType(),
                status: req.response()?.status()
            });
        });

        // Set viewport
        await page.setViewport({ width, height });

        // Set user agent to avoid bot detection
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        // Get browser info for debugging
        let browserInfo = {};
        try {
            const browserVersion = await browser.version();
            // @ts-ignore access to internal process
            const execPath = browser.process?.() ? browser.process().spawnfile : 'n/a';
            browserInfo = { browserVersion, execPath };
        } catch (e) {
            // Silently collect browser info
            browserInfo = { error: 'Unable to get browser info' };
        }

        // Navigate to the URL
        await page.goto(url, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });

        // Wait for additional loading time
        await new Promise(resolve => setTimeout(resolve, waitFor));

        // Ensure output directory exists
        const outputDir = path.dirname(outputPath);
        await fs.mkdir(outputDir, { recursive: true });

        // Take screenshot
        const screenshotOptions = {
            path: outputPath,
            fullPage: fullPage,
            type: format,
            quality: format === 'jpeg' || format === 'webp' ? quality : undefined
        };

        await page.screenshot(screenshotOptions);

        // Get the actual dimensions of the captured page
        const dimensions = await page.evaluate(() => {
            return {
                width: Math.max(
                    document.body.scrollWidth,
                    document.documentElement.scrollWidth,
                    document.body.offsetWidth,
                    document.documentElement.offsetWidth,
                    document.documentElement.clientWidth
                ),
                height: Math.max(
                    document.body.scrollHeight,
                    document.documentElement.scrollHeight,
                    document.body.offsetHeight,
                    document.documentElement.offsetHeight,
                    document.documentElement.clientHeight
                )
            };
        });

        // Get file stats
        const stats = await fs.stat(outputPath);

        return {
            success: true,
            path: outputPath,
            width: dimensions.width,
            height: dimensions.height,
            size: stats.size,
            format,
            url,
            browserInfo,
            failedRequests,
            failedRequestsCount: failedRequests.length,
            lastPageError: lastPageError || null
        };

    } catch (error) {
        return {
            success: false,
            error: error && (error.message || String(error)),
            errorStack: error && error.stack ? String(error.stack) : undefined,
            url,
            lastPageError,
            failedRequests
        };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// If run directly (not imported as module)
if (process.argv[1] === fileURLToPath(import.meta.url)) {
    const args = process.argv.slice(2);

    if (args.length < 2) {
        console.error('Usage: node screenshot-capture.js <url> <output-path> [width] [height] [fullPage] [waitFor]');
        process.exit(1);
    }

    const [url, outputPath, width, height, fullPage, waitFor] = args;

    const options = {};
    if (width) options.width = parseInt(width);
    if (height) options.height = parseInt(height);
    if (fullPage !== undefined) options.fullPage = fullPage === 'true';
    if (waitFor) options.waitFor = parseInt(waitFor);

    // Only output the final JSON result, no other logs
    captureScreenshot(url, outputPath, options)
        .then(result => {
            // Only output the JSON, no other logs
            process.stdout.write(JSON.stringify(result) + '\n');
            process.exit(result.success ? 0 : 1);
        })
        .catch(error => {
            const errorResult = {
                success: false,
                error: error.message,
                errorStack: error.stack,
                url: url
            };
            process.stderr.write(JSON.stringify(errorResult) + '\n');
            process.exit(1);
        });
}