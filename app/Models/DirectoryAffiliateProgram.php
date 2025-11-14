<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectoryAffiliateProgram extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_directory';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'directory_affiliate_programs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'directory_team_id',
        'title',
        'program',
    ];

    /**
     * Get the directory team that owns the affiliate program.
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'directory_team_id');
    }
}
