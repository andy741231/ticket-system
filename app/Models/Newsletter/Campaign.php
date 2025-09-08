<?php

namespace App\Models\Newsletter;

use App\Models\User;
use App\Models\Newsletter\Subscriber;
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
        'enable_tracking',
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
        'enable_tracking' => 'boolean',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

/**
     * Get a query builder for the campaign's recipients.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRecipientsQuery()
    {
        $query = Subscriber::query()
            ->where('status', 'active');

        // If not sending to all, filter by target groups
        if (!$this->send_to_all && !empty($this->target_groups)) {
            $query->whereHas('groups', function($q) {
                $q->whereIn('newsletter_subscriber_groups.group_id', $this->target_groups);
            });
        }

        return $query;
    }

    protected $appends = [
        'open_rate',
        'click_rate',
        'unsubscribe_rate',
        'bounce_rate',
        'last_error',
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
        return $this->hasMany(ScheduledSend::class)->orderBy('scheduled_at');
    }
    
    public function pendingScheduledSends(): HasMany
    {
        return $this->scheduledSends()
            ->where('status', 'pending')
            ->where('scheduled_at', '>', now());
    }
    
    public function completedScheduledSends(): HasMany
    {
        return $this->scheduledSends()
            ->where('status', 'completed')
            ->orderBy('scheduled_at', 'desc');
    }
    
    /**
     * Check if the campaign is a recurring campaign.
     *
     * @return bool
     */
    public function isRecurring(): bool
    {
        return $this->send_type === 'recurring' && !empty($this->recurring_config);
    }
    
    /**
     * Get the last sent date for a recurring campaign.
     *
     * @return \Carbon\Carbon|null
     */
    public function getLastSentDate()
    {
        $lastSend = $this->scheduledSends()
            ->where('status', 'completed')
            ->orderBy('scheduled_at', 'desc')
            ->first();
            
        return $lastSend ? $lastSend->sent_at : null;
    }
    
    /**
     * Get the next scheduled send date for a recurring campaign.
     *
     * @return \Carbon\Carbon|null
     */
    public function getNextSendDate()
    {
        if (!$this->isRecurring()) {
            return null;
        }
        
        $nextSend = $this->scheduledSends()
            ->where('status', 'pending')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->first();
            
        return $nextSend ? $nextSend->scheduled_at : null;
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
        $totalSent = (int) ($this->sent_count ?? $this->total_recipients ?? 0);
        if ($totalSent <= 0) return 0;

        $opens = $this->analyticsEvents()->where('event_type', 'opened')->distinct('subscriber_id')->count();
        return round(($opens / $totalSent) * 100, 2);
    }

    public function getClickRateAttribute(): float
    {
        $totalSent = (int) ($this->sent_count ?? $this->total_recipients ?? 0);
        if ($totalSent <= 0) return 0;

        $clicks = $this->analyticsEvents()->where('event_type', 'clicked')->distinct('subscriber_id')->count();
        return round(($clicks / $totalSent) * 100, 2);
    }

    public function getUnsubscribeRateAttribute(): float
    {
        $totalSent = (int) ($this->sent_count ?? $this->total_recipients ?? 0);
        if ($totalSent <= 0) return 0;

        $unsubscribes = $this->analyticsEvents()->where('event_type', 'unsubscribed')->count();
        return round(($unsubscribes / $totalSent) * 100, 2);
    }

    public function getBounceRateAttribute(): float
    {
        $totalSent = (int) ($this->sent_count ?? $this->total_recipients ?? 0);
        if ($totalSent <= 0) return 0;

        $bounces = $this->analyticsEvents()->where('event_type', 'bounced')->count();
        return round(($bounces / $totalSent) * 100, 2);
    }

    public function canBeSent(): bool
    {
        // For recurring campaigns, we only need the template and subject to be set
        if ($this->send_type === 'recurring') {
            return !empty($this->subject) && !empty($this->html_content);
        }
        
        // For one-time sends, check status and required fields
        return in_array($this->status, ['draft', 'scheduled', 'sending']) && 
               !empty($this->subject) && 
               !empty($this->html_content);
    }
    
    
    /**
     * Cancel all pending scheduled sends for this campaign.
     *
     * @return int Number of cancelled sends
     */
    public function cancelPendingSends(): int
    {
        return $this->scheduledSends()
            ->where('status', 'pending')
            ->where('scheduled_at', '>', now())
            ->update(['status' => 'cancelled']);
    }
    

    /**
     * Mark the campaign as sending if it's in a valid state.
     */
    public function markAsSending(): void
    {
        if (in_array($this->status, ['draft', 'scheduled'])) {
            $this->update(['status' => 'sending']);
        }
    }
    
    /**
     * Mark the campaign as sent with current timestamp.
     * Only updates if current status is 'sending' or 'scheduled'.
     */
    public function markAsSent(): void
    {
        if (in_array($this->status, ['sending', 'scheduled'])) {
            $this->update([
                'status' => 'sent',
                'sent_at' => $this->sent_at ?? now(),
                'completed_at' => now()
            ]);
        }
    }
    
    /**
     * Revert the campaign to draft status with an optional reason.
     * Only updates if current status is 'sending' or 'scheduled'.
     *
     * @param string|null $reason Optional reason for reverting to draft
     */
    public function markAsDraft(string $reason = null): void
    {
        if (in_array($this->status, ['sending', 'scheduled'])) {
            $updates = ['status' => 'draft'];
            if ($reason) {
                $updates['error_message'] = $reason;
            }
            $this->update($updates);
        }
    }

    public function recordError(\Throwable $e): void
    {
        // Store the error in the metadata JSON column if it exists
        $metadata = $this->metadata ?? [];
        $metadata['last_error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'time' => now()->toDateTimeString(),
        ];
        
        $this->update([
            'metadata' => $metadata,
            'status' => 'failed',
        ]);
    }

    public function getLastErrorAttribute(): ?string
    {
        // First check if we have an error in metadata
        if (isset($this->metadata['last_error'])) {
            $error = $this->metadata['last_error'];
            if (is_array($error)) {
                return $error['message'] ?? 'An error occurred';
            }
            return $error;
        }

        // Fall back to checking failed jobs
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
                return 'Failed jobs table does not exist';
            }

            $failedJob = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->where('payload', 'like', '%"campaign_id":' . $this->id . '%')
                ->orWhere('payload', 'like', '%"campaign_id":"' . $this->id . '"%')
                ->orderBy('failed_at', 'desc')
                ->first();

            if (!$failedJob) {
                return null;
            }

            $payload = json_decode($failedJob->payload, true);
            if (isset($payload['data']['command'])) {
                $command = unserialize($payload['data']['command']);
                if (isset($command->exception)) {
                    return $command->exception->getMessage();
                }
            }
            
            return $failedJob->exception ?? 'Unknown error occurred';
        } catch (\Exception $e) {
            return 'Error retrieving error details: ' . $e->getMessage();
        }
    }
}
