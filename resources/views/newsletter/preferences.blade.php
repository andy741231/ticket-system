<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Newsletter Preferences</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Source Sans 3', Roboto, Helvetica, Arial, sans-serif;
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
            background: #c8102e;
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
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus {
            outline: none;
            border-color: #c8102e;
        }
        .checkbox-group {
            margin-top: 10px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-weight: normal;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
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
            margin-right: 10px;
        }
        .btn:hover {
            background-color: #a00e26;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
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
            <h1>Update Your Preferences</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">Manage your newsletter subscription</p>
        </div>
        
        <div class="content">
            <form method="POST" action="{{ route('newsletter.public.preferences.update', $subscriber->unsubscribe_token) }}">
                @csrf
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ $subscriber->email }}" readonly style="background-color: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $subscriber->first_name) }}">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $subscriber->last_name) }}">
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $subscriber->name) }}">
                </div>
                
                @if($groups->count() > 0)
                <div class="form-group">
                    <label>Newsletter Groups</label>
                    <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Select which newsletter groups you'd like to receive:</p>
                    <div class="checkbox-group">
                        @foreach($groups as $group)
                        <label>
                            <input type="checkbox" name="groups[]" value="{{ $group->id }}" 
                                {{ $subscriber->groups->contains($group->id) ? 'checked' : '' }}>
                            <strong>{{ $group->name }}</strong>
                            @if($group->description)
                                <br><span style="color: #666; font-size: 14px; margin-left: 22px;">{{ $group->description }}</span>
                            @endif
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn">Update Preferences</button>
                    <a href="{{ route('newsletter.public.unsubscribe', $subscriber->unsubscribe_token) }}" class="btn btn-secondary">Unsubscribe</a>
                </div>
            </form>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 UH Population Health. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
