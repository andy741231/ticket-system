<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketFileController extends Controller
{
    use AuthorizesRequests;
    /**
     * Maximum file size in kilobytes.
     */
    private const MAX_FILE_SIZE = 10240; // 10MB

    /**
     * Allowed file mime types.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword', // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.ms-excel', // .xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'text/plain',
        'text/csv',
    ];

    /**
     * Upload files for a ticket.
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        // Ensure user has access to view the ticket
        $this->authorize('view', $ticket);
        
        $request->validate([
            'files.*' => [
                'required',
                'file',
                'max:' . self::MAX_FILE_SIZE,
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
            ],
        ]);

        if (!$request->hasFile('files')) {
            return response()->json([
                'message' => 'No files were uploaded.',
            ], 400);
        }

        $uploadedFiles = [];
        $storagePath = 'tickets/' . $ticket->id;

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::random(40) . '.' . $extension;
            $filePath = $file->storeAs($storagePath, $fileName, 'public');

            $ticketFile = TicketFile::create([
                'ticket_id' => $ticket->id,
                'file_path' => $filePath,
                'original_name' => $originalName,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            $uploadedFiles[] = [
                'id' => $ticketFile->id,
                'original_name' => $ticketFile->original_name,
                'url' => $ticketFile->url,
                'size' => $ticketFile->size,
                'mime_type' => $ticketFile->mime_type,
            ];
        }

        return response()->json([
            'message' => 'Files uploaded successfully.',
            'files' => $uploadedFiles,
        ], 201);
    }

    /**
     * Delete a file from a ticket.
     */
    public function destroy(Ticket $ticket, TicketFile $file): JsonResponse
    {
        // Ensure user has access to view the ticket
        $this->authorize('view', $ticket);
        
        // Verify the file belongs to the ticket
        if ($file->ticket_id !== $ticket->id) {
            return response()->json([
                'message' => 'The specified file does not belong to this ticket.',
            ], 404);
        }

        // Delete the file from storage
        Storage::disk('public')->delete($file->file_path);

        // Delete the database record
        $file->delete();

        return response()->json([
            'message' => 'File deleted successfully.',
        ]);
    }
}
