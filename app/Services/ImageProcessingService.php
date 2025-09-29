<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\Newsletter\Campaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ImageProcessingService
{
    protected ?string $computedPath = null;

    protected string $nodeBinary = 'node';

    public function __construct()
    {
        $this->computedPath = $this->buildExecutablePath();
        $this->nodeBinary = env('PUPPETEER_NODE_BINARY') ?? env('NODE_BINARY', 'node');

        if ($this->computedPath) {
            putenv('PATH=' . $this->computedPath);
            $_ENV['PATH'] = $this->computedPath;
            $_SERVER['PATH'] = $this->computedPath;
        }
    }

    /**
     * Process a URL to generate a screenshot
     */
    public function processUrl(Ticket $ticket, string $url): TicketImage
    {
        $ticketImage = TicketImage::create([
            'ticket_id' => $ticket->id,
            'source_type' => 'url',
            'source_value' => $url,
            'size' => 0,
            'image_path' => '', // Will be set after processing
            'status' => 'processing',
        ]);

        // Process asynchronously
        $this->captureScreenshot($ticketImage, $url);

        return $ticketImage;
    }

    /**
     * Process an uploaded file to convert it to an image
     */
    public function processFile(Ticket $ticket, UploadedFile $file): TicketImage
    {
        $ticketImage = TicketImage::create([
            'ticket_id' => $ticket->id,
            'source_type' => 'file',
            'source_value' => $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'size' => 0,
            'image_path' => '', // Will be set after processing
            'status' => 'processing',
        ]);

        try {
            $this->convertFileToImage($ticketImage, $file);
        } catch (\Exception $e) {
            $ticketImage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $ticketImage;
    }
    /**
     * Capture screenshot using Puppeteer
     */
    protected function captureScreenshot(TicketImage $ticketImage, string $url): void
    {
        try {
            $filename = 'screenshot_' . $ticketImage->id . '_' . time() . '.png';
            $imagePath = "tickets/{$ticketImage->ticket_id}/images/{$filename}";
            $fullPath = storage_path("app/public/{$imagePath}");

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Set custom temp directory
            $tempDir = base_path('public/storage/temp/' . $ticketImage->ticket_id);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            if (!is_writable($tempDir)) {
                @chmod($tempDir, 0755);
            }

            // Puppeteer cache dir (to avoid using system profile cache)
            $puppeteerCacheDir = base_path('public/storage/temp/puppeteer');
            if (!is_dir($puppeteerCacheDir)) {
                mkdir($puppeteerCacheDir, 0755, true);
            }
            if (!is_writable($puppeteerCacheDir)) {
                @chmod($puppeteerCacheDir, 0755);
            }

            // Ensure PHP itself and the child process use our temp dir
            putenv('TEMP=' . $tempDir);
            putenv('TMP=' . $tempDir);
            putenv('TMPDIR=' . $tempDir);
            $_ENV['TEMP'] = $tempDir;
            $_ENV['TMP'] = $tempDir;
            $_ENV['TMPDIR'] = $tempDir;
            $_SERVER['TEMP'] = $tempDir;
            $_SERVER['TMP'] = $tempDir;
            $_SERVER['TMPDIR'] = $tempDir;
            // Also set Puppeteer-specific cache directory
            putenv('PUPPETEER_CACHE_DIR=' . $puppeteerCacheDir);
            $_ENV['PUPPETEER_CACHE_DIR'] = $puppeteerCacheDir;
            $_SERVER['PUPPETEER_CACHE_DIR'] = $puppeteerCacheDir;

            // Optionally set proxy from .env
            $proxy = env('PUPPETEER_PROXY') ?? env('HTTPS_PROXY') ?? env('HTTP_PROXY');
            $noProxy = env('NO_PROXY');
            if ($proxy) {
                putenv('PUPPETEER_PROXY=' . $proxy);
                $_ENV['PUPPETEER_PROXY'] = $proxy;
                $_SERVER['PUPPETEER_PROXY'] = $proxy;
                // Also set standard proxy vars so any dependencies respect it
                putenv('HTTPS_PROXY=' . $proxy);
                putenv('HTTP_PROXY=' . $proxy);
                $_ENV['HTTPS_PROXY'] = $proxy;
                $_ENV['HTTP_PROXY'] = $proxy;
                $_SERVER['HTTPS_PROXY'] = $proxy;
                $_SERVER['HTTP_PROXY'] = $proxy;
            }
            if ($noProxy) {
                putenv('NO_PROXY=' . $noProxy);
                $_ENV['NO_PROXY'] = $noProxy;
                $_SERVER['NO_PROXY'] = $noProxy;
            }

            // Set environment variables for the spawned process as well
            $env = array_merge($_SERVER, [
                'TEMP' => $tempDir,
                'TMP' => $tempDir,
                'TMPDIR' => $tempDir,
                'PUPPETEER_CACHE_DIR' => $puppeteerCacheDir,
            ]);
            if ($proxy) {
                $env['PUPPETEER_PROXY'] = $proxy;
                $env['HTTPS_PROXY'] = $proxy;
            }
            if ($noProxy) {
                $env['NO_PROXY'] = $noProxy;
            }
            if ($this->computedPath) {
                $env['PATH'] = $this->computedPath;
            }

            // Run Puppeteer screenshot script
            $scriptPath = base_path('scripts/screenshot-capture.js');
            $process = new Process([
                $this->nodeBinary,
                $scriptPath,
                $url,
                $fullPath,
                '1920',
                '1080',
                'true',
                '3000'
            ], null, $env);

            $process->setTimeout(60); // 60 seconds timeout
            $process->run();

            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();

            if (!$process->isSuccessful()) {
                // Log detailed outputs for diagnostics
                Log::error('Screenshot process failed', [
                    'ticket_image_id' => $ticketImage->id,
                    'stdout' => mb_substr($stdout, 0, 4000),
                    'stderr' => mb_substr($stderr, 0, 4000),
                    'exit_code' => $process->getExitCode(),
                ]);
                throw new ProcessFailedException($process);
            }

            // The Node script now outputs clean JSON, so we can parse it directly
            $parsed = json_decode(trim($stdout), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse JSON output from screenshot script', [
                    'error' => json_last_error_msg(),
                    'stdout' => $stdout,
                    'stderr' => $stderr
                ]);
                throw new \Exception('Failed to parse screenshot output: ' . json_last_error_msg());
            }
            
            // Log any issues that occurred during capture
            if (!empty($parsed['lastPageError'])) {
                Log::warning('Page error during screenshot', [
                    'error' => $parsed['lastPageError']
                ]);
            }
            
            if (!empty($parsed['failedRequests'])) {
                Log::debug('Failed requests during screenshot', [
                    'count' => count($parsed['failedRequests']),
                    'requests' => $parsed['failedRequests']
                ]);
            }

            if ($parsed && !empty($parsed['success'])) {
                $fileSize = filesize($fullPath);

                $existingMetadata = $ticketImage->metadata ?? [];

                $ticketImage->update([
                    'image_path' => $imagePath,
                    'mime_type' => 'image/png',
                    'size' => $fileSize,
                    'width' => $parsed['width'] ?? null,
                    'height' => $parsed['height'] ?? null,
                    'status' => 'completed',
                    'metadata' => array_merge($existingMetadata, [
                        'viewport_width' => 1920,
                        'viewport_height' => 1080,
                        'full_page' => true,
                        'captured_at' => now()->toISOString(),
                        'failed_requests' => $parsed['failedRequests'] ?? ($parsed['failedRequestsCount'] ?? 0),
                    ]),
                ]);

                Log::info("Screenshot captured successfully for ticket image {$ticketImage->id}");
            } else {
                // Log stdout/stderr for further debugging
                Log::error('Screenshot JSON parse or failure', [
                    'ticket_image_id' => $ticketImage->id,
                    'stdout' => mb_substr($stdout, 0, 4000),
                    'stderr' => mb_substr($stderr, 0, 4000),
                ]);

                $errMsg = 'Screenshot capture failed';
                if (is_array($parsed)) {
                    $errMsg = $parsed['error'] ?? $errMsg;
                    if (!empty($parsed['lastPageError'])) {
                        $errMsg .= ' | pageerror: ' . $parsed['lastPageError'];
                    }
                }
                throw new \Exception($errMsg);
            }

        } catch (\Exception $e) {
            Log::error("Screenshot capture failed for ticket image {$ticketImage->id}: " . $e->getMessage());
            
            $ticketImage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'metadata' => array_merge($ticketImage->metadata ?? [], [
                    'failed_at' => now()->toISOString(),
                ]),
            ]);
        }
    }

    public function processNewsletterCampaign(Ticket $ticket, Campaign $campaign): TicketImage
    {
        $ticketImage = TicketImage::create([
            'ticket_id' => $ticket->id,
            'source_type' => 'newsletter',
            'source_value' => (string) $campaign->id,
            'original_name' => $campaign->name,
            'size' => 0,
            'image_path' => '',
            'status' => 'processing',
            'metadata' => [
                'newsletter_campaign_id' => $campaign->id,
                'newsletter_campaign_name' => $campaign->name,
                'newsletter_subject' => $campaign->subject,
            ],
        ]);

        try {
            $html = $campaign->html_content;
            if (!$html || !is_string($html)) {
                $html = '<p>No preview content available for this campaign.</p>';
            }

            $html = $this->normalizeNewsletterHtml($html, $campaign);
            $dataUrl = $this->buildDataUrlFromHtml($html);

            $this->captureScreenshot($ticketImage, $dataUrl);
        } catch (\Throwable $e) {
            $ticketImage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'metadata' => array_merge($ticketImage->metadata ?? [], [
                    'failed_at' => now()->toISOString(),
                ]),
            ]);
        }

        return $ticketImage;
    }

    protected function normalizeNewsletterHtml(string $html, Campaign $campaign): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            $trimmed = '<p>Empty campaign content</p>';
        }

        $hasHtml = stripos($trimmed, '<html') !== false && stripos($trimmed, '<body') !== false;

        if ($hasHtml) {
            return $trimmed;
        }

        $subject = htmlspecialchars($campaign->subject ?? $campaign->name ?? 'Newsletter', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fontFamily = "'Source Sans 3', Roboto, Helvetica, Arial, sans-serif";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{$subject}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet" />
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: {$fontFamily};
            line-height: 1.6;
            background-color: #f4f4f4;
        }
        .newsletter-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="newsletter-container">
        {$trimmed}
    </div>
