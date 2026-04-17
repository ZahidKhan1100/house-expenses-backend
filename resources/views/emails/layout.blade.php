{{--
  HabiMate transactional email shell — table layout + inline styles for Gmail/Outlook.
  Brand: Coral #FF6A6A, Teal #2EC4B6
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>@yield('title', 'HabiMate')</title>
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <![endif]-->
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f1f5f9;-webkit-font-smoothing:antialiased;">
  @hasSection('preheader')
  <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f1f5f9;opacity:0;">
    @yield('preheader')
  </div>
  @endif

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;">
    <tr>
      <td align="center" style="padding:40px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;">
          <tr>
            <td style="border-radius:20px;overflow:hidden;background-color:#ffffff;border:1px solid #e2e8f0;box-shadow:0 4px 24px rgba(15,23,42,0.06);">
              {{-- Header --}}
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="background:linear-gradient(135deg,#FF6A6A 0%,#e85a5a 45%,#2EC4B6 100%);padding:28px 32px 24px;text-align:center;">
                    <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.02em;">HabiMate</span>
                    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:10px;font-weight:700;color:rgba(255,255,255,0.92);letter-spacing:0.22em;text-transform:uppercase;margin-top:10px;">Shared living, simplified</div>
                  </td>
                </tr>
              </table>

              {{-- Body --}}
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="padding:36px 32px 28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:1.65;color:#334155;">
                    @yield('content')
                  </td>
                </tr>
              </table>

              {{-- Footer --}}
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="padding:24px 32px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
                    <p style="margin:0 0 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:12px;line-height:1.5;color:#64748b;">
                      &copy; {{ date('Y') }} HabiMate. The referee, not the cop.
                    </p>
                    <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:11px;line-height:1.5;color:#94a3b8;">
                      @yield('footer_note', "You're receiving this email because of an action on your HabiMate account.")
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
