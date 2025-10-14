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
    public ?string $rejectionMessage;

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
        ?string $rejectionMessage = null,
    ) {
        $this->ticketId = $ticketId;
        $this->title = $title;
        $this->priority = $priority;
        $this->status = $status;
        $this->reviewerName = $reviewerName;
        $this->ticketUrl = $ticketUrl;
        $this->rejectionMessage = $rejectionMessage;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $subject = 'Ticket #' . $this->ticketId . ' Rejected: ' . $this->title;

        return $this->subject($subject)
            ->view('emails.tickets.rejected')
            ->text('emails.tickets.rejected_plain')
            ->with([
                'ticketId' => $this->ticketId,
                'title' => $this->title,
                'priority' => $this->priority,
                'status' => $this->status,
                'reviewerName' => $this->reviewerName,
                'ticketUrl' => $this->ticketUrl,
                'rejectionMessage' => $this->rejectionMessage,
            ]);
    }
}
