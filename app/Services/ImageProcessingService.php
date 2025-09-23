<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketImage;
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

            // Run Puppeteer screenshot script
            $scriptPath = base_path('scripts/screenshot-capture.js');
            $process = new Process([
                'node',
                $scriptPath,
                $url,
                $fullPath,
                '1920',
                '1080',
                'true',
                '3000'
            ]);

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
                
                $ticketImage->update([
                    'image_path' => $imagePath,
                    'mime_type' => 'image/png',
                    'size' => $fileSize,
                    'width' => $parsed['width'] ?? null,
                    'height' => $parsed['height'] ?? null,
                    'status' => 'completed',
                    'metadata' => [
                        'viewport_width' => 1920,
                        'viewport_height' => 1080,
                        'full_page' => true,
                        'captured_at' => now()->toISOString(),
                        'failed_requests' => $parsed['failedRequests'] ?? ($parsed['failedRequestsCount'] ?? 0),
                    ],
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
            ]);
        }
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
            
            // Try to use ImageMagick to convert PDF to PNG
            $outputFilename = $filename . '.png';
            $outputPath = $imagePath . $outputFilename;
            $fullOutputPath = storage_path("app/public/{$outputPath}");
            
            // Ensure directory exists
            $directory = dirname($fullOutputPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Use ImageMagick convert command
            $process = new Process([
                'convert',
                $fullTempPath . '[0]', // First page only
                '-density', '150',
                '-quality', '90',
                $fullOutputPath
            ]);
            
            $process->setTimeout(30);
            $process->run();
            
            if (!$process->isSuccessful()) {
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
                ],
            ]);
            
            // Clean up temp file
            Storage::delete($tempPath);
            
        } catch (\Exception $e) {
            // Fallback to placeholder
            $this->createFilePlaceholder($ticketImage, $file, $imagePath, $filename);
        }
    }

    /**
     * Create a placeholder image for unsupported file types
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
}
