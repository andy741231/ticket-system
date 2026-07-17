<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts .docx (and .doc) files to PDF using LibreOffice's headless mode.
 */
class DocxToPdfConverter
{
    /** Maximum seconds to wait for a single conversion before killing it. */
    protected const TIMEOUT_SECONDS = 90;

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

        $isWindows = PHP_OS_FAMILY === 'Windows';

        // Use a unique temp directory for output so concurrent conversions don't collide.
        $tempDir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'docx2pdf_' . Str::uuid();
        if (!@mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            return null;
        }

        $soffice = $this->resolveSofficeBinary();
        if ($soffice === null) {
            \Log::error('DocxToPdfConverter: LibreOffice (soffice) binary not found. Is LibreOffice installed?');
            $this->cleanupTempDir($tempDir);
            return null;
        }

        // Use a persistent profile directory so LibreOffice doesn't re-initialize
        // its user profile on every conversion (major speedup after first run).
        $profileDir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'lo_profile';
        if (!is_dir($profileDir)) {
            @mkdir($profileDir, 0755, true);
        }

        // Build the file:// URL for the UserInstallation env var.
        $profilePath = str_replace('\\', '/', $profileDir);
        $profileUrl = 'file:///' . ltrim($profilePath, '/');

        // On Windows, escapeshellarg uses single quotes which cmd.exe doesn't
        // understand. We need double quotes for paths with spaces (e.g.
        // "C:\Program Files\LibreOffice\program\soffice.exe").
        if ($isWindows) {
            $escapedBinary = '"' . $soffice . '"';
            $escapedInput  = '"' . $absolutePath . '"';
            $escapedOutDir = '"' . $tempDir . '"';
            $escapedProfile = '"' . $profileUrl . '"';
        } else {
            $escapedBinary = escapeshellarg($soffice);
            $escapedInput  = escapeshellarg($absolutePath);
            $escapedOutDir = escapeshellarg($tempDir);
            $escapedProfile = escapeshellarg($profileUrl);
        }

        // Build the conversion command.
        $convertCmd = sprintf(
            '%s --headless --nologo --nofirststartwizard --norestore -env:UserInstallation=%s --convert-to pdf --outdir %s %s',
            $escapedBinary,
            $escapedProfile,
            $escapedOutDir,
            $escapedInput
        );

        if ($isWindows) {
            $result = $this->execWindows($convertCmd, self::TIMEOUT_SECONDS);
        } else {
            $result = $this->execUnix($convertCmd, self::TIMEOUT_SECONDS);
        }

        $output = $result['output'];
        $exitCode = $result['exitCode'];
        $timedOut = $result['timedOut'];

        if ($timedOut) {
            \Log::error('DocxToPdfConverter: soffice timed out after ' . self::TIMEOUT_SECONDS . 's. Output: ' . implode("\n", $output));
            $this->cleanupTempDir($tempDir);
            return null;
        }

        // LibreOffice names the output file after the input, with .pdf extension.
        $baseName = pathinfo($absolutePath, PATHINFO_FILENAME);
        $pdfPath = $tempDir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

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
     * Execute a command on Unix with a timeout, using proc_open.
     */
    protected function execUnix(string $command, int $timeout): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($command, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return ['output' => [], 'exitCode' => -1, 'timedOut' => false];
        }

        fclose($pipes[0]);

        $output = [];
        $startTime = time();
        $timedOut = false;

        // Read output in a non-blocking loop with timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            if (time() - $startTime >= $timeout) {
                $timedOut = true;
                // Kill the process and any children
                proc_terminate($proc, 9);
                break;
            }
            $chunk = fread($pipes[1], 4096);
            if ($chunk) $output[] = $chunk;
            $chunk = fread($pipes[2], 4096);
            if ($chunk) $output[] = $chunk;
            usleep(100000); // 100ms
        }

        // Read any remaining output
        $remaining = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        if ($remaining) $output[] = $remaining;

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_get_status($proc)['exitcode'];
        proc_close($proc);

        // Normalize output into lines
        $lines = [];
        foreach ($output as $chunk) {
            $lines = array_merge($lines, explode("\n", trim($chunk)));
        }
        $lines = array_filter($lines, fn($l) => $l !== '');

        return ['output' => $lines, 'exitCode' => $exitCode, 'timedOut' => $timedOut];
    }

    /**
     * Execute a command on Windows with a timeout.
     *
     * Uses a wrapper script approach: runs soffice via start /B and polls
     * for the output file, killing the process if it exceeds the timeout.
     */
    protected function execWindows(string $command, int $timeout): array
    {
        // On Windows, exec() can hang indefinitely if LibreOffice tries to
        // interact with the desktop. We use a different approach:
        // 1. Run the command with proc_open
        // 2. Poll for completion with a timeout
        // 3. Kill the process tree if it exceeds the timeout

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // On Windows, wrap with cmd /c to ensure proper process handling
        $fullCommand = 'cmd /c ' . $command;

        $proc = proc_open($fullCommand, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            return ['output' => [], 'exitCode' => -1, 'timedOut' => false];
        }

        fclose($pipes[0]);

        $output = [];
        $startTime = time();
        $timedOut = false;

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            if (time() - $startTime >= $timeout) {
                $timedOut = true;
                // Kill the process — on Windows, proc_terminate with SIGKILL
                proc_terminate($proc, 9);
                // Also try to kill any lingering soffice processes spawned
                @shell_exec('taskkill /F /IM soffice.exe /T 2>nul');
                break;
            }
            $chunk = fread($pipes[1], 4096);
            if ($chunk) $output[] = $chunk;
            $chunk = fread($pipes[2], 4096);
            if ($chunk) $output[] = $chunk;
            usleep(100000); // 100ms
        }

        // Read any remaining output
        $remaining = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        if ($remaining) $output[] = $remaining;

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_get_status($proc)['exitcode'];
        proc_close($proc);

        // Normalize output into lines
        $lines = [];
        foreach ($output as $chunk) {
            $lines = array_merge($lines, explode("\n", trim($chunk)));
        }
        $lines = array_filter($lines, fn($l) => $l !== '');

        return ['output' => $lines, 'exitCode' => $exitCode, 'timedOut' => $timedOut];
    }

    /**
     * Resolve the soffice binary path across platforms.
     */
    protected function resolveSofficeBinary(): ?string
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        // 1. Check PATH
        if ($isWindows) {
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
                '/Applications/LibreOffice.app/Contents/MacOS/soffice',
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
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanupTempDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
