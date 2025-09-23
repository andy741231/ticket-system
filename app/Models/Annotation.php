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
     * Get the user who created this annotation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
