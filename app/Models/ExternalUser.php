<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExternalUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'verification_token',
        'verified_at',
        'session_token',
        'session_expires_at',
        'session_fingerprint',
        'last_activity_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'session_expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Generate a new verification token
     */
    public function generateVerificationToken(): string
    {
        $this->verification_token = Str::random(64);
        $this->save();
        
        return $this->verification_token;
    }

    /**
     * Verify the external user
     */
    public function verify(): void
    {
        $this->verified_at = now();
        $this->verification_token = null;
        $this->save();
    }

    /**
     * Generate a new session token
     */
    public function generateSession(string $fingerprint, int $durationDays = 7): string
    {
        $this->session_token = Str::random(64);
        $this->session_expires_at = now()->addDays($durationDays);
        $this->session_fingerprint = $fingerprint;
        $this->last_activity_at = now();
        $this->save();
        
        return $this->session_token;
    }

    /**
     * Validate session token and fingerprint
     */
    public function validateSession(string $token, string $fingerprint): bool
    {
        if ($this->session_token !== $token) {
            return false;
        }

        if ($this->session_expires_at && $this->session_expires_at->isPast()) {
            return false;
        }

        // Check fingerprint match (allow some flexibility for minor changes)
        if ($this->session_fingerprint !== $fingerprint) {
            return false;
        }

        // Update last activity
        $this->last_activity_at = now();
        $this->save();

        return true;
    }

    /**
     * Invalidate the current session
     */
    public function invalidateSession(): void
    {
        $this->session_token = null;
        $this->session_expires_at = null;
        $this->session_fingerprint = null;
        $this->save();
    }

    /**
     * Check if user is verified
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if session is active
     */
    public function hasActiveSession(): bool
    {
        return $this->session_token !== null 
            && $this->session_expires_at 
            && $this->session_expires_at->isFuture();
    }

    /**
     * Get image access records
     */
    public function imageAccess()
    {
        return $this->hasMany(ExternalUserImageAccess::class);
    }

    /**
     * Get accessible images
     */
    public function accessibleImages()
    {
        return $this->belongsToMany(TicketImage::class, 'external_user_image_access')
            ->withPivot(['invited_by_user_id', 'invited_at', 'first_accessed_at', 'last_accessed_at', 'access_revoked'])
            ->withTimestamps();
    }

    /**
     * Get annotations created by this external user
     */
    public function annotations()
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * Get comments created by this external user
     */
    public function comments()
    {
        return $this->hasMany(AnnotationComment::class);
    }

    /**
     * Check if external user has access to an image
     */
    public function hasAccessToImage(int $imageId): bool
    {
        return $this->imageAccess()
            ->where('ticket_image_id', $imageId)
            ->where('access_revoked', false)
            ->exists();
    }

    /**
     * Grant access to an image
     */
    public function grantAccessToImage(int $imageId, ?int $invitedByUserId = null): ExternalUserImageAccess
    {
        return ExternalUserImageAccess::firstOrCreate(
            [
                'external_user_id' => $this->id,
                'ticket_image_id' => $imageId,
            ],
            [
                'invited_by_user_id' => $invitedByUserId,
                'invited_at' => now(),
            ]
        );
    }

    /**
     * Record image access
     */
    public function recordImageAccess(int $imageId): void
    {
        $access = $this->imageAccess()
            ->where('ticket_image_id', $imageId)
            ->first();

        if ($access) {
            if (!$access->first_accessed_at) {
                $access->first_accessed_at = now();
            }
            $access->last_accessed_at = now();
            $access->save();
        }
    }

    /**
     * Get display name for UI
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (Guest)';
    }

    /**
     * Generate session fingerprint from request
     */
    public static function generateFingerprint($request): string
    {
        return hash('sha256', 
            $request->ip() . 
            $request->userAgent()
        );
    }
}
