<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Invited!</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
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
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #6b7280;
            font-size: 16px;
        }
        .content {
            margin: 30px 0;
        }
        .invite-details {
            background: #f3f4f6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .invite-details strong {
            color: #374151;
        }
        .cta-button {
            display: inline-block;
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .cta-button:hover {
            background: #4338ca;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .expiry-notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">{{ $appName }}</div>
            <h1 class="title">You're Invited!</h1>
            <p class="subtitle">{{ $invitedBy }} has invited you to join {{ $appName }}</p>
        </div>

        <div class="content">
            <p>Hello!</p>
            
            <p>You've been invited to join <strong>{{ $appName }}</strong> by <strong>{{ $invitedBy }}</strong>. We're excited to have you on board!</p>

            <div class="invite-details">
                <p><strong>Email:</strong> {{ $invite->email }}</p>
                <p><strong>Role:</strong> {{ ucfirst($invite->role) }}</p>
                <p><strong>Invited by:</strong> {{ $invitedBy }}</p>
            </div>

            <div class="expiry-notice">
                <strong>⏰ Important:</strong> This invitation expires on {{ $invite->expires_at->format('F j, Y \a\t g:i A') }}
            </div>

            <div style="text-align: center;">
                <a href="{{ $acceptUrl }}" class="cta-button">Accept Invitation</a>
            </div>

            <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #4f46e5;">{{ $acceptUrl }}</p>

            <p>If you didn't expect this invitation or believe it was sent in error, you can safely ignore this email.</p>
        </div>

        <div class="footer">
            <p>This invitation was sent by {{ $appName }}.</p>
            <p>If you have any questions, please contact your administrator.</p>
        </div>
    </div>
</body>
</html>
