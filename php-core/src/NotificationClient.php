<?php

declare(strict_types=1);

/**
 * NotificationClient — fire-and-forget HTTP client for the Node.js
 * notification microservice.
 *
 * Called after a new affiliate is registered. Sends a POST request to
 * the notification service with the affiliate's details. The short
 * CURLOPT_TIMEOUT ensures the main API is never blocked if the
 * notification service is slow or unreachable.
 */
class NotificationClient
{
    /**
     * @param array $data  e.g. ['affiliate_id' => 1, 'business_name' => '...', 'email' => '...']
     */
    public static function send(array $data): void
    {
        $url = Env::get('NOTIFY_SERVICE_URL', 'http://notification-service:3000/notify');

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,   // give up connecting after 2 s
            CURLOPT_TIMEOUT        => 3,   // total request timeout 3 s
        ]);

        // Intentionally ignore the response — this is a best-effort notification.
        // If the service is down, the affiliate registration still succeeds.
        curl_exec($ch);
        curl_close($ch);
    }
}
