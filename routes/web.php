<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;



Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-mail', function () {
    try {
        Mail::raw('This is a test email from Mailgun', function ($message) {
            $message->to('kzahid416@gmail.com')
                    ->subject('Mailgun Test');
        });

        return '✅ Test email sent successfully!';
    } catch (\Exception $e) {
        return '❌ Mail sending failed: ' . $e->getMessage();
    }
});

Route::get('/reset-password', function () {
    return view('auth.reset-password', [
        'passwordResetScheme' => (string) config('houseexpenses.password_reset.mobile_scheme', 'com.ihabimate.habimate'),
        'passwordResetAndroidPackage' => (string) config('houseexpenses.password_reset.android_package', 'com.ihabimate.habimate'),
        'passwordResetApiUrl' => url('/api/v1/reset-password'),
    ]);
});