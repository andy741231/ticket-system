<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\TicketImage;
use App\Models\ExternalUser;
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

        // Check if image is public
        if (!$image->isPublic()) {
            return response()->json([
                'success' => false,
                'message' => 'This image is not publicly accessible'
            ], 403);
        }

        $annotations = $image->annotations()
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

        // Check if image is public
        if (!$image->isPublic()) {
            return response()->json([
                'success' => false,
                'message' => 'This image is not publicly accessible'
            ], 403);
        }

        // Get and validate external user session (required for creating annotations)
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please verify your email.'
            ], 401);
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
            'external_user_id' => $externalUser->id,
            'type' => $request->type,
            'coordinates' => $request->coordinates,
            'style' => $request->style ?? [],
            'content' => $request->content,
            'status' => 'pending',
        ]);

        $annotation->save();

        // Load relationships for response
        $annotation->load(['user', 'externalUser', 'reviewer', 'comments.user', 'comments.externalUser']);

        return response()->json([
            'success' => true,
            'data' => $annotation
        ], 201);
    }

    /**
     * Update an annotation (public access - only if created by same external user)
     */
    public function update(Request $request, TicketImage $image, Annotation $annotation): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Get and validate external user session
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Check if annotation belongs to this image
        if ($annotation->ticket_image_id !== $image->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found for this image'
            ], 404);
        }

        // Only allow updating own annotations
        if ($annotation->external_user_id !== $externalUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own annotations'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|string|in:point,rectangle,circle,arrow,freehand,text',
            'coordinates' => 'sometimes|array',
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

        // Update annotation
        $annotation->update($request->only(['type', 'coordinates', 'style', 'content']));

        // Load relationships for response
        $annotation->load(['user', 'externalUser', 'reviewer', 'comments.user', 'comments.externalUser']);

        return response()->json([
            'success' => true,
            'data' => $annotation
        ]);
    }

    /**
     * Delete an annotation (public access - only if created by same external user)
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

        // Get and validate external user session
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Check if annotation belongs to this image
        if ($annotation->ticket_image_id !== $image->id) {
            return response()->json([
                'success' => false,
                'message' => 'Annotation not found for this image'
            ], 404);
        }

        // Only allow deletion of own annotations
        if ($annotation->external_user_id !== $externalUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own annotations'
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

        // Check if image is public
        if (!$image->isPublic()) {
            return response()->json([
                'success' => false,
                'message' => 'This image is not publicly accessible'
            ], 403);
        }

        // Get and validate external user session (required for commenting)
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please verify your email.'
            ], 401);
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
            'parent_id' => 'nullable|exists:annotation_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from content
        $mentions = $this->extractMentions($request->content);

        $comment = new AnnotationComment([
            'annotation_id' => $annotation->id,
            'external_user_id' => $externalUser->id,
            'content' => $request->content,
            'parent_id' => $request->parent_id,
            'mentions' => $mentions,
        ]);

        $comment->save();

        // Process mentions (send notifications)
        $this->processMentions($comment, $mentions, $image, $externalUser);

        // Load relationships for response
        $comment->load(['user', 'externalUser']);

        return response()->json([
            'success' => true,
            'data' => $comment
        ], 201);
    }

    /**
     * Get comments for an annotation (public access)
     */
    public function getComments(TicketImage $image, Annotation $annotation, Request $request): JsonResponse
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

        $comments = $annotation->allComments()
            ->with(['user', 'externalUser', 'replies.user', 'replies.externalUser'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    /**
     * Update a comment (public access)
     */
    public function updateComment(TicketImage $image, Annotation $annotation, AnnotationComment $comment, Request $request): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Get and validate external user session
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Check ownership
        if ($comment->external_user_id !== $externalUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own comments'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from content
        $mentions = $this->extractMentions($request->content);

        $comment->content = $request->content;
        $comment->mentions = $mentions;
        $comment->save();

        // Process new mentions
        $this->processMentions($comment, $mentions, $image, $externalUser);

        $comment->load(['user', 'externalUser']);

        return response()->json([
            'success' => true,
            'data' => $comment
        ]);
    }

    /**
     * Delete a comment (public access)
     */
    public function destroyComment(TicketImage $image, Annotation $annotation, AnnotationComment $comment, Request $request): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Get and validate external user session
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Check ownership
        if ($comment->external_user_id !== $externalUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own comments'
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    }

    /**
     * Store image-level comment (not linked to annotation)
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

        // Check if image is public
        if (!$image->isPublic()) {
            return response()->json([
                'success' => false,
                'message' => 'This image is not publicly accessible'
            ], 403);
        }

        // Get and validate external user session
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please verify your email.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:annotation_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from content
        $mentions = $this->extractMentions($request->content);

        $comment = new AnnotationComment([
            'annotation_id' => null, // Image-level comments have null annotation_id
            'ticket_image_id' => $image->id, // Link directly to the image
            'external_user_id' => $externalUser->id,
            'content' => $request->content,
            'parent_id' => $request->parent_id,
            'mentions' => $mentions,
        ]);

        $comment->save();

        // Process mentions (send notifications)
        $this->processMentions($comment, $mentions, $image, $externalUser);

        // Load relationships for response
        $comment->load(['user', 'externalUser']);

        return response()->json([
            'success' => true,
            'data' => $comment
        ], 201);
    }

    /**
     * Get image-level comments
     */
    public function listImageComments(Request $request, TicketImage $image): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Get image-level comments (comments with ticket_image_id and null annotation_id)
        $comments = AnnotationComment::where('ticket_image_id', $image->id)
            ->whereNull('annotation_id')
            ->with(['user', 'externalUser'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
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

    /**
     * Get authenticated external user from session
     */
    private function getAuthenticatedExternalUser(Request $request, TicketImage $image): ?ExternalUser
    {
        $sessionToken = $request->cookie('external_session');
        $externalUserId = $request->cookie('external_user_id');

        \Log::info('External auth check', [
            'has_session_token' => !empty($sessionToken),
            'has_user_id' => !empty($externalUserId),
            'user_id' => $externalUserId,
            'image_id' => $image->id
        ]);

        if (!$sessionToken || !$externalUserId) {
            \Log::warning('Missing session token or user ID');
            return null;
        }

        $externalUser = ExternalUser::find($externalUserId);

        if (!$externalUser) {
            \Log::warning('External user not found', ['user_id' => $externalUserId]);
            return null;
        }

        // Validate session
        $fingerprint = ExternalUser::generateFingerprint($request);
        if (!$externalUser->validateSession($sessionToken, $fingerprint)) {
            \Log::warning('Session validation failed', [
                'user_id' => $externalUserId,
                'fingerprint' => $fingerprint
            ]);
            return null;
        }

        // Check image access
        if (!$externalUser->hasAccessToImage($image->id)) {
            \Log::warning('User does not have access to image', [
                'user_id' => $externalUserId,
                'image_id' => $image->id
            ]);
            return null;
        }

        \Log::info('External user authenticated successfully', [
            'user_id' => $externalUserId,
            'image_id' => $image->id
        ]);

        return $externalUser;
    }

    /**
     * Extract email mentions from content
     */
    private function extractMentions(string $content): array
    {
        // Match @email@domain.com pattern
        preg_match_all('/@([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $content, $matches);
        
        $mentions = [];
        if (!empty($matches[1])) {
            foreach ($matches[1] as $email) {
                $mentions[] = [
                    'type' => 'email',
                    'value' => $email,
                ];
            }
        }

        return $mentions;
    }

    /**
     * Get mentionable users for external user (public access)
     */
    public function mentionableUsers(Request $request, TicketImage $image): JsonResponse
    {
        \Log::info('[PublicAnnotation] mentionableUsers called', [
            'image_id' => $image->id,
            'has_token' => !empty($request->query('token'))
        ]);
        
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            \Log::warning('[PublicAnnotation] Invalid token');
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Get authenticated external user
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            \Log::warning('[PublicAnnotation] No authenticated external user');
            return response()->json([
                'success' => false,
                'users' => []
            ]);
        }
        
        \Log::info('[PublicAnnotation] External user authenticated', [
            'external_user_id' => $externalUser->id,
            'external_user_name' => $externalUser->name
        ]);

        $ticket = $image->ticket;
        $users = [];

        // Get internal users who have access to this ticket
        $userIds = collect([$ticket->user_id]);
        
        // Add assigned users
        $assignedUserIds = \DB::table('ticket_user')
            ->where('ticket_id', $ticket->id)
            ->pluck('user_id');
        $userIds = $userIds->merge($assignedUserIds);
        
        // Add users with manage/update permissions
        $managementUsers = \App\Models\User::whereHas('roles.permissions', function ($query) {
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
                    ->where('model_type', \App\Models\User::class)
                    ->pluck('model_id');
                $userIds = $userIds->merge($superAdminIds);
            }
        } catch (\Throwable $e) {
            // Skip if role table issues
        }
        
        $internalUsers = \App\Models\User::whereIn('id', $userIds->unique())->get();

        foreach ($internalUsers as $user) {
            $users[] = [
                'id' => 'int_' . $user->id,
                'name' => $user->name,
                'username' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'type' => 'internal',
                'searchable_names' => [
                    $user->email,
                    $user->name,
                    $user->first_name,
                    $user->last_name,
                ]
            ];
        }

        // Get other external users who have access to this image
        $otherExternalUsers = ExternalUser::whereHas('imageAccess', function ($query) use ($image) {
            $query->where('ticket_image_id', $image->id)
                ->where('access_revoked', false);
        })
        ->where('id', '!=', $externalUser->id)
        ->get();

        foreach ($otherExternalUsers as $extUser) {
            $users[] = [
                'id' => 'ext_' . $extUser->id,
                'name' => $extUser->name . ' (Guest)',
                'username' => $extUser->email,
                'first_name' => $extUser->name,
                'last_name' => null,
                'email' => $extUser->email,
                'type' => 'external',
                'searchable_names' => [
                    $extUser->email,
                    $extUser->name,
                    $extUser->name . ' (Guest)'
                ]
            ];
        }

        \Log::info('[PublicAnnotation] Returning mentionable users', [
            'total_users' => count($users),
            'internal_count' => count(array_filter($users, fn($u) => $u['type'] === 'internal')),
            'external_count' => count(array_filter($users, fn($u) => $u['type'] === 'external'))
        ]);
        
        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }


    /**
     * Process mentions and send notifications
     */
    private function processMentions(AnnotationComment $comment, array $mentions, TicketImage $image, ExternalUser $mentionedBy): void
    {
        if (empty($mentions)) {
            return;
        }

        foreach ($mentions as $mention) {
            if ($mention['type'] !== 'email') {
                continue;
            }

            $email = $mention['value'];

            // Check if it's an internal user
            $internalUser = \App\Models\User::where('email', $email)->first();
            if ($internalUser) {
                // Send notification to internal user
                $ticket = $image->ticket;
                $annotation = $comment->annotation;
                
                \Illuminate\Support\Facades\Mail::to($internalUser->email)->send(
                    new \App\Mail\AnnotationMentionNotification(
                        $ticket,
                        $annotation,
                        $comment,
                        $internalUser,
                        (object)['name' => $mentionedBy->name . ' (Guest)', 'email' => $mentionedBy->email]
                    )
                );
                continue;
            }

            // Handle external user mention
            $externalUser = ExternalUser::where('email', $email)->first();

            if (!$externalUser) {
                // Create new external user and send invitation
                $externalUser = ExternalUser::create([
                    'email' => $email,
                    'name' => explode('@', $email)[0], // Use email prefix as default name
                ]);
            }

            // Grant access to this image
            $externalUser->grantAccessToImage($image->id, $mentionedBy->id);

            // Generate verification/access URL
            if (!$externalUser->hasActiveSession()) {
                // Send verification email
                $token = $externalUser->generateVerificationToken();
                $verificationUrl = route('external-auth.verify', [
                    'image' => $image->id,
                    'token' => $token,
                    'email' => $externalUser->email,
                ]);

                \Illuminate\Support\Facades\Mail::to($externalUser->email)->send(
                    new \App\Mail\ExternalUserVerification(
                        $externalUser,
                        $image,
                        $verificationUrl,
                        $mentionedBy->name,
                        "You were mentioned in a comment"
                    )
                );
            } else {
                // Send mention notification with direct access
                $publicToken = hash('sha256', $image->id . $image->created_at . config('app.key'));
                $accessUrl = route('annotations.public', [
                    'image' => $image->id,
                    'token' => $publicToken,
                    'comment' => $comment->id,
                ]);

                \Illuminate\Support\Facades\Mail::to($externalUser->email)->send(
                    new \App\Mail\ExternalUserMentionNotification(
                        $externalUser,
                        $comment,
                        $image,
                        $mentionedBy->name,
                        $accessUrl
                    )
                );
            }
        }
    }

    /**
     * Update an image-level comment (public access)
     */
    public function updateImageComment(Request $request, TicketImage $image, AnnotationComment $comment): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Get authenticated external user
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Verify the comment is an image-level comment for this image
        if ($comment->annotation_id !== null || $comment->ticket_image_id !== $image->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found for this image'
            ], 404);
        }

        // Verify the comment belongs to this external user
        if ($comment->external_user_id !== $externalUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this comment'
            ], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from content
        $mentions = $this->extractMentions($request->content);

        $comment->content = $request->content;
        $comment->mentions = $mentions;
        $comment->save();

        // Process new mentions
        $this->processMentions($comment, $mentions, $image, $externalUser);

        $comment->load(['user', 'externalUser']);

        return response()->json([
            'success' => true,
            'data' => $comment,
            'message' => 'Comment updated successfully'
        ]);
    }

    /**
     * Delete an image-level comment (public access)
     */
    public function destroyImageComment(Request $request, TicketImage $image, AnnotationComment $comment): JsonResponse
    {
        // Validate public token
        $token = $request->query('token');
        if (!$this->validatePublicToken($image, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // Get authenticated external user
        $externalUser = $this->getAuthenticatedExternalUser($request, $image);
        if (!$externalUser) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Verify the comment is an image-level comment for this image
        if ($comment->annotation_id !== null || $comment->ticket_image_id !== $image->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found for this image'
            ], 404);
        }

        // Verify the comment belongs to this external user
        if ($comment->external_user_id !== $externalUser->id) {
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
