<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ExpoPushService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $expoToken, string $title, string $body, array $data = []): void
    {
        // Expo tokens look like: ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]
        if (trim($expoToken) === '') {
            return;
        }

        Http::timeout(6)->post('https://exp.host/--/api/v2/push/send', [
            'to' => $expoToken,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
            'priority' => 'high',
        ]);
    }
}

