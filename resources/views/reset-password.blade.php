<!DOCTYPE html>
<html>
<head>
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="font-family: Arial; text-align: center; padding: 40px;">

  <h2>Opening HabiMate...</h2>
  <p>If nothing happens, tap below:</p>

  <a id="openApp"
     style="padding:12px 20px;background:#FF6A6A;color:#fff;text-decoration:none;border-radius:6px;">
     Open App
  </a>

  <script>
    const params = new URLSearchParams(window.location.search);
    const token = params.get("token");
    const email = params.get("email");

    const deepLink = `habimate://reset-password?token=${token}&email=${email}`;

    const userAgent = navigator.userAgent || navigator.vendor;

    const isIOS = /iPad|iPhone|iPod/.test(userAgent);
    const isAndroid = /android/i.test(userAgent);

    // Set button link
    document.getElementById("openApp").href = deepLink;

    // Try opening app
    window.location = deepLink;

    // Fallback logic
    setTimeout(() => {
      if (isIOS) {
        window.location = "https://apps.apple.com/app/idYOUR_APP_ID";
      } else if (isAndroid) {
        window.location = "https://play.google.com/store/apps/details?id=YOUR_PACKAGE_NAME";
      } else {
        window.location = "https://habimate.com";
      }
    }, 2000);
  </script>

</body>
</html>