<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'team_id',
        'slug',
        'description',
        'is_mutable',
    ];

    protected $casts = [
        'is_mutable' => 'bool',
    ];
}
