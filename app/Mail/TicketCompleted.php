<?php

namespace App\Mail;

use App\Mail\BaseTicketMailable;

class TicketCompleted extends BaseTicketMailable
{
    public int $ticketId;
    public string $title;
    public string $status;
    public string $resolverName;
    public string $ticketUrl;
    public string $ticketTags;

    /**
     * Create a new message instance.
     */
    public function __construct(
        int $ticketId,
        string $title,
        string $status,
        string $resolverName,
        string $ticketUrl,
        string $ticketTags = '',
    ) {
        $this->ticketId = $ticketId;
        $this->title = $title;
        $this->status = $status;
        $this->resolverName = $resolverName;
        $this->ticketUrl = $ticketUrl;
        $this->ticketTags = $ticketTags;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $subject = 'Ticket #' . $this->ticketId . ' Completed: ' . $this->title;

        return $this->buildMessage(
            subject: $subject,
            heading: 'Ticket #' . $this->ticketId . ' Completed',
            intro: 'Your ticket has been marked as completed in The Hub.',
            meta: [
                'Title' => $this->title,
                'Tags' => $this->ticketTags ?: 'None',
                'Status' => $this->status,
                'Resolved by' => $this->resolverName,
            ],
            ticketUrl: $this->ticketUrl,
            buttonText: 'View Ticket',
            footer: 'This is an automated notification from The Hub.'
        );
    }
}
