<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnotationComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'annotation_id',
        'user_id',
        'content',
        'parent_id',
        'created_by_public',
        'public_user_info',
        'mentions',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by_public' => 'boolean',
        'public_user_info' => 'array',
        'mentions' => 'array',
    ];

    /**
     * Get the annotation that owns this comment.
     */
    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }

    /**
     * Get the user who created this comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment if this is a reply.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AnnotationComment::class, 'parent_id');
    }

    /**
     * Get all replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(AnnotationComment::class, 'parent_id')
            ->with(['user', 'replies'])
            ->orderBy('created_at', 'asc');
    }

    /**
     * Check if this comment is a reply.
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }
}
