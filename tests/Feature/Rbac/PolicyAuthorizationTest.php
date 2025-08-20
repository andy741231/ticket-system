<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function createPerm(string $name): Permission
    {
        return Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ], [
            'key' => $name,
            'description' => $name,
            'is_mutable' => true,
        ]);
    }

    protected function grant(User $user, string $perm): void
    {
        $this->createPerm($perm);
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => Permission::where('name', $perm)->first()->id,
            'team_id' => null, // global for simplicity
            'effect' => 'allow',
            'reason' => 'test',
            'expires_at' => now()->addHour(),
        ]);
    }

    #[Test]
    public function ticket_policy_authorization_matrix()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        // Ensure permissions exist
        $perms = [
            'tickets.ticket.view',
            'tickets.ticket.manage',
            'tickets.ticket.update',
            'tickets.ticket.delete',
        ];
        foreach ($perms as $p) { $this->createPerm($p); }

        $ticketReceived = Ticket::factory()->for($owner)->create(['status' => 'Received']);
        $ticketApproved = Ticket::factory()->for($owner)->create(['status' => 'Approved']);

        // viewAny (no perms yet)
        $this->assertFalse($other->can('viewAny', Ticket::class));

        // view: owner without perms can view own; other without perms cannot view others
        $this->assertTrue($owner->can('view', $ticketApproved));
        $this->assertFalse($other->can('view', $ticketApproved));

        // Grant view -> enables viewAny and view of others
        $this->grant($other, 'tickets.ticket.view');
        $this->assertTrue($other->can('viewAny', Ticket::class));
        $this->assertTrue($other->can('view', $ticketApproved));

        // (intentionally do not grant 'manage' yet; update checks below assume no manage)

        // create: always true
        $this->assertTrue($owner->can('create', Ticket::class));
        $this->assertTrue($other->can('create', Ticket::class));

        // update: owner can update when Received
        $this->assertTrue($owner->can('update', $ticketReceived));
        $this->assertFalse($owner->can('update', $ticketApproved));
        // other cannot update without perms
        $this->assertFalse($other->can('update', $ticketApproved));
        // with update perm, can update any
        $this->grant($other, 'tickets.ticket.update');
        $this->assertTrue($other->can('update', $ticketApproved));

        // delete: only with manage or delete
        $this->assertFalse($owner->can('delete', $ticketApproved));
        $this->grant($other, 'tickets.ticket.manage');
        $this->assertTrue($other->can('delete', $ticketApproved)); // other has manage now
        $this->grant($owner, 'tickets.ticket.delete');
        $this->assertTrue($owner->can('delete', $ticketApproved));

        // changeStatus: manage or update
        $this->assertTrue($other->can('changeStatus', $ticketApproved));
        $this->grant($owner, 'tickets.ticket.update');
        $this->assertTrue($owner->can('changeStatus', $ticketApproved)); // owner has update grant now

        // viewAuthor: only manage
        $this->assertTrue($other->can('viewAuthor', $ticketApproved));
        $this->assertFalse($owner->can('viewAuthor', $ticketApproved));
    }

    #[Test]
    public function user_policy_authorization_matrix()
    {
        $admin = User::factory()->create();
        $self = User::factory()->create();
        $other = User::factory()->create();

        // Ensure permissions exist
        $perms = [
            'hub.user.view',
            'hub.user.manage',
            'hub.user.create',
            'hub.user.update',
            'hub.user.delete',
        ];
        foreach ($perms as $p) { $this->createPerm($p); }

        // viewAny
        $this->assertFalse($self->can('viewAny', User::class));
        $this->grant($admin, 'hub.user.view');
        $this->assertTrue($admin->can('viewAny', User::class));

        // view: own profile always allowed; others require view/manage
        $this->assertTrue($self->can('view', $self));
        $this->assertFalse($self->can('view', $other));
        $this->assertFalse($other->can('view', $self));
        $this->grant($admin, 'hub.user.manage');
        $this->assertTrue($admin->can('view', $self));

        // create: manage or create
        $this->assertFalse($self->can('create', User::class));
        $this->grant($admin, 'hub.user.create');
        $this->assertTrue($admin->can('create', User::class));

        // update: manage or update or self
        $this->assertTrue($self->can('update', $self));
        $this->assertFalse($self->can('update', $other));
        $this->grant($admin, 'hub.user.update');
        $this->assertTrue($admin->can('update', $other));

        // delete: manage or delete and not self
        $this->assertFalse($self->can('delete', $other));
        $this->grant($admin, 'hub.user.delete');
        $this->assertTrue($admin->can('delete', $other));
        $this->assertFalse($admin->can('delete', $admin));

        // changeRole: manage and not self
        $this->assertTrue($admin->can('changeRole', $other));
        $this->assertFalse($admin->can('changeRole', $admin));

        // viewTickets: manage or self
        $this->assertTrue($admin->can('viewTickets', $other));
        $this->assertTrue($self->can('viewTickets', $self));
        $this->assertFalse($self->can('viewTickets', $other));
    }
}
