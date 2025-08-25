<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->subject }} — Newsletter</title>
    <meta name="robots" content="index,follow">
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 0; background: #f9fafb; }
        .container { max-width: 860px; margin: 0 auto; padding: 24px; }
        .card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; }
        .title { font-size: 1.75rem; font-weight: 800; margin: 0 0 6px; }
        .meta { color: #6b7280; font-size: 0.95rem; margin-bottom: 16px; }
        a.back { color: #374151; text-decoration: none; font-weight: 600; }
        a.back:hover { text-decoration: underline; }
    </style>
</head>
<body>
<main class="container" role="main">
    <p><a class="back" href="{{ route('newsletter.public.archive') }}">← Back to Archive</a></p>

    <article class="card" aria-labelledby="newsletter-title">
        <header>
            <h1 id="newsletter-title" class="title">{{ $campaign->name }}</h1>
            <div class="meta">
                Subject: <strong>{{ $campaign->subject }}</strong>
                @if($campaign->sent_at)
                    · Sent on {{ $campaign->sent_at->format('M d, Y \a\t h:ia') }}
                @endif
            </div>
        </header>
        <section>
            {!! $campaign->html_content !!}
        </section>
    </article>
</main>
</body>
</html>
