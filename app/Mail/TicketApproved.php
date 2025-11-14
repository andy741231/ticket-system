<?php

namespace App\Mail;

use App\Mail\BaseTicketMailable;

class TicketApproved extends BaseTicketMailable
{
    public int $ticketId;
    public string $title;
    public string $status;
    public string $submitterName;
    public string $ticketUrl;
    public string $ticketTags;

    /**
     * Create a new message instance.
     */
    public function __construct(
        int $ticketId,
        string $title,
        string $status,
        string $submitterName,
        string $ticketUrl,
        string $ticketTags = '',
    ) {
        $this->ticketId = $ticketId;
        $this->title = $title;
        $this->status = $status;
        $this->submitterName = $submitterName;
        $this->ticketUrl = $ticketUrl;
        $this->ticketTags = $ticketTags;
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
                'Tags' => $this->ticketTags ?: 'None',
                'Status' => $this->status,
                'Submitted by' => $this->submitterName,
            ],
            ticketUrl: $this->ticketUrl,
            buttonText: 'Open Ticket',
            footer: 'You are receiving this because you are assigned to this ticket.'
        );
    }
}
