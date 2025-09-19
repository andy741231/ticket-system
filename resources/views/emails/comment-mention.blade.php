<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You were mentioned in a comment</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .ticket-info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .comment-content {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #2196f3;
        }
        .mention {
            background-color: #bbdefb;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        .button {
            display: inline-block;
            background-color: #2196f3;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💬 You were mentioned in a comment</h1>
        <p>Hello {{ $mentionedUser->name }},</p>
        <p><strong>{{ $mentioningUser->name }}</strong> mentioned you in a comment on ticket <strong>#{{ $ticket->id }}</strong>.</p>
    </div>

    <div class="ticket-info">
        <h3>📋 Ticket Details</h3>
        <p><strong>Title:</strong> {{ $ticket->title }}</p>
        <p><strong>Status:</strong> {{ $ticket->status }}</p>
        <p><strong>Priority:</strong> {{ $ticket->priority }}</p>
        @if($ticket->due_date)
            <p><strong>Due Date:</strong> {{ $ticket->due_date->format('M j, Y') }}</p>
        @endif
    </div>

    <div class="comment-content">
        <h4>💭 Comment from {{ $mentioningUser->name }}</h4>
        <p><strong>Posted:</strong> {{ $comment->created_at->format('M j, Y \a\t g:i A') }}</p>
        <div style="margin-top: 10px;">
            {!! nl2br(e($comment->body)) !!}
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $commentUrl }}" class="button" style="font-size: 16px; font-weight: bold;">
            💬 View Comment & Reply
        </a>
        <p style="margin-top: 10px; font-size: 12px; color: #666;">
            Click the button above to view the comment and join the conversation
        </p>
    </div>

    <div class="footer">
        <p>This email was sent because you were mentioned in a comment. You can view the full conversation and reply by clicking the link above.</p>
        <p>If you no longer wish to receive these notifications, you can update your preferences in your account settings.</p>
    </div>
</body>
</html>
