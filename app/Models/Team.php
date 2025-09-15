<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'directory';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'directory_team';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'title',
        'degree',
        'email',
        'bio',
        'img',
        'description',
        'message',
        'group_1',
        'program',
        'team',
        'department',
    ];

    /**
     * Append computed attributes when serializing to arrays/JSON.
     *
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * Accessor for a combined full name for backward compatibility.
     */
    public function getNameAttribute(): string
    {
        $first = $this->attributes['first_name'] ?? '';
        $last = $this->attributes['last_name'] ?? '';
        return trim($first . ' ' . $last);
    }
}