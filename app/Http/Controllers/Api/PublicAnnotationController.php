<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\TicketImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PublicAnnotationController extends Controller
{
    /**
     * Get all annotations for a ticket image (public access)
     */
    public function index(TicketImage $image, Request $request): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        $annotations = $image->annotations()
            ->with(['user', 'reviewer', 'comments.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $annotations
        ]);
    }

    /**
     * Create a new annotation (public access)
     */
    public function store(Request $request, TicketImage $image): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:point,rectangle,circle,arrow,freehand,text',
            'coordinates' => 'required|array',
            'style' => 'nullable|array',
            'content' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $annotation = new Annotation([
            'ticket_image_id' => $image->id,
            'type' => $request->type,
            'coordinates' => $request->coordinates,
            'style' => $request->style ?? [],
            'content' => $request->content,
            'status' => 'pending',
            'user_id' => null, // Anonymous public user
            'created_by_public' => true,
            'public_user_info' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now()->toISOString(),
            ]
        ]);

        $annotation->save();

        // Load relationships for response
        $annotation->load(['user', 'reviewer', 'comments.user']);

        return response()->json([
            'success' => true,
            'data' => $annotation
        ], 201);
    }

    /**
     * Delete an annotation (public access - only if created by public user)
     */
    public function destroy(TicketImage $image, Annotation $annotation, Request $request): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Check if annotation belongs to this image
        if ($annotation->ticket_image_id !== $image->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found for this image'
            ], 404);
        }

        // Only allow deletion of public annotations (created by anonymous users)
        if (!($annotation->created_by_public ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete authenticated user annotations'
            ], 403);
        }

        $annotation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Annotation deleted successfully'
        ]);
    }

    /**
     * Add a comment to an annotation (public access)
     */
    public function storeComment(Request $request, TicketImage $image, Annotation $annotation): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Check if annotation belongs to this image
        if ($annotation->ticket_image_id !== $image->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found for this image'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'public_name' => 'required|string|max:100',
            'public_email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment = new AnnotationComment([
            'annotation_id' => $annotation->id,
            'content' => $request->content,
            'user_id' => null, // Anonymous public user
            'created_by_public' => true,
            'public_user_info' => [
                'name' => $request->public_name,
                'email' => $request->public_email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now()->toISOString(),
            ]
        ]);

        $comment->save();

        // Load relationships for response
        $comment->load(['user']);

        return response()->json([
            'success' => true,
            'data' => $comment
        ], 201);
    }

    /**
     * Add image-level comment (public access)
     */
    public function storeImageComment(Request $request, TicketImage $image): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'public_name' => 'required|string|max:100',
            'public_email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get or create root annotation for image-level comments
        $root = $image->annotations()->where('type', 'root_comment')->first();
        if (!$root) {
            $root = Annotation::create([
                'ticket_image_id' => $image->id,
                'user_id' => null,
                'type' => 'root_comment',
                'coordinates' => json_encode([]),
                'content' => null,
                'style' => json_encode([]),
                'status' => 'approved',
                'created_by_public' => true,
                'public_user_info' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now()->toISOString(),
                ]
            ]);
        }

        $comment = new AnnotationComment([
            'annotation_id' => $root->id,
            'content' => $request->content,
            'user_id' => null,
            'created_by_public' => true,
            'public_user_info' => [
                'name' => $request->public_name,
                'email' => $request->public_email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now()->toISOString(),
            ]
        ]);

        $comment->save();

        return response()->json([
            'success' => true,
            'data' => $comment
        ], 201);
    }

    /**
     * Validate a public token
     */
    private function validatePublicToken(TicketImage $image, ?string $token): bool
    {
        if (!$token) {
            return false;
        }

        $expectedToken = hash('sha256', $image->id . $image->created_at . config('app.key'));
        return hash_equals($expectedToken, $token);
    }
}
