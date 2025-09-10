<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Source Sans 3', Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #f9fafb; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin-top: 32px; }
        .title { font-size: 1.5rem; font-weight: 700; margin: 0 0 8px; }
        .meta { color: #6b7280; font-size: 0.95rem; margin-bottom: 16px; }
        .link { color: #c8102e; text-decoration: none; font-weight: 600; }
        .link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<main class="container">
    <article class="card" aria-labelledby="unsubscribed-title">
        <h1 class="title" id="unsubscribed-title">You're unsubscribed</h1>
        <p class="meta">{{ $subscriber->email }}</p>
        <p>You have successfully been unsubscribed from future newsletters. If this was a mistake, you can re-subscribe anytime by contacting us.</p>
        <p><a class="link" href="{{ route('newsletter.public.archive') }}">View past newsletters</a></p>
    </article>
</main>
</body>
</html>
