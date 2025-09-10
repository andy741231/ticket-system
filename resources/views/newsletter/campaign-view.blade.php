<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->subject }} — Newsletter</title>
    <meta name="robots" content="index,follow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <script>
      window.addEventListener('load', function() {
        // Function to send height to parent
        function sendHeight() {
          const height = document.documentElement.offsetHeight;
          window.parent.postMessage({ 
            type: 'setHeight', 
            height: height 
          }, '*');
        }

        // Listen for height requests
        window.addEventListener('message', function(event) {
          if (event.data.type === 'getHeight') {
            sendHeight();
          }
        });

        // Initial height
        sendHeight();
      });
    </script>
    <style>
        body { font-family: 'Source Sans 3', Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji", sans-serif; margin: 0; background: #f9fafb; }
        .container { max-width: 860px; margin: 0 auto; padding: 24px; }
        .card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; }
        .title { font-size: 1.75rem; font-weight: 800; margin: 0 0 6px; }
        .meta { color: #6b7280; font-size: 0.95rem; margin-bottom: 16px; }
        a.back { color: #374151; text-decoration: none; font-weight: 600; }
        a.back:hover { text-decoration: underline; }
        .header-container { max-width: 600px; margin: 0 auto; padding: 24px; }
    </style>
</head>
<body class="" style="background-color: #e7e7e7;">
<main class="container" style="background-color: #e7e7e7;" role="main">
   

  <article class="card" style="background-color: #e7e7e7;" aria-labelledby="newsletter-title">
        <header class="header-container">
        @if(!request()->header('X-Inertia') && !request()->has('embed'))
        <p><a class="back" href="{{ route('newsletter.public.archive') }}">← View Archive</a></p>
        @endif
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
