<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\User;
use App\Mail\AnnotationMentionNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AnnotationController extends Controller
{
    /**
     * Extract mentions from comment text and return valid users (internal and external)
     */
    private function extractValidMentions(string $text, Ticket $ticket): array
    {
        $validMentions = ['internal' => [], 'external' => []];
        
        // Extract email mentions: @email@domain.com
        preg_match_all('/@([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $text, $emailMatches);
        $mentionedEmails = array_unique($emailMatches[1]);
        
        // Extract username mentions: @username (not containing @)
        preg_match_all('/@([a-zA-Z0-9_\-]+)(?!@)/', $text, $usernameMatches);
        // Filter out any that are actually part of email addresses
        $mentionedUsernames = array_filter(array_unique($usernameMatches[1]), function($username) use ($mentionedEmails) {
            foreach ($mentionedEmails as $email) {
                if (strpos($email, $username) === 0) {
                    return false; // This username is part of an email
                }
            }
            return true;
        });
        
        // Get internal users who have access to this ticket
        $validUsers = $this->getUsersWithTicketAccess($ticket);
        
        // Process email mentions
        foreach ($mentionedEmails as $email) {
            $email = trim($email);
            
            // Check if it's an internal user
            $user = $validUsers->first(function ($user) use ($email) {
                return !empty($user->email) && strcasecmp($user->email, $email) === 0;
            });
            
            if ($user) {
                $validMentions['internal'][] = $user->id;
            } else {
                // Check if it's an external user with access to this ticket's images
                $externalUser = \App\Models\ExternalUser::where('email', $email)
                    ->whereHas('imageAccess', function ($query) use ($ticket) {
                        $query->whereIn('ticket_image_id', function ($subQuery) use ($ticket) {
                            $subQuery->select('id')
                                ->from('ticket_images')
                                ->where('ticket_id', $ticket->id);
                        })
                        ->where('access_revoked', false);
                    })
                    ->first();
                    
                if ($externalUser) {
                    $validMentions['external'][] = $externalUser->id;
                }
            }
        }
        
        // Process username mentions (internal users only)
        foreach ($mentionedUsernames as $username) {
            $username = trim($username);
            
            // Try to find exact username match
            $user = $validUsers->first(function ($user) use ($username) {
                return !empty($user->username) && strcasecmp($user->username, $username) === 0;
            });
            
            // If no username match, try other fields
            if (!$user) {
                $user = $validUsers->first(function ($user) use ($username) {
                    if (!empty($user->first_name) && strcasecmp($user->first_name, $username) === 0) {
                        return true;
                    }
                    if (!empty($user->last_name) && strcasecmp($user->last_name, $username) === 0) {
                        return true;
                    }
                    if (!empty($user->name) && strcasecmp($user->name, $username) === 0) {
                        return true;
                    }
                    return false;
                });
            }
            
            if ($user) {
                $validMentions['internal'][] = $user->id;
            }
        }
        
        // Remove duplicates
        $validMentions['internal'] = array_unique($validMentions['internal']);
        $validMentions['external'] = array_unique($validMentions['external']);
        
        return $validMentions;
    }
    
    /**
     * Get all users who have access to the ticket
     */
    private function getUsersWithTicketAccess(Ticket $ticket)
    {
        $userIds = collect([$ticket->user_id]);
        
        // Add assigned users
        $assignedUserIds = $ticket->assignees()->pluck('users.id');
        $userIds = $userIds->merge($assignedUserIds);
        
        // Add users with manage/update permissions
        $managementUsers = User::whereHas('roles.permissions', function ($query) {
            $query->whereIn('name', ['tickets.ticket.manage', 'tickets.ticket.update']);
        })->pluck('id');
        $userIds = $userIds->merge($managementUsers);

        // Add super admins
        try {
            $superRoleIds = \Spatie\Permission\Models\Role::query()
                ->where('name', 'super_admin')
                ->pluck('id');
            if ($superRoleIds->isNotEmpty()) {
                $superAdminIds = \DB::table('model_has_roles')
                    ->whereIn('role_id', $superRoleIds)
                    ->where('model_type', User::class)
                    ->pluck('model_id');
                $userIds = $userIds->merge($superAdminIds);
            }
        } catch (\Throwable $e) {
            // Fail open
        }
        
        return User::whereIn('id', $userIds->unique())->get();
    }

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
            ->with(['user', 'externalUser', 'reviewer', 'comments.user', 'comments.externalUser'])
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
        $this->authorize('view', $ticket);

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
        $this->authorize('view', $ticket);

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
        $this->authorize('view', $ticket);

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
            // Extract valid mentions
            $mentions = $this->extractValidMentions($request->content, $ticket);
            
            // Store all mention IDs (internal and external) for the database
            $allMentionIds = array_merge(
                array_map(fn($id) => 'user_' . $id, $mentions['internal']),
                array_map(fn($id) => 'external_' . $id, $mentions['external'])
            );
            
            $comment = AnnotationComment::create([
                'annotation_id' => $annotation->id,
                'user_id' => Auth::id(),
                'content' => $request->content,
                'parent_id' => $request->parent_id,
                'mentions' => $allMentionIds,
            ]);

            $comment->load(['user', 'externalUser', 'replies.user', 'replies.externalUser']);
            
            // Send mention notifications to internal users
            if (!empty($mentions['internal'])) {
                $currentUser = Auth::user();
                $mentionedUsers = User::whereIn('id', $mentions['internal'])->get();
                
                foreach ($mentionedUsers as $mentionedUser) {
                    // Don't send notification to the user who made the comment
                    if ($mentionedUser->id !== $currentUser->id) {
                        Mail::to($mentionedUser->email)
                            ->send(new AnnotationMentionNotification($ticket, $annotation, $comment, $mentionedUser, $currentUser));
                    }
                }
            }
            
            // Send mention notifications to external users
            if (!empty($mentions['external'])) {
                $currentUser = Auth::user();
                $mentionedExternalUsers = \App\Models\ExternalUser::whereIn('id', $mentions['external'])->get();
                
                foreach ($mentionedExternalUsers as $externalUser) {
                    // Get the image for this annotation
                    $image = $annotation->image;
                    $publicToken = hash('sha256', $image->id . $image->created_at . config('app.key'));
                    $accessUrl = route('annotations.public', [
                        'image' => $image->id,
                        'token' => $publicToken,
                        'comment' => $comment->id,
                    ]);
                    
                    // Send notification email to external user
                    Mail::to($externalUser->email)->send(
                        new \App\Mail\ExternalUserMentionNotification(
                            $externalUser,
                            $comment,
                            $image,
                            $currentUser->name,
                            $accessUrl
                        )
                    );
                }
            }

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

        $comments = $annotation->comments()
            ->with(['user', 'externalUser'])
            ->get();

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

        // Extract valid mentions from updated comment
        $mentions = $this->extractValidMentions($request->content, $ticket);
        
        // Store all mention IDs (internal and external) for the database
        $allMentionIds = array_merge(
            array_map(fn($id) => 'user_' . $id, $mentions['internal']),
            array_map(fn($id) => 'external_' . $id, $mentions['external'])
        );
        
        $comment->update([
            'content' => $request->content,
            'mentions' => $allMentionIds,
        ]);

        $comment->load(['user', 'externalUser']);

        return response()->json([
            'success' => true,
            'data' => $comment,
            'message' => 'Comment updated successfully'
        ]);
    }


    /**
     * List all comments for an image (image-level comments only, not linked to specific annotations)
     */
    public function listImageComments(Ticket $ticket, TicketImage $ticketImage): JsonResponse
    {
        \Log::info('[listImageComments] Called', [
            'ticket_id' => $ticket->id,
            'image_id' => $ticketImage->id
        ]);

        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            \Log::error('[listImageComments] Image does not belong to ticket');
            return response()->json([
                'success' => false,
                'message' => 'Image not found for this ticket'
            ], 404);
        }
        
        // Get image-level comments (comments with ticket_image_id and null annotation_id)
        $comments = AnnotationComment::where('ticket_image_id', $ticketImage->id)
            ->whereNull('annotation_id')
            ->with(['user', 'externalUser'])
            ->orderBy('created_at', 'asc')
            ->get();

        \Log::info('[listImageComments] Found image-level comments', [
            'count' => $comments->count(),
            'comment_ids' => $comments->pluck('id')->toArray()
        ]);

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
        \Log::info('[storeImageComment] Called', [
            'ticket_id' => $ticket->id,
            'image_id' => $ticketImage->id,
            'user_id' => Auth::id(),
            'content' => $request->content
        ]);

        $this->authorize('view', $ticket);

        if ($ticketImage->ticket_id !== $ticket->id) {
            \Log::error('[storeImageComment] Image does not belong to ticket');
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
            \Log::error('[storeImageComment] Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Extract valid mentions
            $mentions = $this->extractValidMentions($request->content, $ticket);
            \Log::info('[storeImageComment] Extracted mentions', ['mentions' => $mentions]);

            // Store all mention IDs (internal and external) for the database
            $allMentionIds = array_merge(
                array_map(fn($id) => 'user_' . $id, $mentions['internal']),
                array_map(fn($id) => 'external_' . $id, $mentions['external'])
            );

            \Log::info('[storeImageComment] Creating comment with null annotation_id and ticket_image_id');
            $comment = AnnotationComment::create([
                'annotation_id' => null, // Image-level comments have null annotation_id
                'ticket_image_id' => $ticketImage->id, // Link directly to the image
                'user_id' => Auth::id(),
                'content' => $request->content,
                'parent_id' => $request->parent_id,
                'mentions' => $allMentionIds,
            ]);
            \Log::info('[storeImageComment] Comment created', ['comment_id' => $comment->id]);

            $comment->load(['user', 'externalUser', 'replies.user', 'replies.externalUser']);

            // Send mention notifications to internal users
            if (!empty($mentions['internal'])) {
                \Log::info('[storeImageComment] Sending mention notifications to internal users');
                $currentUser = Auth::user();
                $mentionedUsers = User::whereIn('id', $mentions['internal'])->get();
                
                foreach ($mentionedUsers as $mentionedUser) {
                    // Don't send notification to the user who made the comment
                    if ($mentionedUser->id !== $currentUser->id) {
                        Mail::to($mentionedUser->email)
                            ->send(new AnnotationMentionNotification($ticket, null, $comment, $mentionedUser, $currentUser));
                    }
                }
            }
            
            // Send mention notifications to external users
            if (!empty($mentions['external'])) {
                \Log::info('[storeImageComment] Sending mention notifications to external users');
                $currentUser = Auth::user();
                $mentionedExternalUsers = \App\Models\ExternalUser::whereIn('id', $mentions['external'])->get();
                
                foreach ($mentionedExternalUsers as $externalUser) {
                    $publicToken = hash('sha256', $ticketImage->id . $ticketImage->created_at . config('app.key'));
                    $accessUrl = route('annotations.public', [
                        'image' => $ticketImage->id,
                        'token' => $publicToken,
                        'comment' => $comment->id,
                    ]);
                    
                    Mail::to($externalUser->email)->send(
                        new \App\Mail\ExternalUserMentionNotification(
                            $externalUser,
                            $comment,
                            $ticketImage,
                            $currentUser->name,
                            $accessUrl
                        )
                    );
                }
            }

            \Log::info('[storeImageComment] Success, returning response');
            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment added successfully'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('[storeImageComment] Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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

        // Ensure comment is an image-level comment for this image
        if ($comment->annotation_id !== null || $comment->ticket_image_id !== $ticketImage->id) {
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

        // Extract valid mentions from updated comment
        $mentions = $this->extractValidMentions($request->content, $ticket);

        // Store all mention IDs (internal and external) for the database
        $allMentionIds = array_merge(
            array_map(fn($id) => 'user_' . $id, $mentions['internal']),
            array_map(fn($id) => 'external_' . $id, $mentions['external'])
        );

        $comment->update([
            'content' => $request->content,
            'mentions' => $allMentionIds,
        ]);

        $comment->load(['user', 'externalUser']);

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

        // Ensure comment is an image-level comment for this image
        if ($comment->annotation_id !== null || $comment->ticket_image_id !== $ticketImage->id) {
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
