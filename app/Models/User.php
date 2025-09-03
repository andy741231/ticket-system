<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the username field for the user.
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'description',
        'invited_by_user_id',
        'invited_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'invited_at' => 'datetime',
    ];

    /**
     * Overrides defined for this user.
     */
    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    /**
     * Get all tickets created by the user.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Apps (teams) this user has at least one role in.
     * Derived from the Spatie pivot table `model_has_roles` using team_id -> apps.id.
     */
    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(App::class, 'model_has_roles', 'model_id', 'team_id')
            ->where('model_type', static::class)
            ->withPivot('role_id')
            ->distinct();
    }

    /**
     * Direct permissions assigned to the user (not via roles), including team context.
     */
    public function directPermissionsWithTeam(): MorphToMany
    {
        $tableNames = config('permission.table_names');
        $columns = config('permission.column_names');
        $teamKey = $columns['team_foreign_key'] ?? 'team_id';

        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            $tableNames['model_has_permissions'],
            $columns['model_morph_key'] ?? 'model_id',
            $columns['permission_pivot_key'] ?? 'permission_id'
        )->withPivot($teamKey);
    }

    /**
     * The user who invited this user.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Users invited by this user.
     */
    public function invitedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'invited_by_user_id');
    }

    /**
     * Invites sent by this user.
     */
    public function sentInvites(): HasMany
    {
        return $this->hasMany(Invite::class, 'invited_by_user_id');
    }

    /**
     * Check if the user is a global super admin (role 'super_admin' with team_id NULL).
     */
    public function isSuperAdmin(): bool
    {
        // Teams-enabled: treat any assignment of role 'super_admin' (in any team) as super admin
        // Avoid relying on registrar team context; query pivot directly
        $roleIds = \Spatie\Permission\Models\Role::query()
            ->where('name', 'super_admin')
            ->pluck('id');
        if ($roleIds->isEmpty()) {
            return false;
        }

        return DB::table('model_has_roles')
            ->whereIn('role_id', $roleIds)
            ->where('model_type', static::class)
            ->where('model_id', $this->getKey())
            ->exists();
    }
}
