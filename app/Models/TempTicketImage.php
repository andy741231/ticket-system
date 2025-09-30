<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TempTicketImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Always include computed attributes when serializing
    protected $appends = [
        'image_url',
        'file_size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TempTicketImage $tempImage) {
            // Delete the image file when the record is deleted
            if ($tempImage->image_path && Storage::disk('public')->exists($tempImage->image_path)) {
                Storage::disk('public')->delete($tempImage->image_path);
            }
        });
    }

    /**
     * Get the user that owns this temp image.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
