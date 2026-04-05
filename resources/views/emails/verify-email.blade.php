<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Your HabiMate Account</title>
  <style>
    /* Base Reset */
    body {
      margin: 0;
      padding: 0;
      background-color: #0f172a; /* Matching your Web/App background */
      font-family: 'Sora', 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    .wrapper {
      width: 100%;
      table-layout: fixed;
      background-color: #0f172a;
      padding-bottom: 40px;
    }
    .main-card {
      max-width: 600px;
      margin: 40px auto;
      background-color: #1e293b; /* Deep slate to mimic the app card */
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    }
    .header-gradient {
      background: linear-gradient(135deg, #FF6B6B 0%, #4E54C8 100%);
      padding: 40px 20px;
      text-align: center;
    }
    .content {
      padding: 40px 30px;
      text-align: center;
      color: #f1f5f9;
    }
    .logo-text {
      color: #ffffff;
      font-size: 28px;
      font-weight: 800;
      letter-spacing: -1px;
      margin: 0;
    }
    h1 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 16px;
      color: #ffffff;
    }
    p {
      font-size: 16px;
      line-height: 1.6;
      color: #94a3b8; /* Slate-400 */
      margin-bottom: 30px;
    }
    .btn-wrapper {
      margin: 35px 0;
    }
    .btn {
      background-color: #ffffff;
      color: #4E54C8 !important;
      text-decoration: none;
      font-weight: 800;
      padding: 16px 36px;
      border-radius: 14px;
      font-size: 16px;
      display: inline-block;
      transition: all 0.3s ease;
    }
    .footer {
      padding: 20px;
      font-size: 12px;
      color: #64748b;
      text-align: center;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
    .tagline {
      color: #FF6B6B;
      font-weight: 700;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-bottom: 8px;
      display: block;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="main-card">
      <div class="header-gradient">
        <p class="logo-text">HabiMate</p>
      </div>

      <div class="content">
        <span class="tagline">Shared Living. House or Away.</span>
        <h1>Welcome home, {{ $name }}!</h1>
        <p>
          You're one step away from managing your household beautifully. 
          Please confirm your email to activate your account and start syncing with your mates.
        </p>
        
        <div class="btn-wrapper">
          <a href="{{ $verificationUrl }}" class="btn">Verify Account</a>
        </div>

        <p style="font-size: 13px;">
          Or copy and paste this link into your browser:<br>
          <span style="color: #4E54C8; word-break: break-all;">{{ $verificationUrl }}</span>
        </p>
      </div>

      <div class="footer">
        If you didn't create a HabiMate account, you can safely ignore this email.<br>
        &copy; {{ date('Y') }} HabiMate Inc.
      </div>
    </div>
  </div>
</body>
</html>