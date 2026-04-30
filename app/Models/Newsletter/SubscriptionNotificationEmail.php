<?php

namespace App\Models\Newsletter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubscriptionNotificationEmail extends Model
{
    use HasFactory;

    protected $table = 'newsletter_subscription_notification_emails';

    protected $fillable = [
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'newsletter_notification_email_groups', 'notification_email_id', 'group_id')
            ->withTimestamps();
    }
}
