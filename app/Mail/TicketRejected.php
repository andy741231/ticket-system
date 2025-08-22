<?php

namespace App\Mail;

use App\Mail\BaseTicketMailable;

class TicketRejected extends BaseTicketMailable
{
    public int $ticketId;
    public string $title;
    public string $priority;
    public string $status;
    public string $reviewerName;
    public string $ticketUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        int $ticketId,
        string $title,
        string $priority,
        string $status,
        string $reviewerName,
        string $ticketUrl,
    ) {
        $this->ticketId = $ticketId;
        $this->title = $title;
        $this->priority = $priority;
        $this->status = $status;
        $this->reviewerName = $reviewerName;
        $this->ticketUrl = $ticketUrl;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $subject = 'Ticket #' . $this->ticketId . ' Rejected: ' . $this->title;

        return $this->buildMessage(
            subject: $subject,
            heading: 'Ticket #' . $this->ticketId . ' Rejected',
            intro: 'Your ticket has been reviewed and was not approved.',
            meta: [
                'Title' => $this->title,
                'Priority' => $this->priority,
                'Status' => $this->status,
                'Reviewed by' => $this->reviewerName,
            ],
            ticketUrl: $this->ticketUrl,
            buttonText: 'View Ticket',
            footer: 'This is an automated notification from The Hub.'
        );
    }
}
