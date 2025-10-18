<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TicketImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'source_type',
        'source_value',
        'image_path',
        'original_name',
        'name',
        'mime_type',
        'size',
        'width',
        'height',
        'status',
        'error_message',
        'metadata',
        'is_public',
        'public_access_level',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    // Always include computed attributes when serializing
    protected $appends = [
        'image_url',
        'file_size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TicketImage $ticketImage) {
            // Delete the image file when the record is deleted
            if ($ticketImage->image_path && Storage::disk('public')->exists($ticketImage->image_path)) {
                Storage::disk('public')->delete($ticketImage->image_path);
            }
        });
    }

    /**
     * Get the ticket that owns this image.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get all annotations for this image.
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * Get the URL for the image.
     */
    public function getImageUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function getFileSizeAttribute(): int
    {
        return (int) ($this->size ?? 0);
    }

    /**
     * Check if the image processing is complete.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the image processing failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the image is still processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Get external users with access to this image
     */
    public function externalUsers()
    {
        return $this->belongsToMany(ExternalUser::class, 'external_user_image_access')
            ->withPivot(['invited_by_user_id', 'invited_at', 'first_accessed_at', 'last_accessed_at', 'access_revoked'])
            ->withTimestamps();
    }

    /**
     * Get external user access records
     */
    public function externalUserAccess()
    {
        return $this->hasMany(ExternalUserImageAccess::class);
    }

    /**
     * Check if image is public
     */
    public function isPublic(): bool
    {
        return $this->is_public === true;
    }

    /**
     * Get access level for external users
     */
    public function getAccessLevel(): string
    {
        return $this->public_access_level ?? 'annotate';
    }

    /**
     * Check if external users can annotate
     */
    public function canExternalUsersAnnotate(): bool
    {
        return $this->isPublic() && $this->getAccessLevel() === 'annotate';
    }

    /**
     * Check if external users can comment
     */
    public function canExternalUsersComment(): bool
    {
        return $this->isPublic() && in_array($this->getAccessLevel(), ['comment', 'annotate']);
    }
}
