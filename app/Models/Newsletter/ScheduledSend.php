<?php

namespace App\Models\Newsletter;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class ScheduledSend extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    protected $table = 'newsletter_scheduled_sends';

    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'status',
        'scheduled_at',
        'sent_at',
        'error_message',
        'retry_count',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'retry_count' => 0,
        'metadata' => '{}',
    ];

    /**
     * The "booting" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->scheduled_at)) {
                $model->scheduled_at = now();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Scope a query to only include pending sends that are due.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope a query to only include pending sends.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include sent sends.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope a query to only include failed sends.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include sends that are ready to be processed.
     */
    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('scheduled_at', '<=', now())
                    ->orderBy('scheduled_at');
    }

    /**
     * Mark the scheduled send as processing.
     */
    public function markAsProcessing(): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSING,
            'error_message' => null,
        ]);
    }

    /**
     * Mark the scheduled send as sent.
     */
    public function markAsSent(): bool
    {
        \Log::info('markAsSent called', [
            'scheduled_send_id' => $this->id,
            'current_status' => $this->status,
            'campaign_id' => $this->campaign_id,
            'subscriber_id' => $this->subscriber_id
        ]);

        $result = $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'error_message' => null,
        ]);

        \Log::info('markAsSent result', [
            'scheduled_send_id' => $this->id,
            'result' => $result ? 'success' : 'failed',
            'updated_status' => $this->fresh()->status
        ]);

        return $result;
    }

    /**
     * Mark the scheduled send as failed.
     */
    public function markAsFailed(?string $error = null): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark the scheduled send as cancelled.
     */
    public function markAsCancelled(): bool
    {
        return $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Check if the scheduled send is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the scheduled send is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the scheduled send is sent.
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if the scheduled send is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the scheduled send is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if the scheduled send is due to be sent.
     */
    public function isDue(): bool
    {
        return $this->isPending() && $this->scheduled_at->isPast();
    }

    /**
     * Get the delay in seconds until the scheduled send time.
     */
    public function getDelayInSeconds(): int
    {
        return max(0, now()->diffInSeconds($this->scheduled_at, false));
    }

    /**
     * Get the status as a human-readable string.
     */
    public function getStatusLabel(): string
    {
        return ucfirst($this->status);
    }
}
