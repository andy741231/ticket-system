<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ticket #{{ $ticketId }} Rejected</title>
    <style>
        body { font-family: 'Source Sans 3', Roboto, Helvetica, Arial, sans-serif; color: #111827; margin: 0; padding: 0; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { line-height: 1.5; margin: 0 0 12px; }
        .meta { background: #f9fafb; padding: 12px; border-radius: 6px; margin: 12px 0; }
        .rejection-message { background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px; border-radius: 6px; margin: 12px 0; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: 600; }
        .muted { color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Ticket #{{ $ticketId }} Rejected</h1>
        <p>Your ticket has been reviewed and was not approved.</p>

        @if(!empty($rejectionMessage))
        <div class="rejection-message">
            <p style="margin: 0;"><strong>Reason:</strong></p>
            <p style="margin: 8px 0 0 0;">{{ $rejectionMessage }}</p>
        </div>
        @endif

        <div class="meta">
            <p><strong>Title:</strong> {{ $title }}</p>
            <p><strong>Priority:</strong> {{ $priority }}</p>
            <p><strong>Status:</strong> {{ $status }}</p>
            <p><strong>Reviewed by:</strong> {{ $reviewerName }}</p>
        </div>

        <p>
            <a class="btn" href="{{ $ticketUrl }}">View Ticket</a>
        </p>

        <p class="muted">This is an automated notification from The Hub.</p>
    </div>
</div>
</body>
</html>
