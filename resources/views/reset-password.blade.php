<!DOCTYPE html>
<html>

<head>
    <title>Reset Password</title>
</head>

<body style="font-family: Arial; text-align: center; padding: 40px;">

    <h2>Redirecting to app...</h2>
    <p>If nothing happens, click below:</p>

    <a id="openApp" href="#"
        style="
        padding:12px 20px;
        background:#FF6A6A;
        color:#fff;
        text-decoration:none;
        border-radius:6px;
    ">
        Open App
    </a>

    <script>
        const params = new URLSearchParams(window.location.search);
        const token = params.get("token");
        const email = params.get("email");

        const deepLink = `habimate://reset-password?token=${token}&email=${email}`;

        document.getElementById("openApp").href = deepLink;

        // Try open app
        window.location.href = deepLink;

        // fallback
        setTimeout(() => {
            window.location.href = "https://play.google.com/store";
        }, 2000);
    </script>

</body>

</html>
