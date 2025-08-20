<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'key',
        'description',
        'is_mutable',
    ];

    protected $casts = [
        'is_mutable' => 'bool',
    ];
}
