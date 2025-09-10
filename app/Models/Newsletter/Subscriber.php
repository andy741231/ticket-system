<?php

namespace App\Models\Newsletter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    use HasFactory;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'email',
        'name',
        'first_name',
        'last_name',
        'organization',
        'status',
        'subscribed_at',
        'unsubscribed_at',
        'metadata',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscriber) {
            if (empty($subscriber->unsubscribe_token)) {
                $subscriber->unsubscribe_token = Str::random(32);
            }
            if (empty($subscriber->subscribed_at) && $subscriber->status === 'active') {
                $subscriber->subscribed_at = now();
            }
        });
    }

    /**
     * Check if the subscriber is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    /**
     * Check if the subscriber is pending confirmation.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    /**
     * Check if the subscriber has unsubscribed.
     */
    public function hasUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed' || $this->unsubscribed_at !== null;
    }
    
    /**
     * Mark the subscriber as active.
     */
    public function markAsActive(): bool
    {
        return $this->update([
            'status' => 'active',
            'subscribed_at' => $this->subscribed_at ?? now(),
            'unsubscribed_at' => null,
        ]);
    }
    
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'newsletter_subscriber_groups', 'subscriber_id', 'group_id')
            ->withTimestamps();
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function scheduledSends(): HasMany
    {
        return $this->hasMany(ScheduledSend::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnsubscribed($query)
    {
        return $query->where('status', 'unsubscribed');
    }

    public function scopeBounced($query)
    {
        return $query->where('status', 'bounced');
    }

    public function unsubscribe(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }

    public function resubscribe(): void
    {
        $this->update([
            'status' => 'active',
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }
        
        return $this->name ?: $this->email;
    }

    public function getOrganizationAttribute(): ?string
    {
        return $this->attributes['organization'] ?? null;
    }

    public function setOrganizationAttribute(?string $value): void
    {
        $this->attributes['organization'] = $value;
    }
}
