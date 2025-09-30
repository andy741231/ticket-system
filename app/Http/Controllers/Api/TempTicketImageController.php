<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TempTicketImage;
use App\Models\Newsletter\Campaign;
use App\Services\TempImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TempTicketImageController extends Controller
{
    protected TempImageProcessingService $imageProcessingService;

    public function __construct(TempImageProcessingService $imageProcessingService)
    {
        $this->imageProcessingService = $imageProcessingService;
    }

    /**
     * Get all temp images for the current user
     */
    public function index(): JsonResponse
    {
        $images = TempTicketImage::where('user_id', auth()->id())
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
                    'name' => $image->name,
                    'width' => $image->width,
                    'height' => $image->height,
                    'status' => $image->status,
                    'error_message' => $image->error_message,
                    'metadata' => $image->metadata,
                    'file_size' => $image->file_size,
                    'created_at' => $image->created_at,
                ];
            })
        ]);
    }

    /**
     * Create temp image from URL
     */
    public function storeFromUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tempImage = $this->imageProcessingService->processUrl(
                auth()->id(),
                $request->url,
                $request->input('name')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tempImage->id,
                    'source_type' => $tempImage->source_type,
                    'source_value' => $tempImage->source_value,
                    'status' => $tempImage->status,
                    'created_at' => $tempImage->created_at,
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
     * Create temp image from a newsletter campaign draft
     */
    public function storeFromNewsletter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'newsletter_campaign_id' => 'required|integer|exists:newsletter_campaigns,id',
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $campaign = Campaign::query()
                ->select(['id', 'name', 'html_content', 'status', 'subject'])
                ->findOrFail($validator->validated()['newsletter_campaign_id']);

            if ($campaign->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft campaigns can be captured.',
                ], 422);
            }

            $tempImage = $this->imageProcessingService->processNewsletterCampaign(
                auth()->id(),
                $campaign,
                $request->input('name')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tempImage->id,
                    'source_type' => $tempImage->source_type,
                    'source_value' => $tempImage->source_value,
                    'original_name' => $tempImage->original_name,
                    'status' => $tempImage->status,
                    'created_at' => $tempImage->created_at,
                ],
                'message' => 'Newsletter capture started. The preview will appear once ready.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process newsletter campaign: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create temp image from uploaded file
     */
    public function storeFromFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tempImage = $this->imageProcessingService->processFile(
                auth()->id(),
                $request->file('file'),
                $request->input('name')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tempImage->id,
                    'source_type' => $tempImage->source_type,
                    'source_value' => $tempImage->source_value,
                    'original_name' => $tempImage->original_name,
                    'status' => $tempImage->status,
                    'created_at' => $tempImage->created_at,
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
     * Get specific temp image
     */
    public function show(TempTicketImage $tempImage): JsonResponse
    {
        // Ensure the current user owns the temp image
        if ($tempImage->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tempImage->id,
                'source_type' => $tempImage->source_type,
                'source_value' => $tempImage->source_value,
                'image_url' => $tempImage->image_url,
                'original_name' => $tempImage->original_name,
                'name' => $tempImage->name,
                'width' => $tempImage->width,
                'height' => $tempImage->height,
                'status' => $tempImage->status,
                'error_message' => $tempImage->error_message,
                'metadata' => $tempImage->metadata,
                'file_size' => $tempImage->file_size,
                'created_at' => $tempImage->created_at,
            ]
        ]);
    }

    /**
     * Delete a temp image
     */
    public function destroy(TempTicketImage $tempImage): JsonResponse
    {
        // Ensure the current user owns the temp image
        if ($tempImage->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }

        try {
            $tempImage->delete();

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
     * Check processing status of a temp image
     */
    public function status(TempTicketImage $tempImage): JsonResponse
    {
        // Ensure the current user owns the temp image
        if ($tempImage->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tempImage->id,
                'status' => $tempImage->status,
                'error_message' => $tempImage->error_message,
                'image_url' => $tempImage->isCompleted() ? $tempImage->image_url : null,
                'width' => $tempImage->width,
                'height' => $tempImage->height,
                'updated_at' => $tempImage->updated_at,
            ]
        ]);
    }
}
