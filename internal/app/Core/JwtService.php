<?php declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ServerException;

class JwtService {
    private string $secret;
    private int $expiresIn;

    public function __construct(string $secret, int $expiresIn = 600) {
        if (empty($secret)) {
            throw new ServerException('JWT secret key is required');
        }
        $this->secret = $secret;
        $this->expiresIn = max(60, $expiresIn);
    }

    
    public function encode(array $payload, ?int $expiresIn = null): string {
        $issuedAt = time();
        $expiresAt = $issuedAt + ($expiresIn ?? $this->expiresIn);

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $payload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expiresAt
        ]);

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));

        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secret,
            true
        );
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    
    public function decode(string $token): ?array {
        $parts = explode('.', $token);
        $payload = null;

        if (count($parts) === 3) {
            [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

            $signature = hash_hmac(
                'sha256',
                $headerEncoded . '.' . $payloadEncoded,
                $this->secret,
                true
            );
            $expectedSignature = $this->base64UrlEncode($signature);

            if (hash_equals($expectedSignature, $signatureEncoded)) {
                $decodedPayload = json_decode(
                    $this->base64UrlDecode($payloadEncoded),
                    true
                );

                if (is_array($decodedPayload) && (!isset($decodedPayload['exp']) || $decodedPayload['exp'] >= time())) {
                    $payload = $decodedPayload;
                }
            }
        }

        return $payload;
    }

    
    public static function getBearerToken(): ?string {
        $headers = [
            (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
            (string) ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''),
            (string) ($_SERVER['Authorization'] ?? ''),
            (string) ($_SERVER['HTTP_X_AUTHORIZATION'] ?? ''),
            (string) ($_SERVER['HTTP_X_HR_TOKEN'] ?? ''),
        ];

        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (is_array($requestHeaders)) {
                foreach ($requestHeaders as $key => $value) {
                    if (strtolower((string) $key) === 'authorization') {
                        $headers[] = (string) $value;
                    }
                }
            }
        }

        $header = '';
        foreach ($headers as $candidate) {
            if (trim($candidate) !== '') {
                $header = $candidate;
                break;
            }
        }

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return trim($header) !== '' ? trim($header) : null;
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string {
        return base64_decode(
            strtr($data, '-_', '+/') . str_repeat('=', 4 - (strlen($data) % 4))
        );
    }
}
