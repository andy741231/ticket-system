<?php

namespace App\Services;

use App\Models\TempTicketImage;
use App\Models\Newsletter\Campaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class TempImageProcessingService
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
    public function processUrl(int $userId, string $url, ?string $name = null): TempTicketImage
    {
        $tempImage = TempTicketImage::create([
            'user_id' => $userId,
            'source_type' => 'url',
            'source_value' => $url,
            'name' => $name,
            'size' => 0,
            'image_path' => '', // Will be set after processing
            'status' => 'processing',
        ]);

        // Process asynchronously
        $this->captureScreenshot($tempImage, $url);

        return $tempImage;
    }

    /**
     * Process an uploaded file to convert it to an image
     */
    public function processFile(int $userId, UploadedFile $file, ?string $name = null): TempTicketImage
    {
        $tempImage = TempTicketImage::create([
            'user_id' => $userId,
            'source_type' => 'file',
            'source_value' => $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'name' => $name,
            'size' => 0,
            'image_path' => '', // Will be set after processing
            'status' => 'processing',
        ]);

        try {
            $this->convertFileToImage($tempImage, $file);
        } catch (\Exception $e) {
            $tempImage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $tempImage;
    }

    /**
     * Process a newsletter campaign to generate a screenshot
     */
    public function processNewsletterCampaign(int $userId, Campaign $campaign, ?string $name = null): TempTicketImage
    {
        $tempImage = TempTicketImage::create([
            'user_id' => $userId,
            'source_type' => 'newsletter',
            'source_value' => (string) $campaign->id,
            'original_name' => $campaign->name,
            'name' => $name,
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
            
            // Write HTML to temporary file
            $tempHtmlPath = $this->writeHtmlToTempFile($html, $tempImage->id);
            $fileUrl = 'file://' . str_replace('\\', '/', $tempHtmlPath);

            $this->captureScreenshot($tempImage, $fileUrl, $tempHtmlPath);
        } catch (\Throwable $e) {
            $tempImage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'metadata' => array_merge($tempImage->metadata ?? [], [
                    'failed_at' => now()->toISOString(),
                ]),
            ]);
        }

        return $tempImage;
    }

    /**
     * Capture screenshot using Puppeteer
     */
    protected function captureScreenshot(TempTicketImage $tempImage, string $url, ?string $tempFilePath = null): void
    {
        try {
            $filename = 'temp_screenshot_' . $tempImage->id . '_' . time() . '.png';
            $imagePath = "temp/{$tempImage->user_id}/images/{$filename}";
            $fullPath = storage_path("app/public/{$imagePath}");

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Set custom temp directory
            $tempDir = base_path('public/storage/temp/' . $tempImage->user_id);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            if (!is_writable($tempDir)) {
                @chmod($tempDir, 0755);
            }

            // Puppeteer cache dir
            $puppeteerCacheDir = storage_path('app/puppeteer');
            if (!is_dir($puppeteerCacheDir)) {
                mkdir($puppeteerCacheDir, 0755, true);
            }
            if (!is_writable($puppeteerCacheDir)) {
                @chmod($puppeteerCacheDir, 0755);
            }

            $scriptPath = base_path('scripts/screenshot-capture.js');

            $process = new Process([
                $this->nodeBinary,
                $scriptPath,
                $url,
                $fullPath,
            ], base_path(), [
                'PUPPETEER_CACHE_DIR' => $puppeteerCacheDir,
                'TMPDIR' => $tempDir,
                'TEMP' => $tempDir,
                'TMP' => $tempDir,
                'PATH' => $this->computedPath ?? getenv('PATH'),
            ], null, 120);

            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if (!file_exists($fullPath)) {
                throw new \Exception('Screenshot file was not created');
            }

            $fileSize = filesize($fullPath);
            [$width, $height] = getimagesize($fullPath);

            $tempImage->update([
                'image_path' => $imagePath,
                'size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'status' => 'completed',
                'metadata' => array_merge($tempImage->metadata ?? [], [
                    'captured_at' => now()->toISOString(),
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Temp screenshot capture failed', [
                'temp_image_id' => $tempImage->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            $tempImage->update([
                'status' => 'failed',
                'error_message' => 'Screenshot capture failed: ' . $e->getMessage(),
                'metadata' => array_merge($tempImage->metadata ?? [], [
                    'failed_at' => now()->toISOString(),
                ]),
            ]);
        } finally {
            // Clean up temporary HTML file if it was created
            if ($tempFilePath && file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }
    }

    /**
     * Convert uploaded file to image
     */
    protected function convertFileToImage(TempTicketImage $tempImage, UploadedFile $file): void
    {
        try {
            $filename = 'temp_' . $tempImage->id . '_' . time() . '.png';
            $imagePath = "temp/{$tempImage->user_id}/images/{$filename}";
            $fullPath = storage_path("app/public/{$imagePath}");

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());
            $image->toPng()->save($fullPath);

            $fileSize = filesize($fullPath);
            [$width, $height] = getimagesize($fullPath);

            $tempImage->update([
                'image_path' => $imagePath,
                'size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'status' => 'completed',
            ]);

        } catch (\Exception $e) {
            Log::error('Temp file conversion failed', [
                'temp_image_id' => $tempImage->id,
                'error' => $e->getMessage(),
            ]);

            $tempImage->update([
                'status' => 'failed',
                'error_message' => 'File conversion failed: ' . $e->getMessage(),
            ]);
        }
    }

    protected function normalizeNewsletterHtml(string $html, Campaign $campaign): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            $trimmed = '<p>Empty campaign content</p>';
        }

        if (!preg_match('/<html[\s>]/i', $trimmed)) {
            $trimmed = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>{$campaign->name}</title></head><body>{$trimmed}</body></html>";
        }

        return $trimmed;
    }

    protected function writeHtmlToTempFile(string $html, int $tempImageId): string
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/temp_newsletter_' . $tempImageId . '_' . time() . '.html';
        file_put_contents($tempFile, $html);
        return $tempFile;
    }

    protected function buildExecutablePath(): ?string
    {
        $paths = [
            '/usr/local/bin',
            '/usr/bin',
            '/opt/homebrew/bin',
            getenv('PATH'),
        ];

        return implode(':', array_filter($paths));
    }
}
