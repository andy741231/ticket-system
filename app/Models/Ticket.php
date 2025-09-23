<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'due_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Ticket $ticket) {
            // Delete the entire ticket directory and all files within it
            // Storage path is 'public/tickets/{ticket_id}' as files are stored via the 'public' disk
            Storage::disk('public')->deleteDirectory('tickets/' . $ticket->id);
            // Database records for related files will be removed by FK cascade (see migration)
        });

        static::saving(function (Ticket $ticket) {
            if ($ticket->isDirty('description')) {
                $ticket->description_text = strip_tags($ticket->description);
            }
        });
    }

    /**
     * Get the files associated with the ticket.
     */
    public function files(): HasMany
    {
        return $this->hasMany(TicketFile::class);
    }

    /**
     * Get the user that owns the ticket.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    

    /**
     * User who last modified the ticket.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Users assigned to the ticket (many-to-many).
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Scope a query to only include tickets for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Comments on the ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)
            ->whereNull('parent_id')
            ->with(['user', 'reactions', 'attachments', 'replies'])
            ->orderByDesc('pinned')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Images associated with the ticket for annotation.
     */
    public function images(): HasMany
    {
        return $this->hasMany(TicketImage::class);
    }
}
