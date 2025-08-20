<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[Test]
    public function user_can_upload_file_to_ticket()
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        // Ensure app context and permission for uploads
        $ticketsApp = \App\Models\App::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
        \App\Models\Permission::firstOrCreate(['name' => 'tickets.file.upload', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($ticketsApp->id);
        $user->givePermissionTo('tickets.file.upload');

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->postJson("/api/tickets/{$ticket->id}/files", [
            'files' => [$file],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'files' => [
                '*' => [
                    'id',
                    'original_name',
                    'url',
                    'size',
                    'mime_type',
                ]
            ]
        ]);

        // Assert the file was stored
        $this->assertCount(1, $ticket->files);
        $uploadedFile = $ticket->files->first();
        $this->assertEquals('document.pdf', $uploadedFile->original_name);
        
        // Assert the file exists in storage
        Storage::disk('public')->assertExists($uploadedFile->file_path);
    }

    #[Test]
    public function user_can_remove_file_from_ticket()
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);
        
        // Create a test file
        $file = UploadedFile::fake()->create('test.pdf', 1000, 'application/pdf');
        $path = $file->store("tickets/{$ticket->id}", 'public');
        
        $ticketFile = TicketFile::create([
            'ticket_id' => $ticket->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->actingAs($user);

        // Ensure app context and permission for file deletion
        $ticketsApp = \App\Models\App::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
        \App\Models\Permission::firstOrCreate(['name' => 'tickets.file.upload', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($ticketsApp->id);
        $user->givePermissionTo('tickets.file.upload');

        $response = $this->deleteJson("/api/tickets/{$ticket->id}/files/{$ticketFile->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('ticket_files', ['id' => $ticketFile->id]);
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function files_are_deleted_when_ticket_is_deleted()
    {
        $user = User::factory()->create();
        
        // Create and assign admin role to user to allow ticket deletion
        // The tickets.destroy route is prefixed with 'tickets', so SetAppContext sets team to the 'tickets' app id.
        $ticketsApp = \App\Models\App::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
        $teamId = $ticketsApp->id;
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'team_id' => $teamId,
        ]);
        $user->assignRole($role);

        // Ensure permission exists and is granted for delete
        \App\Models\Permission::firstOrCreate(['name' => 'tickets.ticket.delete', 'guard_name' => 'web']);
        $user->givePermissionTo('tickets.ticket.delete');
        
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);
        
        // Create test files
        $file1 = UploadedFile::fake()->create('document1.pdf', 1000, 'application/pdf');
        $file2 = UploadedFile::fake()->create('document2.pdf', 1000, 'application/pdf');
        
        $paths = [];
        
        foreach ([$file1, $file2] as $file) {
            $path = $file->store("tickets/{$ticket->id}", 'public');
            $paths[] = $path;
            
            TicketFile::create([
                'ticket_id' => $ticket->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $this->actingAs($user);

        // Delete the ticket
        $response = $this->delete(route('tickets.destroy', $ticket->id));
        
        $response->assertStatus(302); // Redirect after deletion
        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseMissing('ticket_files', ['ticket_id' => $ticket->id]);
        
        // Assert files were deleted from storage
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    #[Test]
    public function file_upload_validates_file_types()
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        // Ensure app context and permission for uploads
        $ticketsApp = \App\Models\App::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
        \App\Models\Permission::firstOrCreate(['name' => 'tickets.file.upload', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($ticketsApp->id);
        $user->givePermissionTo('tickets.file.upload');

        $invalidFile = UploadedFile::fake()->create('script.exe', 1000, 'application/x-msdownload');

        $response = $this->postJson("/api/tickets/{$ticket->id}/files", [
            'files' => [$invalidFile],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['files.0']);
    }

    #[Test]
    public function file_upload_validates_file_size()
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        // Ensure app context and permission for uploads
        $ticketsApp = \App\Models\App::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
        \App\Models\Permission::firstOrCreate(['name' => 'tickets.file.upload', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($ticketsApp->id);
        $user->givePermissionTo('tickets.file.upload');

        // 15MB file (larger than the 10MB limit)
        $largeFile = UploadedFile::fake()->create('large.pdf', 15000, 'application/pdf');

        $response = $this->postJson("/api/tickets/{$ticket->id}/files", [
            'files' => [$largeFile],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['files.0']);
    }
}
