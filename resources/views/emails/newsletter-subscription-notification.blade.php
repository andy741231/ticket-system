<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Newsletter Subscription</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Source Sans 3', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8fafc;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .header {
            background-color: #c8102e;
            color: white;
            padding: 14px 18px;
            border-radius: 8px;
            font-weight: 700;
        }
        .meta {
            margin-top: 18px;
            background: #f3f4f6;
            border-radius: 6px;
            padding: 14px;
        }
        .meta p {
            margin: 6px 0;
        }
        .cta {
            margin-top: 18px;
            text-align: center;
        }
        .cta a {
            display: inline-block;
            background: #111827;
            color: white;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        .footer {
            margin-top: 22px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">New Newsletter Subscription</div>

        <p>A new user has subscribed to the newsletter.</p>

        <div class="meta">
            <p><strong>Email:</strong> {{ $subscriber->email }}</p>
            <p><strong>Name:</strong> {{ $subscriber->full_name }}</p>
            <p><strong>Organization:</strong> {{ $subscriber->organization ?? '' }}</p>
            <p><strong>Subscribed at:</strong> {{ optional($subscriber->subscribed_at)->format('F j, Y g:i A') ?? '' }}</p>
        </div>

        <div class="cta">
            <a href="{{ $subscriberUrl }}">View Subscriber</a>
        </div>

        <div class="footer">
            This is an automated notification from The Hub.
        </div>
    </div>
</body>
</html>
