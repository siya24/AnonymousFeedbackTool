<?php declare(strict_types=1);

namespace App\Core;

class JwtService {
    private string $secret;
    private int $expiresIn = 86400; 

    public function __construct(string $secret) {
        if (empty($secret)) {
            throw new \RuntimeException('JWT secret key is required');
        }
        $this->secret = $secret;
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
        
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secret,
            true
        );
        $expectedSignature = $this->base64UrlEncode($signature);

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return null;
        }

        
        $payload = json_decode(
            $this->base64UrlDecode($payloadEncoded),
            true
        );

        if (!is_array($payload)) {
            return null;
        }

        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    
    public static function getBearerToken(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            return null;
        }

        return $matches[1];
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
