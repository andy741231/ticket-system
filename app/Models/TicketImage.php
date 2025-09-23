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
        'mime_type',
        'size',
        'width',
        'height',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Always include computed attributes when serializing
    protected $appends = [
        'image_url',
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
}
