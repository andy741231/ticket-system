<?php

namespace App\Http\Controllers;

use App\Models\ExternalUser;
use App\Models\TicketImage;
use App\Mail\ExternalUserVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ExternalAuthController extends Controller
{
    /**
     * Request verification email
     */
    public function requestVerification(Request $request, TicketImage $image)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:100',
        ]);

        // Check if image is public
        if (!$image->isPublic()) {
            return response()->json([
                'success' => false,
                'message' => 'This image is not publicly accessible'
            ], 403);
        }

        // Rate limiting: 3 requests per hour per email
        $key = 'external-auth:' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many verification requests. Please try again in " . ceil($seconds / 60) . " minutes."
            ], 429);
        }

        RateLimiter::hit($key, 3600); // 1 hour

        // Get or create external user
        $externalUser = ExternalUser::firstOrCreate(
            ['email' => $request->email],
            ['name' => $request->name]
        );

        // Update name if changed
        if ($externalUser->name !== $request->name) {
            $externalUser->name = $request->name;
            $externalUser->save();
        }

        // Generate verification token
        $token = $externalUser->generateVerificationToken();

        // Grant access to this image
        $externalUser->grantAccessToImage($image->id, auth()->id());

        // Generate verification URL
        $verificationUrl = route('external-auth.verify', [
            'image' => $image->id,
            'token' => $token,
            'email' => $externalUser->email,
        ]);

        // Get context if provided
        $context = $request->input('context');
        $invitedByName = auth()->check() ? auth()->user()->name : null;

        // Send verification email
        Mail::to($externalUser->email)->send(
            new ExternalUserVerification(
                $externalUser,
                $image,
                $verificationUrl,
                $invitedByName,
                $context
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent! Please check your inbox.'
        ]);
    }

    /**
     * Verify email and create session
     */
    public function verify(Request $request, TicketImage $image)
    {
        $email = $request->query('email');
        $token = $request->query('token');

        if (!$email || !$token) {
            return Inertia::render('Errors/ExternalAuthError', [
                'message' => 'Invalid verification link'
            ]);
        }

        // Find external user
        $externalUser = ExternalUser::where('email', $email)->first();

        if (!$externalUser) {
            return Inertia::render('Errors/ExternalAuthError', [
                'message' => 'User not found'
            ]);
        }

        // Check if image is public
        if (!$image->isPublic()) {
            return Inertia::render('Errors/ExternalAuthError', [
                'message' => 'This image is no longer publicly accessible'
            ]);
        }

        // Check if user already has an active session and access to this image
        $fingerprint = ExternalUser::generateFingerprint($request);
        $sessionToken = $request->cookie('external_session');
        
        if ($sessionToken && $externalUser->validateSession($sessionToken, $fingerprint) && $externalUser->hasAccessToImage($image->id)) {
            // User already has valid session, just redirect them
            $publicToken = $this->generatePublicToken($image);
            
            return redirect()
                ->route('annotations.public', [
                    'image' => $image->id,
                    'token' => $publicToken
                ]);
        }

        // Verify token (only if not already verified or session expired)
        if ($externalUser->verification_token !== $token) {
            // Check if user is already verified but token was consumed
            if ($externalUser->isVerified() && $externalUser->hasAccessToImage($image->id)) {
                // User was already verified, generate new session and redirect
                $sessionToken = $externalUser->generateSession($fingerprint, 7);
                $externalUser->recordImageAccess($image->id);
                $publicToken = $this->generatePublicToken($image);
                
                return redirect()
                    ->route('annotations.public', [
                        'image' => $image->id,
                        'token' => $publicToken
                    ])
                    ->cookie('external_session', $sessionToken, 60 * 24 * 7, '/', null, false, true)
                    ->cookie('external_user_id', $externalUser->id, 60 * 24 * 7, '/', null, false, false);
            }
            
            return Inertia::render('Errors/ExternalAuthError', [
                'message' => 'Invalid or expired verification token'
            ]);
        }

        // First-time verification: verify the user
        $externalUser->verify();

        // Generate session
        $sessionToken = $externalUser->generateSession($fingerprint, 7); // 7 days

        // Grant access to the image
        $externalUser->grantAccessToImage($image->id);
        
        // Record first/last access time
        $externalUser->recordImageAccess($image->id);

        // Generate public token for the image
        $publicToken = $this->generatePublicToken($image);

        // Redirect to annotation page with session
        return redirect()
            ->route('annotations.public', [
                'image' => $image->id,
                'token' => $publicToken
            ])
            ->cookie('external_session', $sessionToken, 60 * 24 * 7, '/', null, false, true) // 7 days, httpOnly
            ->cookie('external_user_id', $externalUser->id, 60 * 24 * 7, '/', null, false, false); // 7 days, accessible to JS
    }

    /**
     * Check session status
     */
    public function checkSession(Request $request, TicketImage $image)
    {
        $sessionToken = $request->cookie('external_session');
        $externalUserId = $request->cookie('external_user_id');

        if (!$sessionToken || !$externalUserId) {
            return response()->json([
                'authenticated' => false,
                'user' => null
            ]);
        }

        $externalUser = ExternalUser::find($externalUserId);

        if (!$externalUser) {
            return response()->json([
                'authenticated' => false,
                'user' => null
            ]);
        }

        // Validate session
        $fingerprint = ExternalUser::generateFingerprint($request);
        $isValid = $externalUser->validateSession($sessionToken, $fingerprint);

        if (!$isValid) {
            return response()->json([
                'authenticated' => false,
                'user' => null
            ])->withoutCookie('external_session')->withoutCookie('external_user_id');
        }

        // Check image access
        if (!$externalUser->hasAccessToImage($image->id)) {
            return response()->json([
                'authenticated' => false,
                'user' => null,
                'message' => 'No access to this image'
            ]);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $externalUser->id,
                'name' => $externalUser->name,
                'email' => $externalUser->email,
                'display_name' => $externalUser->display_name,
                'is_external' => true,
            ]
        ]);
    }

    /**
     * Logout external user
     */
    public function logout(Request $request)
    {
        $sessionToken = $request->cookie('external_session');
        $externalUserId = $request->cookie('external_user_id');

        if ($sessionToken && $externalUserId) {
            $externalUser = ExternalUser::find($externalUserId);
            if ($externalUser) {
                $externalUser->invalidateSession();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ])->withoutCookie('external_session')->withoutCookie('external_user_id');
    }

    /**
     * Generate public token for image
     */
    private function generatePublicToken(TicketImage $image): string
    {
        return hash('sha256', $image->id . $image->created_at . config('app.key'));
    }
}
