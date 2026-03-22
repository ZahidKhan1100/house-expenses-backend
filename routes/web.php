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