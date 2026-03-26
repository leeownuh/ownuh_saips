<?php
/**
 * Ownuh SAIPS — JWT Auth Middleware
 * Validates RS256 JWT access tokens on every protected API request.
 * SRS §3.4 — session management
 */

declare(strict_types=1);

namespace SAIPS\Middleware;

class AuthMiddleware
{
    private string $publicKeyPath;
    private array  $config;

    public function __construct(array $config)
    {
        $this->config        = $config;
        $this->publicKeyPath = $config['jwt']['public_key_path'];
    }

    /**
     * Validate the Bearer JWT from the Authorization header.
     * Returns decoded payload on success; throws on failure.
     */
    public function validate(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            $this->abort(401, 'UNAUTHORIZED', 'Missing or malformed Authorization header.');
        }

        $token = substr($header, 7);
        $payload = $this->verifyJwt($token);

        // Check expiry
        if ($payload['exp'] < time()) {
            $this->abort(401, 'TOKEN_EXPIRED', 'Access token has expired. Please refresh.');
        }

        // Check issuer
        if (($payload['iss'] ?? '') !== $this->config['jwt']['issuer']) {
            $this->abort(401, 'INVALID_TOKEN', 'Token issuer mismatch.');
        }

        return $payload;
    }

    /**
     * Require a minimum role level.
     * Roles in ascending privilege: user < manager < admin < superadmin
     */
    public function requireRole(array $payload, string $minRole): void
    {
        $hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
        $userLevel = $hierarchy[$payload['role']] ?? 0;
        $required  = $hierarchy[$minRole] ?? 999;

        if ($userLevel < $required) {
            $this->abort(403, 'FORBIDDEN', 'Insufficient privileges for this action.');
        }
    }

    private function verifyJwt(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            $this->abort(401, 'INVALID_TOKEN', 'Malformed JWT structure.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $publicKey = openssl_pkey_get_public('file://' . $this->publicKeyPath);
        if (!$publicKey) {
            $this->abort(500, 'SERVER_ERROR', 'Unable to load public key.');
        }

        $data      = $headerB64 . '.' . $payloadB64;
        $signature = base64_decode(strtr($signatureB64, '-_', '+/'));
        $verified  = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            $this->abort(401, 'INVALID_TOKEN', 'JWT signature verification failed.');
        }

        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
        if (!$payload) {
            $this->abort(401, 'INVALID_TOKEN', 'Unable to decode JWT payload.');
        }

        return $payload;
    }

    private function abort(int $status, string $code, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'code' => $code, 'message' => $message]);
        exit;
    }
}
