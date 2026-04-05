<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('sendMailgunEmail')) {
    function sendMailgunEmail($to, $subject, $html, $text = null)
    {
        $domain = env('MAILGUN_DOMAIN');
        $apiKey = env('MAILGUN_SECRET');
        $from = env('MAIL_FROM_ADDRESS', 'app@habimate.com');
        $fromName = env('MAIL_FROM_NAME', 'HabiMate');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/{$domain}/messages");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "api:{$apiKey}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'from'    => "{$fromName} <{$from}>",
            'to'      => $to,
            'subject' => $subject,
            'text'    => $text ?? strip_tags($html),
            'html'    => $html,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("Mailgun cURL error: {$error}");
            return false;
        }

        Log::info("Mailgun response: {$response}");
        return true;
    }
}