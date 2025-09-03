<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferences Updated</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Source Sans 3', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: #28a745;
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .content {
            padding: 30px;
            text-align: center;
        }
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .btn {
            background-color: #c8102e;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn:hover {
            background-color: #a00e26;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Preferences Updated!</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">Your newsletter preferences have been successfully updated</p>
        </div>
        
        <div class="content">
            <div class="success-icon">✓</div>
            <h2>Thank you!</h2>
            <p>Your newsletter preferences have been updated successfully. You will receive future newsletters based on your updated preferences.</p>
            
            <div style="margin-top: 30px;">
                <a href="{{ route('newsletter.public.preferences', $subscriber->unsubscribe_token) }}" class="btn">Update Again</a>
                <a href="{{ route('newsletter.public.archive') }}" class="btn">View Newsletter Archive</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 UH Population Health. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
