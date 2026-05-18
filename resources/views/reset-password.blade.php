<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Redirect — HabiMate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $base = rtrim((string) config('houseexpenses.password_reset.web_base'), '/');
        $target = $base . '/reset-password' . (request()->getQueryString() ? '?' . request()->getQueryString() : '');
    @endphp
    <meta http-equiv="refresh" content="0;url={{ $target }}">
</head>

<body style="font-family: system-ui, sans-serif; padding: 24px;">
    <p><a href="{{ $target }}">Continue to reset password →</a></p>
</body>

</html>
