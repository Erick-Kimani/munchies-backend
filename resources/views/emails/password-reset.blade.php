<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { padding: 20px; }
        .code { font-size: 28px; font-weight: bold; color: #dc3545; text-align: center; margin: 20px 0; letter-spacing: 5px; }
        .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>

        <div class="content">
            <p>Hi {{ $user->name }},</p>

            <p>We received a request to reset your Munchies account password. Use the code below to reset it:</p>

            <div class="code">{{ $code }}</div>

            <p>This code will expire in 30 minutes.</p>

            <p>If you didn't request this, please ignore this email and your password will remain unchanged.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Munchies. All rights reserved.</p>
        </div>
    </div>
</body>
</html>