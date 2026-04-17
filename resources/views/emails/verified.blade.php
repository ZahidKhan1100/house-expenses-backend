{{--
  Browser page shown after email verification (not an email). No external CSS — works offline and in strict environments.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark light">
  <title>Email verified — HabiMate</title>
</head>
<body style="margin:0;min-height:100vh;background-color:#0b1220;background-image:radial-gradient(ellipse 80% 50% at 50% -20%, rgba(255,106,106,0.25) 0%, transparent 55%), radial-gradient(ellipse 60% 40% at 100% 50%, rgba(46,196,182,0.12) 0%, transparent 50%);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="min-height:100vh;">
    <tr>
      <td align="center" style="padding:48px 20px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:440px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:24px;backdrop-filter:blur(12px);box-shadow:0 24px 48px rgba(0,0,0,0.35);">
          <tr>
            <td style="padding:40px 32px 32px;text-align:center;">
              <div style="width:72px;height:72px;margin:0 auto 24px;border-radius:20px;background:linear-gradient(135deg,#FF6A6A,#2EC4B6);text-align:center;line-height:72px;box-shadow:0 12px 32px rgba(255,106,106,0.35);">
                <span style="font-size:36px;color:#ffffff;">✓</span>
              </div>

              @if (($status ?? '') === 'success')
                <h1 style="margin:0 0 12px;font-size:26px;font-weight:800;color:#f8fafc;letter-spacing:-0.02em;line-height:1.2;">
                  You’re verified, {{ $name }}!
                </h1>
                <p style="margin:0;font-size:16px;line-height:1.6;color:#94a3b8;">
                  Your email is confirmed. Open the HabiMate app and settle in—your house is ready when you are.
                </p>
              @else
                <h1 style="margin:0 0 12px;font-size:24px;font-weight:800;color:#f8fafc;letter-spacing:-0.02em;">
                  Already verified
                </h1>
                <p style="margin:0;font-size:16px;line-height:1.6;color:#94a3b8;">
                  Hi {{ $name }}, your account is already active. Jump back into the app anytime.
                </p>
              @endif

              <p style="margin:32px 0 0;font-size:11px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#64748b;">
                HabiMate · Shared living, simplified
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
