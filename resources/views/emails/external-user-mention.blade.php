<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You Were Mentioned</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
        }
        .button:hover {
            background: #5568d3;
        }
        .comment-box {
            background: #f9fafb;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0;">💬 You Were Mentioned</h1>
    </div>
    
    <div class="content">
        <p>Hi {{ $externalUser->name }},</p>
        
        <p><strong>{{ $mentionedByName }}</strong> mentioned you in an annotation comment:</p>
        
        <div class="comment-box">
            <p style="margin: 0; color: #4b5563;">{{ Str::limit($comment->content, 200) }}</p>
        </div>
        
        <p>Click the button below to view the full comment and respond:</p>
        
        <div style="text-align: center;">
            <a href="{{ $accessUrl }}" class="button">View Comment & Respond</a>
        </div>
        
        <p style="font-size: 14px; color: #6b7280;">
            Or copy and paste this link into your browser:<br>
            <a href="{{ $accessUrl }}" style="color: #667eea; word-break: break-all;">{{ $accessUrl }}</a>
        </p>
        
        @if(!$externalUser->hasActiveSession())
        <p style="font-size: 14px; color: #f59e0b; background: #fef3c7; padding: 10px; border-radius: 4px;">
            ⚠️ You'll need to verify your email again to access this annotation.
        </p>
        @endif
    </div>
    
    <div class="footer">
        <p>This is an automated message from the Ticket System.<br>
        Please do not reply to this email.</p>
    </div>
</body>
</html>
