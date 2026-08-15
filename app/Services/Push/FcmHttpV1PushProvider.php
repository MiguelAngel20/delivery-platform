<?php

namespace App\Services\Push;

use App\Contracts\PushProvider;
use App\Enums\NotificationPriority;
use App\Support\PushMessage;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class FcmHttpV1PushProvider implements PushProvider
{
    public function send(string $token, PushMessage $message): array
    {
        return $this->sendToMany([$token], $message);
    }

    public function sendToMany(array $tokens, PushMessage $message): array
    {
        $tokens = array_values(array_filter($tokens));

        if ($tokens === []) {
            return ['sent' => 0, 'failed' => 0, 'invalidated' => []];
        }

        $sent = 0;
        $failed = 0;
        $invalidated = [];

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($this->accessToken())
                    ->timeout(10)
                    ->post($this->endpoint(), [
                        'message' => $this->payload($token, $message),
                    ]);

                if ($response->successful()) {
                    $sent++;

                    continue;
                }

                $errorCode = (string) data_get($response->json(), 'error.status', '');
                $errorMessage = (string) data_get($response->json(), 'error.message', $response->body());

                if ($this->isInvalidToken($errorCode, $errorMessage)) {
                    $invalidated[] = $token;
                } else {
                    $failed++;
                    Log::warning('FCM send failed', [
                        'status' => $response->status(),
                        'error' => $errorMessage,
                    ]);
                }
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('FCM send exception', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return compact('sent', 'failed', 'invalidated');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $token, PushMessage $message): array
    {
        $ttl = $message->ttlSeconds
            ?? (int) config('push.ttl.default_seconds', 86400);

        $data = [];
        foreach ($message->data as $key => $value) {
            $data[(string) $key] = (string) $value;
        }

        return [
            'token' => $token,
            'notification' => [
                'title' => $message->title,
                'body' => $message->body,
            ],
            'data' => $data,
            'android' => [
                'priority' => $message->priority === NotificationPriority::High ? 'HIGH' : 'NORMAL',
                'ttl' => "{$ttl}s",
            ],
            'webpush' => [
                'headers' => [
                    'TTL' => (string) $ttl,
                    'Urgency' => $message->priority === NotificationPriority::High ? 'high' : 'normal',
                ],
                'notification' => [
                    'title' => $message->title,
                    'body' => $message->body,
                ],
                'fcm_options' => array_filter([
                    'link' => $data['click_path'] ?? null,
                ]),
            ],
        ];
    }

    private function endpoint(): string
    {
        $projectId = (string) config('push.fcm.project_id');

        if ($projectId === '') {
            throw new RuntimeException('FIREBASE_PROJECT_ID no configurado.');
        }

        return "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm:access_token', 3300, function (): string {
            $credentials = $this->serviceAccountCredentials();
            $token = $credentials->fetchAuthToken();

            $accessToken = $token['access_token'] ?? null;

            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('No se pudo obtener access token de Firebase.');
            }

            return $accessToken;
        });
    }

    private function serviceAccountCredentials(): ServiceAccountCredentials
    {
        $json = (string) config('push.fcm.credentials_json');
        $path = (string) config('push.fcm.credentials');

        if ($json !== '') {
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                throw new RuntimeException('FIREBASE_CREDENTIALS_JSON inválido.');
            }

            return new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $decoded,
            );
        }

        if ($path !== '' && is_readable($path)) {
            return new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $path,
            );
        }

        throw new RuntimeException('Credenciales Firebase no configuradas.');
    }

    private function isInvalidToken(string $status, string $message): bool
    {
        $haystack = strtoupper($status.' '.$message);

        return str_contains($haystack, 'UNREGISTERED')
            || str_contains($haystack, 'INVALID_ARGUMENT')
            || str_contains($haystack, 'NOT_FOUND')
            || str_contains($haystack, 'REGISTRATION-TOKEN-NOT-REGISTERED');
    }
}
