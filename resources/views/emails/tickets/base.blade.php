<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $heading }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #111827; margin: 0; padding: 0; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { line-height: 1.5; margin: 0 0 12px; }
        .meta { background: #f9fafb; padding: 12px; border-radius: 6px; margin: 12px 0; }
        .meta p { margin: 0 0 6px; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: 600; }
        .muted { color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>{{ $heading }}</h1>
        <p>{{ $intro }}</p>

        @if(!empty($meta))
        <div class="meta">
            @foreach($meta as $label => $value)
                <p><strong>{{ $label }}:</strong> {{ $value }}</p>
            @endforeach
        </div>
        @endif

        @if(!empty($ticketUrl))
        <p>
            <a class="btn" href="{{ $ticketUrl }}">{{ $buttonText ?? 'View Ticket' }}</a>
        </p>
        @endif

        @if(!empty($footer))
        <p class="muted">{{ $footer }}</p>
        @endif
    </div>
</div>
</body>
</html>
