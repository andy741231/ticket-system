<?php

namespace App\Mail;

use App\Mail\BaseTicketMailable;

class TicketCompleted extends BaseTicketMailable
{
    public int $ticketId;
    public string $title;
    public string $priority;
    public string $status;
    public string $resolverName;
    public string $ticketUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        int $ticketId,
        string $title,
        string $priority,
        string $status,
        string $resolverName,
        string $ticketUrl,
    ) {
        $this->ticketId = $ticketId;
        $this->title = $title;
        $this->priority = $priority;
        $this->status = $status;
        $this->resolverName = $resolverName;
        $this->ticketUrl = $ticketUrl;
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
                'Priority' => $this->priority,
                'Status' => $this->status,
                'Resolved by' => $this->resolverName,
            ],
            ticketUrl: $this->ticketUrl,
            buttonText: 'View Ticket',
            footer: 'This is an automated notification from The Hub.'
        );
    }
}
