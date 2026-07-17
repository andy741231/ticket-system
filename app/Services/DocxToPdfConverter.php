<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts .docx (and .doc) files to PDF using LibreOffice's headless mode.
 */
class DocxToPdfConverter
{
    /**
     * Convert a stored document file to PDF and store the result on the given disk.
     *
     * @param  string $disk        Storage disk where the source file lives.
     * @param  string $filePath    Relative path on the disk to the source file.
     * @return string|null         Relative path on the disk to the generated PDF, or null on failure.
     */
    public function convert(string $disk, string $filePath): ?string
    {
        $absolutePath = Storage::disk($disk)->path($filePath);

        if (!file_exists($absolutePath)) {
            return null;
        }

        // Use a unique temp directory so concurrent conversions don't collide.
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $tempDir = sys_get_temp_dir() . '/docx2pdf_' . Str::uuid();
        if (!@mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            return null;
        }

        $soffice = $this->resolveSofficeBinary();
        if ($soffice === null) {
            \Log::error('DocxToPdfConverter: LibreOffice (soffice) binary not found. Is LibreOffice installed?');
            $this->cleanupTempDir($tempDir);
            return null;
        }

        // Build the shell command. soffice needs a writable user profile dir.
        $userProfile = $tempDir . '/profile';
        $escapedBinary = escapeshellarg($soffice);
        $escapedInput  = escapeshellarg($absolutePath);
        $escapedOutDir = escapeshellarg($tempDir);

        // The UserInstallation URL must be a proper file:// URL.
        // On Windows, paths like C:\temp\profile need to become file:///C:/temp/profile
        $profileUrl = 'file://' . str_replace('\\', '/', $userProfile);
        if ($isWindows && preg_match('/^file:\/\/\/[A-Za-z]:/', $profileUrl) === 0) {
            // Add leading slash for drive-letter paths: file://C:/... → file:///C:/...
            $profileUrl = 'file:///' . ltrim(str_replace('\\', '/', $userProfile), '/');
        }

        $command = sprintf(
            '%s --headless --nologo --nofirststartwizard --norestore -env:UserInstallation=%s --convert-to pdf --outdir %s %s 2>&1',
            $escapedBinary,
            escapeshellarg($profileUrl),
            $escapedOutDir,
            $escapedInput
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        // LibreOffice names the output file after the input, with .pdf extension.
        $baseName = pathinfo($absolutePath, PATHINFO_FILENAME);
        $pdfPath = $tempDir . '/' . $baseName . '.pdf';

        if (!file_exists($pdfPath)) {
            \Log::error('DocxToPdfConverter: soffice produced no PDF. Exit code: ' . $exitCode . '. Output: ' . implode("\n", $output));
            $this->cleanupTempDir($tempDir);
            return null;
        }

        // Store the PDF on the same disk under a documents/previews path.
        $pdfContents = file_get_contents($pdfPath);
        $this->cleanupTempDir($tempDir);

        if ($pdfContents === false) {
            return null;
        }

        $relativePath = 'documents/previews/' . Str::uuid() . '.pdf';
        if (!Storage::disk($disk)->put($relativePath, $pdfContents)) {
            return null;
        }

        return $relativePath;
    }

    /**
     * Resolve the soffice binary path across platforms.
     */
    protected function resolveSofficeBinary(): ?string
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        // 1. Check PATH
        if ($isWindows) {
            // `where` is the Windows equivalent of `command -v`
            $pathResult = trim((string) shell_exec('where soffice 2>nul'));
            if ($pathResult !== '' && is_executable($pathResult)) {
                return $pathResult;
            }
        } else {
            $pathResult = trim((string) shell_exec('command -v soffice 2>/dev/null'));
            if ($pathResult !== '' && is_executable($pathResult)) {
                return $pathResult;
            }
        }

        // 2. Platform-specific known locations
        $candidates = $isWindows
            ? [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            ]
            : [
                // macOS .app
                '/Applications/LibreOffice.app/Contents/MacOS/soffice',
                // Common Linux locations
                '/usr/bin/soffice',
                '/usr/bin/libreoffice',
                '/usr/local/bin/soffice',
            ];

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function cleanupTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->cleanupTempDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
