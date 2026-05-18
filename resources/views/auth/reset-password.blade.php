<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Reset Password — HabiMate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body style="font-family: system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f6f8;margin:0;color:#0f172a;">
    @php
        $tokenQs = request()->query('token');
        $emailQs = request()->query('email');
        $hasParams = filled($tokenQs) && filled($emailQs);
    @endphp

    <div
        style="max-width:420px;margin:24px auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 24px rgba(15,23,42,.08)">
        <p style="margin:0 0 8px;color:#64748b;font-size:13px;text-transform:uppercase;letter-spacing:.06em">HabiMate</p>
        <h1 style="margin:0 0 8px;font-size:22px;font-weight:800">Reset your password</h1>

        @if (!$hasParams)
            <p style="color:#b91c1c;line-height:1.5;margin:16px 0 0;">This link is missing a reset token or email. Open the link from your
                password-reset email again, or request a new reset from the app.</p>
        @else
            <div style="margin:20px 0;padding:16px;background:#fafafa;border:1px solid #e2e8f0;border-radius:10px">
                <p style="margin:0 0 12px;color:#334155;font-size:15px;line-height:1.5"><strong>Tips:</strong></p>
                <ul style="margin:0 0 14px;padding-left:18px;color:#475569;font-size:14px;line-height:1.45;">
                    <li><strong>Use the mobile app?</strong> Tap <strong>Open in HabiMate</strong>.</li>
                    <li><strong>Use a PC or no app?</strong> Enter your new password below on this page.</li>
                </ul>
                <button type="button" id="openAppBtn"
                    style="width:100%;padding:13px;background:#FF6A6A;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer">
                    Open in HabiMate app
                </button>
                <p id="appHint" style="margin:10px 0 0;font-size:12px;color:#64748b;line-height:1.4;">
                    Already installed — you may get a blank tab in some browsers before the app opens. That is normal.</p>
            </div>

            <hr style="border:none;border-top:1px solid #e2e8f0;margin:22px 0">

            <h2 style="font-size:15px;margin:0 0 14px;color:#475569;font-weight:700">Continue in browser</h2>

            <form id="resetForm">

                <label for="password" style="display:block;font-size:12px;color:#64748b;margin:0 0 4px;">New password</label>
                <input type="password" id="password" placeholder="Minimum 6 characters" autocomplete="new-password"
                    style="width:100%;padding:11px;margin:0 0 14px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;font-size:15px" />

                <label for="confirm" style="display:block;font-size:12px;color:#64748b;margin:0 0 4px;">Confirm password</label>
                <input type="password" id="confirm" placeholder="Re-enter password" autocomplete="new-password"
                    style="width:100%;padding:11px;margin:0 0 14px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;font-size:15px" />

                <button type="submit"
                    style="width:100%;padding:12px;background:#64748b;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer">
                    Reset password in browser
                </button>

            </form>

            <p id="msg" style="margin-top:14px;font-size:14px;line-height:1.45;"></p>

            <script>
                const cfg = @json([
                    'scheme' => $passwordResetScheme,
                    'androidPackage' => $passwordResetAndroidPackage,
                    'token' => request()->query('token'),
                    'email' => request()->query('email'),
                    'apiUrl' => $passwordResetApiUrl,
                ]);

                function buildCustomSchemeUrl() {
                    const q = new URLSearchParams({
                        token: cfg.token,
                        email: cfg.email,
                    }).toString();
                    return `${cfg.scheme}://reset-password?${q}`;
                }

                /** Android Chrome often handles intent:// better than naked custom schemes when coming from HTTPS. */
                function buildAndroidIntentUrl() {
                    const q = new URLSearchParams({
                        token: cfg.token,
                        email: cfg.email,
                    }).toString();
                    const fallback = encodeURIComponent(window.location.href);
                    return (
                        `intent://reset-password?${q}` +
                        `#Intent;scheme=${cfg.scheme};package=${cfg.androidPackage};` +
                        `S.browser_fallback_url=${fallback};end`
                    );
                }

                function openMobileApp(e) {
                    if (e) e.preventDefault();
                    const ua = navigator.userAgent || '';
                    const isAndroid = /android/i.test(ua);

                    window.location.href = isAndroid ? buildAndroidIntentUrl() : buildCustomSchemeUrl();
                    return false;
                }

                const openBtn = document.getElementById('openAppBtn');
                if (openBtn) openBtn.addEventListener('click', openMobileApp);

                // Gentle auto-open on phones (respects same behavior as tapping the button).
                (function maybeAutoOpen() {
                    const ua = navigator.userAgent || '';
                    if (!/Mobile|Android|iPhone|iPad|iPod/i.test(ua)) return;
                    // Small delay so the page renders and user sees controls if the app does not launch.
                    setTimeout(() => openMobileApp(null), 400);
                })();

                document.getElementById('resetForm').addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const password = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm').value;
                    const msg = document.getElementById('msg');
                    msg.textContent = '';

                    try {
                        const res = await fetch(cfg.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                email: cfg.email,
                                token: cfg.token,
                                password,
                                password_confirmation: confirm
                            })
                        });

                        const data = await res.json();

                        if (!res.ok) {
                            msg.style.color = '#b91c1c';
                            msg.textContent =
                                typeof data.message === 'string' ? data.message :
                                (data.errors ? JSON.stringify(data.errors) : 'Could not reset password.');
                            return;
                        }

                        msg.style.color = '#047857';
                        msg.textContent = data.message ||
                            'Password updated. Sign in again in the HabiMate app with your new password.';
                    } catch (err) {
                        msg.style.color = '#b91c1c';
                        msg.textContent = 'Could not reach the server. Try again or use Wi‑Fi / mobile data.';
                    }
                });
            </script>
        @endif
    </div>
</body>

</html>
