<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TempFile;
use App\Models\TicketFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TempFileController extends Controller
{
    private const MAX_FILE_SIZE = 10240; // 10MB

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files.*' => [
                'required',
                'file',
                'max:' . self::MAX_FILE_SIZE,
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
            ],
        ]);

        if (!$request->hasFile('files')) {
            return response()->json(['message' => 'No files were uploaded.'], 400);
        }

        $uploadedFiles = [];
        $storagePath = 'temp/' . auth()->id();

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::random(40) . '.' . $extension;
            $filePath = $file->storeAs($storagePath, $fileName, 'public');

            $temp = TempFile::create([
                'user_id' => auth()->id(),
                'file_path' => $filePath,
                'original_name' => $originalName,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            $uploadedFiles[] = [
                'id' => $temp->id,
                'original_name' => $temp->original_name,
                'url' => asset('storage/' . $temp->file_path),
                'size' => $temp->size,
                'mime_type' => $temp->mime_type,
            ];
        }

        return response()->json([
            'message' => 'Files uploaded successfully.',
            'files' => $uploadedFiles,
        ], 201);
    }

    public function destroy(TempFile $file): JsonResponse
    {
        // Ensure the current user owns the temp file
        if ($file->user_id !== auth()->id()) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        return response()->json(['message' => 'File deleted successfully.']);
    }
}
