<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
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
        .info-box {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
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
        <h1 style="margin: 0;">🎨 Annotation Access</h1>
    </div>
    
    <div class="content">
        <p>Hi {{ $externalUser->name }},</p>
        
        @if($invitedByName)
            <p><strong>{{ $invitedByName }}</strong> has invited you to collaborate on an annotation.</p>
        @else
            <p>You've been invited to access and collaborate on an annotation.</p>
        @endif
        
        @if($context)
            <div class="info-box">
                <strong>Context:</strong><br>
                {{ $context }}
            </div>
        @endif
        
        <p>To access the annotation and start collaborating, please verify your email address by clicking the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Verify Email & Access Annotation</a>
        </div>
        
        <p style="font-size: 14px; color: #6b7280;">
            Or copy and paste this link into your browser:<br>
            <a href="{{ $verificationUrl }}" style="color: #667eea; word-break: break-all;">{{ $verificationUrl }}</a>
        </p>
        
        <div class="info-box">
            <strong>What you can do:</strong>
            <ul style="margin: 10px 0;">
                <li>Add annotations and comments</li>
                <li>Mention other users with @email</li>
                <li>Edit and delete your own content</li>
                <li>Collaborate in real-time</li>
            </ul>
        </div>
        
        <p style="font-size: 14px; color: #6b7280;">
            This verification link will expire in 24 hours. If you didn't expect this email, you can safely ignore it.
        </p>
    </div>
    
    <div class="footer">
        <p>This is an automated message from the Ticket System.<br>
        Please do not reply to this email.</p>
    </div>
</body>
</html>
