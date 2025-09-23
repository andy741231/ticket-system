<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Services\ImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TicketImageController extends Controller
{
    protected ImageProcessingService $imageProcessingService;

    public function __construct(ImageProcessingService $imageProcessingService)
    {
        $this->imageProcessingService = $imageProcessingService;
    }

    /**
     * Get all images for a ticket
     */
    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $images = $ticket->images()
            ->with(['annotations.user', 'annotations.comments.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'source_type' => $image->source_type,
                    'source_value' => $image->source_value,
                    'image_url' => $image->image_url,
                    'original_name' => $image->original_name,
                    'width' => $image->width,
                    'height' => $image->height,
                    'status' => $image->status,
                    'error_message' => $image->error_message,
                    'metadata' => $image->metadata,
                    'created_at' => $image->created_at,
                    'annotations' => $image->annotations,
                ];
            })
        ]);
    }

    /**
     * Create image from URL
     */
    public function storeFromUrl(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticketImage = $this->imageProcessingService->processUrl($ticket, $request->url);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ticketImage->id,
                    'source_type' => $ticketImage->source_type,
                    'source_value' => $ticketImage->source_value,
                    'status' => $ticketImage->status,
                    'created_at' => $ticketImage->created_at,
                ],
                'message' => 'Screenshot capture started. Please check back in a few moments.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create image from uploaded file
     */
    public function storeFromFile(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticketImage = $this->imageProcessingService->processFile($ticket, $request->file('file'));

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ticketImage->id,
                    'source_type' => $ticketImage->source_type,
                    'source_value' => $ticketImage->source_value,
                    'original_name' => $ticketImage->original_name,
                    'status' => $ticketImage->status,
                    'created_at' => $ticketImage->created_at,
                ],
                'message' => 'File processing started.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific image with annotations
     */
    public function show(Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        $ticketImage->load(['annotations.user', 'annotations.comments.user']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ticketImage->id,
                'source_type' => $ticketImage->source_type,
                'source_value' => $ticketImage->source_value,
                'image_url' => $ticketImage->image_url,
                'original_name' => $ticketImage->original_name,
                'width' => $ticketImage->width,
                'height' => $ticketImage->height,
                'status' => $ticketImage->status,
                'error_message' => $ticketImage->error_message,
                'metadata' => $ticketImage->metadata,
                'created_at' => $ticketImage->created_at,
                'annotations' => $ticketImage->annotations,
            ]
        ]);
    }

    /**
     * Delete an image
     */
    public function destroy(Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        $this->authorize('update', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        try {
            $ticketImage->delete();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check processing status of an image
     */
    public function status(Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ticketImage->id,
                'status' => $ticketImage->status,
                'error_message' => $ticketImage->error_message,
                'image_url' => $ticketImage->isCompleted() ? $ticketImage->image_url : null,
                'width' => $ticketImage->width,
                'height' => $ticketImage->height,
                'updated_at' => $ticketImage->updated_at,
            ]
        ]);
    }
}
