<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\Ticket;
use App\Models\TicketImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AnnotationController extends Controller
{
    /**
     * Get all annotations for a ticket image
     */
    public function index(Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        $annotations = $ticketImage->annotations()
            ->where('type', '<>', 'root_comment')
            ->with(['user', 'reviewer', 'comments.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $annotations
        ]);
    }

    /**
     * Create a new annotation
     */
    public function store(Request $request, Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        $this->authorize('update', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:point,rectangle,circle,arrow,freehand,text',
            'coordinates' => 'required|array',
            'content' => 'nullable|string|max:1000',
            'style' => 'nullable|array',
            'style.color' => 'nullable|string|max:20',
            'style.strokeWidth' => 'nullable|numeric|min:1|max:20',
            'style.fillColor' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $annotation = Annotation::create([
                'ticket_image_id' => $ticketImage->id,
                'user_id' => Auth::id(),
                'type' => $request->type,
                'coordinates' => $request->coordinates,
                'content' => $request->content,
                'style' => $request->style ?? [
                    'color' => '#ff0000',
                    'strokeWidth' => 2,
                    'fillColor' => 'transparent'
                ],
                'status' => 'pending',
            ]);

            $annotation->load(['user', 'comments.user']);

            return response()->json([
                'success' => true,
                'data' => $annotation,
                'message' => 'Annotation created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create annotation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific annotation
     */
    public function show(Ticket $ticket, TicketImage $ticketImage, Annotation $annotation): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id || $annotation->ticket_image_id !== $ticketImage->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found'
            ], 404);
        }

        $annotation->load(['user', 'reviewer', 'comments.user']);

        return response()->json([
            'success' => true,
            'data' => $annotation
        ]);
    }

    /**
     * Update an annotation
     */
    public function update(Request $request, Ticket $ticket, TicketImage $ticketImage, Annotation $annotation): JsonResponse
    {
        $this->authorize('update', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id || $annotation->ticket_image_id !== $ticketImage->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found'
            ], 404);
        }

        // Only allow the creator or admins to update
        if ($annotation->user_id !== Auth::id() && !Auth::user()->can('tickets.ticket.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this annotation'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:point,rectangle,circle,arrow,freehand,text',
            'coordinates' => 'sometimes|array',
            'content' => 'nullable|string|max:1000',
            'style' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $annotation->update($request->only(['type', 'coordinates', 'content', 'style']));
            $annotation->load(['user', 'reviewer', 'comments.user']);

            return response()->json([
                'success' => true,
                'data' => $annotation,
                'message' => 'Annotation updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update annotation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an annotation
     */
    public function destroy(Ticket $ticket, TicketImage $ticketImage, Annotation $annotation): JsonResponse
    {
        $this->authorize('update', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id || $annotation->ticket_image_id !== $ticketImage->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found'
            ], 404);
        }

        // Only allow the creator or admins to delete
        if ($annotation->user_id !== Auth::id() && !Auth::user()->can('tickets.ticket.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this annotation'
            ], 403);
        }

        try {
            $annotation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Annotation deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete annotation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve or reject an annotation
     */
    public function updateStatus(Request $request, Ticket $ticket, TicketImage $ticketImage, Annotation $annotation): JsonResponse
    {
        // Only admins can approve/reject annotations
        if (!Auth::user()->can('tickets.ticket.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to review annotations'
            ], 403);
        }

        if ($ticketImage->ticket_id !== $ticket->id || $annotation->ticket_image_id !== $ticketImage->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'review_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $annotation->update([
                'status' => $request->status,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_notes' => $request->review_notes,
            ]);

            $annotation->load(['user', 'reviewer', 'comments.user']);

            return response()->json([
                'success' => true,
                'data' => $annotation,
                'message' => 'Annotation ' . $request->status . ' successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update annotation status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a comment to an annotation
     */
    public function storeComment(Request $request, Ticket $ticket, TicketImage $ticketImage, Annotation $annotation): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id || $annotation->ticket_image_id !== $ticketImage->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:annotation_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $comment = AnnotationComment::create([
                'annotation_id' => $annotation->id,
                'user_id' => Auth::id(),
                'content' => $request->content,
                'parent_id' => $request->parent_id,
            ]);

            $comment->load(['user', 'replies.user']);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment added successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comments for an annotation
     */
    public function getComments(Ticket $ticket, TicketImage $ticketImage, Annotation $annotation): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id || $annotation->ticket_image_id !== $ticketImage->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found'
            ], 404);
        }

        $comments = $annotation->comments()->get();

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    /**
     * Delete a comment
     */
    public function destroyComment(Request $request, Ticket $ticket, TicketImage $ticketImage, Annotation $annotation, AnnotationComment $comment): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id || 
            $annotation->ticket_image_id !== $ticketImage->id || 
            $comment->annotation_id !== $annotation->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);
        }

        // Only allow the creator or admins to delete
        if ($comment->user_id !== Auth::id() && !Auth::user()->can('tickets.ticket.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this comment'
            ], 403);
        }

        try {
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a specific annotation comment
     */
    public function updateComment(Request $request, Ticket $ticket, TicketImage $ticketImage, Annotation $annotation, AnnotationComment $comment): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id || $annotation->ticket_image_id !== $ticketImage->id || $comment->annotation_id !== $annotation->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);
        }

        // Only allow the creator or admins to update
        if ($comment->user_id !== Auth::id() && !Auth::user()->can('tickets.ticket.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this comment'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->update([
            'content' => $request->content,
        ]);

        $comment->load(['user']);

        return response()->json([
            'success' => true,
            'data' => $comment,
            'message' => 'Comment updated successfully'
        ]);
    }

    /**
     * Get or create a root annotation used to attach image-level comments.
     */
    private function getOrCreateRootCommentAnnotation(TicketImage $ticketImage): Annotation
    {
        // Use a special type to avoid rendering as a visible marker on canvas
        $root = $ticketImage->annotations()
            ->where('type', 'root_comment')
            ->first();

        if (!$root) {
            $root = Annotation::create([
                'ticket_image_id' => $ticketImage->id,
                'user_id' => Auth::id(),
                'type' => 'root_comment',
                'coordinates' => json_encode([]),
                'content' => null,
                'style' => json_encode([]),
                'status' => 'approved',
            ]);
        }

        return $root;
    }

    /**
     * List image-level comments (not associated with a specific annotation)
     */
    public function listImageComments(Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        $root = $this->getOrCreateRootCommentAnnotation($ticketImage);
        $comments = $root->comments()->with(['user'])->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    /**
     * Store an image-level comment (optionally as a reply via parent_id)
     */
    public function storeImageComment(Request $request, Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:annotation_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $root = $this->getOrCreateRootCommentAnnotation($ticketImage);

            $comment = AnnotationComment::create([
                'annotation_id' => $root->id,
                'user_id' => Auth::id(),
                'content' => $request->content,
                'parent_id' => $request->parent_id,
            ]);

            $comment->load(['user', 'replies.user']);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment added successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an image-level comment
     */
    public function updateImageComment(Request $request, Ticket $ticket, TicketImage $ticketImage, AnnotationComment $comment): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        // Ensure comment belongs to the image root annotation
        $root = $this->getOrCreateRootCommentAnnotation($ticketImage);
        if ($comment->annotation_id !== $root->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found for this image'
            ], 404);
        }

        // Only allow the creator or admins to update
        if ($comment->user_id !== Auth::id() && !Auth::user()->can('tickets.ticket.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this comment'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->update([
            'content' => $request->content,
        ]);

        $comment->load(['user']);

        return response()->json([
            'success' => true,
            'data' => $comment,
            'message' => 'Comment updated successfully'
        ]);
    }

    /**
     * Delete an image-level comment
     */
    public function destroyImageComment(Request $request, Ticket $ticket, TicketImage $ticketImage, AnnotationComment $comment): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }

        $root = $this->getOrCreateRootCommentAnnotation($ticketImage);
        if ($comment->annotation_id !== $root->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found for this image'
            ], 404);
        }

        // Only allow the creator or admins to delete
        if ($comment->user_id !== Auth::id() && !Auth::user()->can('tickets.ticket.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this comment'
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    }
}
