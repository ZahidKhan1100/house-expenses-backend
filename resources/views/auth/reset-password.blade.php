<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body style="font-family:Arial;background:#f5f6f8;margin:0">

<div style="max-width:420px;margin:80px auto;background:#fff;padding:24px;border-radius:12px">

    <h2 style="margin-bottom:10px">Reset your password</h2>
    <p style="color:#666">Enter your new password below</p>

    <form id="resetForm">

        <input type="password" id="password"
            placeholder="New password"
            style="width:100%;padding:12px;margin:10px 0;border:1px solid #ddd;border-radius:8px"/>

        <input type="password" id="confirm"
            placeholder="Confirm password"
            style="width:100%;padding:12px;margin:10px 0;border:1px solid #ddd;border-radius:8px"/>

        <button type="submit"
            style="width:100%;padding:12px;background:#FF6A6A;color:#fff;border:none;border-radius:8px;font-weight:bold">
            Reset Password
        </button>

    </form>

    <p id="msg" style="color:green;margin-top:10px"></p>

    <hr style="margin:20px 0">

    <a id="openAppBtn"
       style="display:block;text-align:center;padding:10px;background:#eee;border-radius:8px;text-decoration:none">
        Open App (if installed)
    </a>

</div>

<script>
const params = new URLSearchParams(window.location.search);
const token = params.get("token");
const email = params.get("email");

// 🔥 Deep link
const deepLink = `habimate://reset-password?token=${token}&email=${email}`;
document.getElementById("openAppBtn").href = deepLink;

// Try open app automatically
window.location.href = deepLink;

// fallback
setTimeout(() => {
    document.getElementById("openAppBtn").innerText = "App not installed? Continue here ↓";
}, 2000);

// Submit reset password
document.getElementById("resetForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const password = document.getElementById("password").value;
    const confirm = document.getElementById("confirm").value;

    const res = await fetch("/api/v1/reset-password", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            email,
            token,
            password,
            password_confirmation: confirm
        })
    });

    const data = await res.json();

    document.getElementById("msg").innerText = data.message;
});
</script>

</body>
</html>