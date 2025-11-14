<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Get the tickets that have this tag.
     */
    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class)->withTimestamps();
    }

    /**
     * Find or create a tag by name.
     */
    public static function findOrCreateByName(string $name): self
    {
        $slug = Str::slug($name);
        
        return static::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name]
        );
    }
}
