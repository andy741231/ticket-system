<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalUserImageAccess extends Model
{
    use HasFactory;

    protected $table = 'external_user_image_access';

    protected $fillable = [
        'external_user_id',
        'ticket_image_id',
        'invited_by_user_id',
        'invited_at',
        'first_accessed_at',
        'last_accessed_at',
        'access_revoked',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'first_accessed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_revoked' => 'boolean',
    ];

    /**
     * Get the external user
     */
    public function externalUser()
    {
        return $this->belongsTo(ExternalUser::class);
    }

    /**
     * Get the ticket image
     */
    public function ticketImage()
    {
        return $this->belongsTo(TicketImage::class);
    }

    /**
     * Get the user who invited this external user
     */
    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Revoke access
     */
    public function revoke(): void
    {
        $this->access_revoked = true;
        $this->save();
    }

    /**
     * Restore access
     */
    public function restore(): void
    {
        $this->access_revoked = false;
        $this->save();
    }
}
