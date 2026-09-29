<?php

namespace App\Services\Auth;

use App\Contracts\FirebaseIdTokenVerifier;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GoogleFirebaseIdTokenVerifier implements FirebaseIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function verify(string $idToken): VerifiedFirebasePhone
    {
        $projectId = $this->projectId();
        $token = trim($idToken);

        if ($projectId === '' || $token === '') {
            throw new FirebaseIdTokenException('invalid');
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = 60;

        try {
            $payload = JWT::decode($token, $this->keys());
        } catch (ExpiredException) {
            throw new FirebaseIdTokenException('expired');
        } catch (FirebaseIdTokenException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new FirebaseIdTokenException('invalid');
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $audience = $payload->aud ?? null;
        $issuer = (string) ($payload->iss ?? '');
        $subject = (string) ($payload->sub ?? '');
        $phone = (string) ($payload->phone_number ?? '');
        $provider = (string) data_get($payload, 'firebase.sign_in_provider', '');

        if ($audience !== $projectId || $issuer !== 'https://securetoken.google.com/'.$projectId) {
            throw new FirebaseIdTokenException('invalid');
        }

        if ($subject === '' || $provider !== 'phone' || ! str_starts_with($phone, '+')) {
            throw new FirebaseIdTokenException('invalid');
        }

        return new VerifiedFirebasePhone($phone, $subject);
    }

    private function projectId(): string
    {
        $projectId = (string) config('push.fcm.project_id', '');

        if ($projectId !== '') {
            return $projectId;
        }

        return (string) config('push.web.project_id', '');
    }

    /**
     * @return array<string, Key>
     */
    private function keys(): array
    {
        $certificates = Cache::remember('firebase:securetoken:certs', 3600, function (): array {
            try {
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->get(self::CERTS_URL);
            } catch (Throwable) {
                throw new FirebaseIdTokenException('unavailable');
            }

            if (! $response->successful()) {
                throw new FirebaseIdTokenException('unavailable');
            }

            $decoded = $response->json();

            return is_array($decoded) ? $decoded : [];
        });

        $keys = [];

        foreach ($certificates as $keyId => $certificate) {
            if (! is_string($keyId) || ! is_string($certificate) || $certificate === '') {
                continue;
            }

            $keys[$keyId] = new Key($certificate, 'RS256');
        }

        if ($keys === []) {
            Cache::forget('firebase:securetoken:certs');

            throw new FirebaseIdTokenException('unavailable');
        }

        return $keys;
    }
}
