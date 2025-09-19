<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommentMentionNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $ticket;
    public $comment;
    public $mentionedUser;
    public $mentioningUser;

    /**
     * Create a new message instance.
     */
    public function __construct(Ticket $ticket, TicketComment $comment, User $mentionedUser, User $mentioningUser)
    {
        $this->ticket = $ticket;
        $this->comment = $comment;
        $this->mentionedUser = $mentionedUser;
        $this->mentioningUser = $mentioningUser;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You were mentioned in Ticket #{$this->ticket->id}: {$this->ticket->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.comment-mention',
            with: [
                'ticket' => $this->ticket,
                'comment' => $this->comment,
                'mentionedUser' => $this->mentionedUser,
                'mentioningUser' => $this->mentioningUser,
                'commentUrl' => route('tickets.show', $this->ticket->id) . '?comment=' . $this->comment->id,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
