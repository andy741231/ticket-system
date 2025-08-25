<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribe</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; margin: 0; background: #f9fafb; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin-top: 32px; }
        .title { font-size: 1.5rem; font-weight: 700; margin: 0 0 8px; }
        .meta { color: #6b7280; font-size: 0.95rem; margin-bottom: 16px; }
        .btn { background: #c8102e; color: white; border: 0; border-radius: 6px; padding: 10px 16px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #a10d25; }
    </style>
</head>
<body>
<main class="container">
    <article class="card" aria-labelledby="unsubscribe-title">
        <h1 class="title" id="unsubscribe-title">Unsubscribe from Newsletter</h1>
        <p class="meta">Email: <strong>{{ $subscriber->email }}</strong></p>
        <p>Are you sure you want to unsubscribe? You will stop receiving future newsletters from us. You can re-subscribe any time.</p>
        <form method="POST" action="{{ route('newsletter.public.unsubscribe', ['token' => $subscriber->unsubscribe_token]) }}">
            @csrf
            <button type="submit" class="btn">Unsubscribe</button>
        </form>
    </article>
</main>
</body>
</html>
