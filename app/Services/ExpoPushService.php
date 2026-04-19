<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    /**
     * Send to every Expo token registered for this user (multi-device: iOS + Android).
     */
    public function sendToUserDevices(?User $user, string $title, string $body, array $data = []): void
    {
        if (! $user) {
            return;
        }

        $tokens = $user->allExpoPushTokens();
        if ($tokens->isEmpty()) {
            return;
        }

        foreach ($tokens as $token) {
            $this->send((string) $token, $title, $body, $data);
        }
    }

    /**
     * Send via Expo Push API. Must match the channel created in the app:
     * Notifications.setNotificationChannelAsync("default", ...).
     *
     * @param  array<string, mixed>  $data
     */
    public function send(string $expoToken, string $title, string $body, array $data = []): void
    {
        if (trim($expoToken) === '') {
            return;
        }

        $message = [
            'to' => $expoToken,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
            'priority' => 'high',
            // Android 8+: must match an existing channel id from the client, or notification may not show.
            'channelId' => 'default',
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://exp.host/--/api/v2/push/send', $message);

            if (! $response->successful()) {
                Log::warning('Expo push HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $json = $response->json();
            $tickets = $json['data'] ?? null;

            if (! is_array($tickets)) {
                return;
            }

            // Single-message request returns one ticket object; batch returns array of tickets.
            $ticketList = isset($tickets['status']) ? [$tickets] : $tickets;

            foreach ($ticketList as $ticket) {
                $status = $ticket['status'] ?? '';

                if ($status === 'ok') {
                    // Receipt ID is for https://exp.host/--/api/v2/push/getReceipts — confirms FCM/APNs handoff.
                    Log::info('Expo push ticket ok (queued for delivery)', [
                        'receipt_id' => $ticket['id'] ?? null,
                    ]);

                    continue;
                }

                if ($status === 'error') {
                    Log::warning('Expo push ticket error', [
                        'message' => $ticket['message'] ?? null,
                        'details' => $ticket['details'] ?? null,
                        'full' => $ticket,
                    ]);
                }
            }

            if (! empty($json['errors'])) {
                Log::warning('Expo push request errors', ['errors' => $json['errors']]);
            }
        } catch (\Throwable $e) {
            Log::warning('Expo push exception: '.$e->getMessage());
        }
    }
}
