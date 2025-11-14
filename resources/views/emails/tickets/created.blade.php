<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>New Ticket #{{ $ticketId }}</title>
    <style>
        /* Basic, email-safe inline-able styles */
        body { font-family: 'Source Sans 3', Roboto, Helvetica, Arial, sans-serif; color: #111827; margin: 0; padding: 0; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { line-height: 1.5; margin: 0 0 12px; }
        .meta { background: #f9fafb; padding: 12px; border-radius: 6px; margin: 12px 0; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: 600; }
        .muted { color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>New Ticket #{{ $ticketId }}</h1>
        <p>A new ticket has been created in The Hub.</p>

        <div class="meta">
            <p><strong>Title:</strong> {{ $title }}</p>
            <p><strong>Tags:</strong> {{ $tags }}</p>
            <p><strong>Submitted by:</strong> {{ $submitterName }}</p>
        </div>

        <p>
            <a class="btn" href="{{ $ticketUrl }}">View Ticket</a>
        </p>

        <p class="muted">You are receiving this because you are an admin of the Tickets app.</p>
    </div>
</div>
</body>
</html>
