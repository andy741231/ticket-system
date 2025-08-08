<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TicketFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TicketFile::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $ticket = Ticket::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');
        
        $path = $file->store("tickets/{$ticket->id}", 'public');
        
        return [
            'ticket_id' => $ticket->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }
}
