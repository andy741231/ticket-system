<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Newsletter Archive</title>
    <meta name="robots" content="index,follow">
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 0; }
        .container { max-width: 960px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .title { font-size: 1.5rem; font-weight: 700; margin: 0 0 8px; }
        .meta { color: #6b7280; font-size: 0.9rem; margin-bottom: 8px; }
        .link { color: #c8102e; text-decoration: none; font-weight: 600; }
        .link:hover { text-decoration: underline; }
        header { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
        footer { padding: 24px; color: #6b7280; font-size: 0.85rem; text-align: center; border-top: 1px solid #e5e7eb; }
        nav { margin-bottom: 16px; }
    </style>
</head>
<body>
<header>
    <div class="container">
        <h1 class="title" style="margin:0">Newsletter Archive</h1>
        <p class="meta" style="margin:4px 0 0">Browse past newsletters sent by UHPH Hub</p>
    </div>
</header>
<main class="container" role="main">
    @forelse ($campaigns as $campaign)
        <article class="card" aria-labelledby="campaign-{{ $campaign->id }}-title">
            <h2 id="campaign-{{ $campaign->id }}-title" class="title">{{ $campaign->name }}</h2>
            <div class="meta">
                Subject: <strong>{{ $campaign->subject }}</strong>
                @if($campaign->sent_at)
                    · Sent on {{ $campaign->sent_at->format('M d, Y \a\t h:ia') }}
                @endif
                @if(isset($campaign->open_rate))
                    · Open rate: {{ number_format($campaign->open_rate, 2) }}%
                @endif
                @if(isset($campaign->click_rate))
                    · Click rate: {{ number_format($campaign->click_rate, 2) }}%
                @endif
            </div>
            <a class="link" href="{{ route('newsletter.public.campaign.view', $campaign) }}" aria-label="View newsletter {{ $campaign->name }}">View newsletter</a>
        </article>
    @empty
        <p>No past newsletters found.</p>
    @endforelse

    <nav aria-label="Archive pagination">
        {{ $campaigns->links() }}
    </nav>
</main>
<footer>
    © {{ date('Y') }} UHPH Hub
</footer>
</body>
</html>
