<?php

namespace App\Models\Newsletter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    protected $table = 'newsletter_analytics_events';

    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'event_type',
        'link_url',
        'user_agent',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function scopeOpens($query)
    {
        return $query->where('event_type', 'opened');
    }

    public function scopeClicks($query)
    {
        return $query->where('event_type', 'clicked');
    }

    public function scopeBounces($query)
    {
        return $query->where('event_type', 'bounced');
    }

    public function scopeUnsubscribes($query)
    {
        return $query->where('event_type', 'unsubscribed');
    }
}
