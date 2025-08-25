<?php

namespace App\Models\Newsletter;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'newsletter_campaigns';

    protected $fillable = [
        'name',
        'subject',
        'preview_text',
        'content',
        'html_content',
        'status',
        'send_type',
        'scheduled_at',
        'sent_at',
        'recurring_config',
        'target_groups',
        'send_to_all',
        'total_recipients',
        'sent_count',
        'failed_count',
        'template_id',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recurring_config' => 'array',
        'target_groups' => 'array',
        'send_to_all' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function scheduledSends(): HasMany
    {
        return $this->hasMany(ScheduledSend::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function getOpenRateAttribute(): float
    {
        $totalSent = $this->sent_count;
        if ($totalSent === 0) return 0;

        $opens = $this->analyticsEvents()->where('event_type', 'opened')->distinct('subscriber_id')->count();
        return round(($opens / $totalSent) * 100, 2);
    }

    public function getClickRateAttribute(): float
    {
        $totalSent = $this->sent_count;
        if ($totalSent === 0) return 0;

        $clicks = $this->analyticsEvents()->where('event_type', 'clicked')->distinct('subscriber_id')->count();
        return round(($clicks / $totalSent) * 100, 2);
    }

    public function getUnsubscribeRateAttribute(): float
    {
        $totalSent = $this->sent_count;
        if ($totalSent === 0) return 0;

        $unsubscribes = $this->analyticsEvents()->where('event_type', 'unsubscribed')->count();
        return round(($unsubscribes / $totalSent) * 100, 2);
    }

    public function getBounceRateAttribute(): float
    {
        $totalSent = $this->sent_count;
        if ($totalSent === 0) return 0;

        $bounces = $this->analyticsEvents()->where('event_type', 'bounced')->count();
        return round(($bounces / $totalSent) * 100, 2);
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, ['draft', 'scheduled']) && 
               !empty($this->subject) && 
               !empty($this->html_content);
    }

    public function markAsSending(): void
    {
        $this->update(['status' => 'sending']);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
