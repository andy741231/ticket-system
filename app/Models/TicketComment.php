<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'parent_id',
        'pinned',
    ];

    protected $casts = [
        'pinned' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'parent_id')->with(['user', 'reactions', 'attachments']);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(TicketCommentReaction::class, 'comment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketCommentAttachment::class, 'comment_id');
    }
}
