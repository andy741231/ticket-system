Ticket #{{ $ticketId }} Rejected

Your ticket has been reviewed and was not approved.

@if(!empty($rejectionMessage))
Reason:
{{ $rejectionMessage }}

@endif
Title: {{ $title }}
Tags: {{ $tags }}
Status: {{ $status }}
Reviewed by: {{ $reviewerName }}

View Ticket: {{ $ticketUrl }}

This is an automated notification from The Hub.
