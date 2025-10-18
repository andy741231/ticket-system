<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnotationMentionNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $ticket;
    public $annotation;
    public $comment;
    public $mentionedUser;
    public $mentioningUser;

    /**
     * Create a new message instance.
     * 
     * @param Ticket $ticket
     * @param Annotation|null $annotation
     * @param AnnotationComment $comment
     * @param User $mentionedUser
     * @param User|object $mentioningUser Can be a User model or stdClass for external users
     */
    public function __construct(Ticket $ticket, ?Annotation $annotation, AnnotationComment $comment, User $mentionedUser, mixed $mentioningUser)
    {
        $this->ticket = $ticket;
        $this->annotation = $annotation;
        $this->comment = $comment;
        $this->mentionedUser = $mentionedUser;
        $this->mentioningUser = $mentioningUser;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $context = $this->annotation ? 'an annotation' : 'an image comment';
        return new Envelope(
            subject: "You were mentioned in {$context} on Ticket #{$this->ticket->id}: {$this->ticket->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // For image-level comments, use ticket_image_id from the comment
        // For annotation-level comments, use ticket_image_id from the annotation
        $imageId = $this->annotation ? $this->annotation->ticket_image_id : $this->comment->ticket_image_id;
        
        return new Content(
            view: 'emails.annotation-mention',
            with: [
                'ticket' => $this->ticket,
                'annotation' => $this->annotation,
                'comment' => $this->comment,
                'mentionedUser' => $this->mentionedUser,
                'mentioningUser' => $this->mentioningUser,
                'annotationUrl' => config('app.url') . '/annotations/' . $imageId . '?comment=' . $this->comment->id,
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
