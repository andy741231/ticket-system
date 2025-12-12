<?php

namespace App\Models\Newsletter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class Group extends Model
{
    use HasFactory;

    protected $table = 'newsletter_groups';

    protected $fillable = [
        'name',
        'description',
        'color',
        'is_active',
        'is_external',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_external' => 'boolean',
    ];

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class, 'newsletter_subscriber_groups', 'group_id', 'subscriber_id')
            ->withTimestamps();
    }

    public function activeSubscribers(): BelongsToMany
    {
        return $this->subscribers()->where('newsletter_subscribers.status', 'active');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeExternal($query)
    {
        return $query->where('is_external', true);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_external', false);
    }

    public function markExternal(): bool
    {
        if (!Schema::hasColumn('newsletter_groups', 'is_external')) {
            return false;
        }

        return $this->update(['is_external' => true]);
    }

    public function markInternal(): bool
    {
        if (!Schema::hasColumn('newsletter_groups', 'is_external')) {
            return false;
        }

        return $this->update(['is_external' => false]);
    }

    public function getSubscriberCountAttribute(): int
    {
        return $this->subscribers()->count();
    }

    public function getActiveSubscriberCountAttribute(): int
    {
        return $this->activeSubscribers()->count();
    }
}
