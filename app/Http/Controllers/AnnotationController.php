<?php

namespace App\Http\Controllers;

use App\Models\TicketImage;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class AnnotationController extends Controller
{
    /**
     * Show annotation page for authenticated users
     */
    public function show(TicketImage $image)
    {
        // Load the ticket and check permissions
        $ticket = $image->ticket;
        $this->authorize('view', $ticket);

        // Check if user can review annotations (Super Admin or Tickets Admin)
        $user = auth()->user();
        $canReviewAnnotations = $user && (
            $user->isSuperAdmin() || 
            $user->can('tickets.ticket.manage')
        );

        return Inertia::render('Annotations/Show', [
            'image' => $image->load('ticket'),
            'ticket' => $ticket,
            'isPublic' => false,
            'publicToken' => null,
            'canReviewAnnotations' => $canReviewAnnotations,
        ]);
    }

    /**
     * Show public annotation page (no auth required)
     */
    public function showPublic(TicketImage $image, Request $request)
    {
        // Generate or validate public token
        $token = $request->query('token');
        
        if (!$token) {
            // Generate a new public token for this image
            $token = $this->generatePublicToken($image);
            
            // Redirect with token to create a shareable URL
            return redirect()->route('annotations.public', [
                'image' => $image->id,
                'token' => $token
            ]);
        }

        // Validate the token (simple validation - in production you might want more security)
        if (!$this->validatePublicToken($image, $token)) {
            abort(403, 'Invalid or expired token');
        }

        return Inertia::render('Annotations/Show', [
            'image' => $image->load('ticket'),
            'ticket' => $image->ticket,
            'isPublic' => true,
            'publicToken' => $token,
        ]);
    }

    /**
     * Generate a public token for sharing
     */
    private function generatePublicToken(TicketImage $image): string
    {
        // Simple token generation - in production you might want to store this in database
        return hash('sha256', $image->id . $image->created_at . config('app.key'));
    }

    /**
     * Validate a public token
     */
    private function validatePublicToken(TicketImage $image, string $token): bool
    {
        $expectedToken = $this->generatePublicToken($image);
        return hash_equals($expectedToken, $token);
    }
}
