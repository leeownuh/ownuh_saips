<?php
/**
 * Ownuh SAIPS — Security Headers Middleware
 * Implements CSRF protection, CSP, HSTS, and other security headers.
 * SRS §6 — Security Controls
 */

declare(strict_types=1);

namespace SAIPS\Middleware;

class SecurityHeadersMiddleware
{
    private array $config;
    private $redis;
    private ?string $nonce = null;
    
    /**
     * @param array $config Security configuration
     * @param $redis Redis client for CSRF token storage
     */
    public function __construct(array $config, $redis = null)
    {
        $this->config = $config;
        $this->redis = $redis;
    }
    
    /**
     * Apply all security headers.
     */
    public function applyHeaders(): void
    {
        $this->applyHSTS();
        $this->applyCSP();
        $this->applyXFrameOptions();
        $this->applyXContentTypeOptions();
        $this->applyXXSSProtection();
        $this->applyReferrerPolicy();
        $this->applyPermissionsPolicy();
    }
    
    /**
     * Apply HTTP Strict Transport Security (HSTS).
     * SRS §6.1 — Force HTTPS
     */
    private function applyHSTS(): void
    {
        $maxAge = $this->config['hsts_max_age'] ?? 31536000; // 1 year
        $includeSubdomains = $this->config['hsts_include_subdomains'] ?? true;
        $preload = $this->config['hsts_preload'] ?? false;
        
        $header = "max-age={$maxAge}";
        
        if ($includeSubdomains) {
            $header .= '; includeSubDomains';
        }
        
        if ($preload) {
            $header .= '; preload';
        }
        
        header("Strict-Transport-Security: {$header}");
    }
    
    /**
     * Generate a cryptographic nonce for the current request.
     * Use <?= $csp->getNonce() ?> in script/style tags instead of unsafe-inline.
     */
    public function getNonce(): string
    {
        if (!isset($this->nonce)) {
            $this->nonce = base64_encode(random_bytes(16));
        }
        return $this->nonce;
    }

    /**
     * Apply Content Security Policy (CSP).
     * SECURITY FIX: Removed 'unsafe-inline' and 'unsafe-eval' from script-src.
     * Uses per-request nonce instead. All inline scripts must use nonce attribute.
     * SRS §6.2 — Content Security
     */
    private function applyCSP(): void
    {
        $nonce = $this->getNonce();

        $directives = [
            "default-src"     => "'self'",
            // SECURITY: no unsafe-inline, no unsafe-eval — use nonce on all inline scripts
            "script-src"      => "'self' 'nonce-{$nonce}'",
            // Styles: allow inline via nonce; fonts from googleapis
            "style-src"       => "'self' 'nonce-{$nonce}' https://fonts.googleapis.com",
            "font-src"        => "'self' https://fonts.gstatic.com",
            "img-src"         => "'self' data: blob:",
            "connect-src"     => "'self'",
            "frame-ancestors" => "'none'",
            "base-uri"        => "'self'",
            "form-action"     => "'self'",
            "object-src"      => "'none'",
            "upgrade-insecure-requests" => "",
        ];

        // Allow config overrides (cannot re-add unsafe-inline/unsafe-eval)
        if (isset($this->config['csp_directives'])) {
            foreach ($this->config['csp_directives'] as $k => $v) {
                // Block attempts to re-introduce unsafe directives via config
                if (str_contains((string)$v, 'unsafe-inline') || str_contains((string)$v, 'unsafe-eval')) {
                    continue;
                }
                $directives[$k] = $v;
            }
        }

        $csp = [];
        foreach ($directives as $directive => $value) {
            $csp[] = $value !== "" ? "{$directive} {$value}" : $directive;
        }

        header("Content-Security-Policy: " . implode('; ', $csp));
    }
    
    /**
     * Apply X-Frame-Options header.
     */
    private function applyXFrameOptions(): void
    {
        $value = $this->config['x_frame_options'] ?? 'SAMEORIGIN';
        header("X-Frame-Options: {$value}");
    }
    
    /**
     * Apply X-Content-Type-Options header.
     */
    private function applyXContentTypeOptions(): void
    {
        header('X-Content-Type-Options: nosniff');
    }
    
    /**
     * Apply X-XSS-Protection header (legacy, but still useful).
     */
    private function applyXXSSProtection(): void
    {
        header('X-XSS-Protection: 1; mode=block');
    }
    
