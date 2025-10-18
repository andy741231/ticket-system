<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You were mentioned in an annotation</title>
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
            background-color: #3b82f6;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .annotation-box {
            background-color: white;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .comment-box {
            background-color: #eff6ff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border: 1px solid #bfdbfe;
        }
        .button {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .mention {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 24px;">📌 You were mentioned in an annotation</h1>
    </div>
    
    <div class="content">
        <p>Hi <strong>{{ $mentionedUser->name }}</strong>,</p>
        
        <p><strong>{{ $mentioningUser->name }}</strong> mentioned you in a comment on {{ $annotation ? 'an annotation' : 'an image' }} in ticket <strong>#{{ $ticket->id }}: {{ $ticket->title }}</strong></p>
        
        @if($annotation && $annotation->content)
        <div class="annotation-box">
            <strong>Annotation:</strong>
            <p style="margin: 10px 0 0 0;">{{ $annotation->content }}</p>
        </div>
        @endif
        
        <div class="comment-box">
            <strong>{{ $mentioningUser->name }}'s comment:</strong>
            <p style="margin: 10px 0 0 0;">{!! nl2br(e($comment->content)) !!}</p>
        </div>
        
        <p>Click the button below to view the annotation and respond:</p>
        
        <a href="{{ $annotationUrl }}" class="button">View Annotation</a>
        
        <p style="font-size: 14px; color: #6b7280; margin-top: 30px;">
            This notification was sent because you were mentioned in an annotation comment. 
            You can view all your notifications in your account settings.
        </p>
    </div>
    
    <div class="footer">
        <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        <p style="font-size: 12px;">
            If you have any questions, please contact support.
        </p>
    </div>
</body>
</html>
