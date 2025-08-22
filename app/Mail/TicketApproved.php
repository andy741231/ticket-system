<?php

namespace App\Mail;

use App\Mail\BaseTicketMailable;

class TicketApproved extends BaseTicketMailable
{
    public int $ticketId;
    public string $title;
    public string $priority;
    public string $status;
    public string $submitterName;
    public string $ticketUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        int $ticketId,
        string $title,
        string $priority,
        string $status,
        string $submitterName,
        string $ticketUrl,
    ) {
        $this->ticketId = $ticketId;
        $this->title = $title;
        $this->priority = $priority;
        $this->status = $status;
        $this->submitterName = $submitterName;
        $this->ticketUrl = $ticketUrl;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $subject = 'Ticket #' . $this->ticketId . ' Approved: ' . $this->title;

        return $this->buildMessage(
            subject: $subject,
            heading: 'Ticket #' . $this->ticketId . ' Approved',
            intro: 'A ticket you are assigned to has been approved in The Hub.',
            meta: [
                'Title' => $this->title,
                'Priority' => $this->priority,
                'Status' => $this->status,
                'Submitted by' => $this->submitterName,
            ],
            ticketUrl: $this->ticketUrl,
            buttonText: 'Open Ticket',
            footer: 'You are receiving this because you are assigned to this ticket.'
        );
    }
}
