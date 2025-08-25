<?php

namespace App\Models\Newsletter;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    protected $table = 'newsletter_templates';

    protected $fillable = [
        'name',
        'description',
        'content',
        'html_content',
        'thumbnail',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'is_default' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_default', false);
    }

    public function makeDefault(): void
    {
        // Remove default status from all other templates
        static::where('is_default', true)->update(['is_default' => false]);
        
        // Set this template as default
        $this->update(['is_default' => true]);
    }
}
