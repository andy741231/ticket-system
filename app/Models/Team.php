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
    protected $connection = 'mysql_directory';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'uhph_team';

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
        'name',
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
}