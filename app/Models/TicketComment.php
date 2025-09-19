<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'parent_id',
        'pinned',
        'mentions',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'mentions' => 'array',
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

    public function mentionedUsers()
    {
        if (empty($this->mentions)) {
            return collect();
        }
        return User::whereIn('id', $this->mentions)->get();
    }
}