    /**
     * Apply Referrer-Policy header.
     */
    private function applyReferrerPolicy(): void
    {
        $policy = $this->config['referrer_policy'] ?? 'strict-origin-when-cross-origin';
        header("Referrer-Policy: {$policy}");
    }
    
    /**
     * Apply Permissions-Policy header.
     */
    private function applyPermissionsPolicy(): void
    {
        $policies = [
            'geolocation' => '()',
            'microphone' => '()',
            'camera' => '()',
            'payment' => '()',
            'usb' => '()',
        ];
        
        $header = [];
        foreach ($policies as $feature => $allow) {
            $header[] = "{$feature}={$allow}";
        }
        
        header('Permissions-Policy: ' . implode(', ', $header));
    }
    
    /**
     * Generate CSRF token for forms.
     * SRS §6.3 — CSRF Protection
     */
    public function generateCsrfToken(string $sessionId = null): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        
        // Store in session
        $_SESSION['csrf_token'] = $tokenHash;
        $_SESSION['csrf_token_time'] = time();
        
        // Also store in Redis if available (for distributed sessions)
        if ($this->redis && $sessionId) {
            $this->redis->setex("saips:csrf:{$sessionId}", 3600, $tokenHash);
        }
        
        return $token;
    }
    
    /**
     * Validate CSRF token.
     */
    public function validateCsrfToken(?string $token, string $sessionId = null): bool
    {
        if (!$token) {
            return false;
        }
        
        $tokenHash = hash('sha256', $token);
        
        // Check session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        $tokenTime = $_SESSION['csrf_token_time'] ?? 0;
        
        // Check token age (max 1 hour)
        if (time() - $tokenTime > 3600) {
            return false;
        }
        
        // Constant-time comparison
        if ($sessionToken && hash_equals($sessionToken, $tokenHash)) {
            return true;
        }
        
        // Check Redis if available
        if ($this->redis && $sessionId) {
            $storedToken = $this->redis->get("saips:csrf:{$sessionId}");
            if ($storedToken && hash_equals($storedToken, $tokenHash)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate CSRF token from request (checks both header and body).
     */
    public function validateRequest(): bool
    {
        // Get token from header or body
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? ($_POST['csrf_token'] ?? null)
            ?? (json_decode(file_get_contents('php://input'), true)['csrf_token'] ?? null);
        
        $sessionId = session_id() ?: null;
        
        return $this->validateCsrfToken($token, $sessionId);
    }
    
    /**
     * Middleware handler - validates CSRF for state-changing requests.
     */
    public function handleCsrf(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Only check CSRF for state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return;
        }
        
        // Skip CSRF for API requests with Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return;
        }
        
        if (!$this->validateRequest()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'code' => 'CSRF_TOKEN_INVALID',
                'message' => 'CSRF token validation failed. Please refresh the page and try again.',
            ]);
            exit;
        }
    }
    
    /**
     * Get CSRF token meta tag for HTML forms.
     */
    public function getCsrfMetaTag(): string
    {
        $token = $this->generateCsrfToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get CSRF token hidden input for forms.
     */
    public function getCsrfInput(): string
    {
        $token = $this->generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get current CSRF token (for JavaScript).
     */
    public function getCurrentToken(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return $_SESSION['csrf_token'] ?? null;
    }
}

/**
 * Helper function to apply security headers globally.
 */
function apply_security_headers(array $config = [], $redis = null): void
{
    $middleware = new SecurityHeadersMiddleware($config, $redis);
    $middleware->applyHeaders();
}

/**
 * Helper function to get CSRF token.
 */
function csrf_token(): string
{
    global $securityHeadersMiddleware;
    
    if (!$securityHeadersMiddleware instanceof SecurityHeadersMiddleware) {
        $securityHeadersMiddleware = new SecurityHeadersMiddleware([]);
    }
    
    return $securityHeadersMiddleware->generateCsrfToken();
}

/**
 * Helper function to get CSRF input field.
 */
function csrf_field(): string
{
    global $securityHeadersMiddleware;
    
    if (!$securityHeadersMiddleware instanceof SecurityHeadersMiddleware) {
        $securityHeadersMiddleware = new SecurityHeadersMiddleware([]);
    }
    
    return $securityHeadersMiddleware->getCsrfInput();
}