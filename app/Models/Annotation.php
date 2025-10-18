<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Annotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_image_id',
        'user_id',
        'external_user_id',
        'type',
        'coordinates',
        'content',
        'style',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_by_public',
        'public_user_info',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'content' => 'json',
        'style' => 'array',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by_public' => 'boolean',
        'public_user_info' => 'array',
    ];

    /**
     * Get the ticket image that owns this annotation.
     */
    public function ticketImage(): BelongsTo
    {
        return $this->belongsTo(TicketImage::class);
    }

    /**
     * Alias for ticketImage relationship (used in some controllers).
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(TicketImage::class, 'ticket_image_id');
    }

    /**
     * Get the user who created this annotation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the external user who created this annotation.
     */
    public function externalUser(): BelongsTo
    {
        return $this->belongsTo(ExternalUser::class);
    }

    /**
     * Get the user who reviewed this annotation.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get all comments for this annotation.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(AnnotationComment::class)
            ->whereNull('parent_id')
            ->with(['user', 'replies'])
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get all comments including replies.
     */
    public function allComments(): HasMany
    {
        return $this->hasMany(AnnotationComment::class);
    }

    /**
     * Check if the annotation is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the annotation is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the annotation is pending review.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get the creator (internal user or external user)
     */
    public function getCreatorAttribute()
    {
        if ($this->user_id) {
            return $this->user;
        }
        
        if ($this->external_user_id) {
            return $this->externalUser;
        }
        
        // Fallback to public_user_info for legacy data
        if ($this->created_by_public && $this->public_user_info) {
            return (object) [
                'name' => $this->public_user_info['name'] ?? 'Anonymous',
                'email' => $this->public_user_info['email'] ?? null,
                'is_external' => true,
            ];
        }
        
        return null;
    }

    /**
     * Check if annotation was created by external user
     */
    public function isCreatedByExternalUser(): bool
    {
        return $this->external_user_id !== null;
    }
}