</body>
</html>
HTML;
    }

    protected function buildDataUrlFromHtml(string $html): string
    {
        $encoded = base64_encode($html);
        return 'data:text/html;charset=utf-8;base64,' . $encoded;
    }

    /**
     * Helper to parse JSON from output with mixed content
     */
    private function parseJsonFromOutput(string $output): ?array
    {
        $lines = preg_split("/\r?\n/", trim($output));
        
        // Try to find the last line that looks like JSON
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '') continue;
            
            // Look for JSON object or array
            if (($line[0] === '{' && substr($line, -1) === '}') || 
                ($line[0] === '[' && substr($line, -1) === ']')) {
                $parsed = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            }
        }
        return null;
    }

    /**
     * Convert a file to an image and store it
     */
    protected function convertFileToImage(TicketImage $ticketImage, UploadedFile $file): void
    {
        $filename = 'converted_' . $ticketImage->id . '_' . time();
        $imagePath = "tickets/{$ticketImage->ticket_id}/images/";
        
        // Handle different file types
        $mimeType = $file->getMimeType();
        
        if (str_starts_with($mimeType, 'image/')) {
            // Already an image, just store it
            $this->storeImageFile($ticketImage, $file, $imagePath, $filename);
        } elseif ($mimeType === 'application/pdf') {
            // Convert PDF to image
            $this->convertPdfToImage($ticketImage, $file, $imagePath, $filename);
        } else {
            // For other file types, create a placeholder or try to convert
            $this->createFilePlaceholder($ticketImage, $file, $imagePath, $filename);
        }
    }

    /**
     * Store image file directly
     */
    protected function storeImageFile(TicketImage $ticketImage, UploadedFile $file, string $imagePath, string $filename): void
    {
        try {
            // Create image manager instance
            $manager = new ImageManager(new Driver());
            
            // Use Intervention Image to process and optimize
            $image = $manager->read($file->getRealPath());
            
            // Get original dimensions
            $width = $image->width();
            $height = $image->height();
            
            // Optimize image size if too large
            if ($width > 2000 || $height > 2000) {
                $image->scale(width: 2000, height: 2000);
            }
            
            // Save as PNG for consistency
            $fullFilename = $filename . '.png';
            $fullPath = $imagePath . $fullFilename;
            
            // Save to storage
            $imageData = $image->toPng()->toString();
            Storage::disk('public')->put($fullPath, $imageData);
            
            $fileSize = strlen($imageData);
            
            $ticketImage->update([
                'image_path' => $fullPath,
                'mime_type' => 'image/png',
                'size' => $fileSize,
                'width' => $image->width(),
                'height' => $image->height(),
                'status' => 'completed',
                'metadata' => [
                    'original_mime_type' => $file->getMimeType(),
                    'original_width' => $width,
                    'original_height' => $height,
                    'processed_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            throw new \Exception("Failed to process image: " . $e->getMessage());
        }
    }

    /**
     * Convert PDF to image (first page)
     */
    protected function convertPdfToImage(TicketImage $ticketImage, UploadedFile $file, string $imagePath, string $filename): void
    {
        try {
            // For PDF conversion, we'll need ImageMagick or similar
            // This is a simplified version - you might need to install additional packages
            
            $tempPath = $file->store('temp');
            $fullTempPath = Storage::path($tempPath);

            // Ensure Symfony Process uses our temp dir too
            $procTempDir = base_path('public/storage/temp/' . $ticketImage->ticket_id);
            if (!is_dir($procTempDir)) {
                mkdir($procTempDir, 0755, true);
            }
            if (!is_writable($procTempDir)) {
                @chmod($procTempDir, 0755);
            }
            putenv('TEMP=' . $procTempDir);
            putenv('TMP=' . $procTempDir);
            putenv('TMPDIR=' . $procTempDir);
            $_ENV['TEMP'] = $procTempDir;
            $_ENV['TMP'] = $procTempDir;
            $_ENV['TMPDIR'] = $procTempDir;
            $_SERVER['TEMP'] = $procTempDir;
            $_SERVER['TMP'] = $procTempDir;
            $_SERVER['TMPDIR'] = $procTempDir;
            $env = array_merge($_SERVER, [
                'TEMP' => $procTempDir,
                'TMP' => $procTempDir,
                'TMPDIR' => $procTempDir,
            ]);
            
            if ($this->computedPath) {
                $env['PATH'] = $this->computedPath;
            }

            // Try to use ImageMagick to convert PDF to PNG
            $outputFilename = $filename . '.png';
            $outputPath = $imagePath . $outputFilename;
            $fullOutputPath = storage_path("app/public/{$outputPath}");
            
            // Ensure directory exists
            $directory = dirname($fullOutputPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Use ImageMagick v7 "magick" command (convert is deprecated)
            $process = new Process([
                'magick',
                '-density', '150',
                $fullTempPath . '[0]', // First page only
                '-quality', '90',
                '-background', 'white',
                '-alpha', 'remove',
                '-alpha', 'off',
                $fullOutputPath
            ], null, $env);

            $process->setTimeout(30);
            $process->run();

            $outputExists = file_exists($fullOutputPath) && filesize($fullOutputPath) > 0;

            if (!$process->isSuccessful() || !$outputExists) {
                Log::warning('PDF to image conversion failed', [
                    'ticket_image_id' => $ticketImage->id,
                    'exit_code' => $process->getExitCode(),
                    'error_output' => mb_substr($process->getErrorOutput(), 0, 4000),
                    'output_exists' => $outputExists,
                ]);
                Storage::delete($tempPath);
                // Fallback: create a placeholder
                $this->createFilePlaceholder($ticketImage, $file, $imagePath, $filename);
                return;
            }

            $fileSize = filesize($fullOutputPath);
            $manager = new ImageManager(new Driver());
            $image = $manager->read($fullOutputPath);
            
            $ticketImage->update([
                'image_path' => $outputPath,
                'mime_type' => 'image/png',
                'size' => $fileSize,
                'width' => $image->width(),
                'height' => $image->height(),
                'status' => 'completed',
                'metadata' => [
                    'original_mime_type' => $file->getMimeType(),
                    'conversion_method' => 'imagemagick',
                    'processed_at' => now()->toISOString(),
                    'page_count' => 1,
                ],
            ]);
            
            // Clean up temp file
            Storage::delete($tempPath);

        } catch (\Exception $e) {
            // Fallback to placeholder
            if (!empty($tempPath)) {
                Storage::delete($tempPath);
            }
            $this->createFilePlaceholder($ticketImage, $file, $imagePath, $filename);
        }
    }

    /**
{{ ... }}
     */
    protected function createFilePlaceholder(TicketImage $ticketImage, UploadedFile $file, string $imagePath, string $filename): void
    {
        try {
            // Create image manager instance
            $manager = new ImageManager(new Driver());
            
            // Create a simple placeholder image with file info
            $image = $manager->create(800, 600)->fill('#f8f9fa');
            
            // Add file information text (simplified - you might want to use a proper font)
            $fileName = $file->getClientOriginalName();
            $fileSize = $this->formatFileSize($file->getSize());
            $mimeType = $file->getMimeType();
            
            // This is a basic implementation - you might want to enhance it
            $outputFilename = $filename . '.png';
            $outputPath = $imagePath . $outputFilename;
            
            // Save placeholder
            $imageData = $image->toPng()->toString();
            Storage::disk('public')->put($outputPath, $imageData);
            
            $ticketImage->update([
                'image_path' => $outputPath,
                'mime_type' => 'image/png',
                'size' => strlen($imageData),
                'width' => 800,
                'height' => 600,
                'status' => 'completed',
                'metadata' => [
                    'original_mime_type' => $file->getMimeType(),
                    'original_filename' => $fileName,
                    'original_size' => $file->getSize(),
                    'is_placeholder' => true,
                    'processed_at' => now()->toISOString(),
                ],
            ]);
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to create file placeholder: " . $e->getMessage());
        }
    }

    /**
     * Format file size for display
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    protected function buildExecutablePath(): ?string
    {
        $sources = [
            env('MAGICK_PATH'),
            env('PATH'),
            getenv('PATH') ?: null,
            $_SERVER['PATH'] ?? null,
            $_ENV['PATH'] ?? null,
        ];

        $segments = [];

        foreach ($sources as $source) {
            if (!$source) {
                continue;
            }

            foreach (explode(PATH_SEPARATOR, $source) as $segment) {
                $segment = trim($segment, "\"' ");

                if ($segment === '') {
                    continue;
                }

                if (!in_array($segment, $segments, true)) {
                    $segments[] = $segment;
                }
            }
        }

        if (empty($segments)) {
            return null;
        }

        return implode(PATH_SEPARATOR, $segments);
    }
}
