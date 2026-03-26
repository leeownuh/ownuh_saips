<?php
/**
 * Ownuh SAIPS — Rate Limit Middleware
 * Redis-backed sliding window rate limiter.
 * SRS §3.3 — rate limiting per endpoint
 */

declare(strict_types=1);

namespace SAIPS\Middleware;

class RateLimitMiddleware
{
    private \Redis $redis;
    private array  $config;

    public function __construct(\Redis $redis, array $config)
    {
        $this->redis  = $redis;
        $this->config = $config;
    }

    /**
     * Check rate limit for the current request.
     * Throws/aborts with 429 if limit exceeded.
     */
    public function check(string $endpoint, string $identifier, string $scope = 'per_ip'): void
    {
        $limits = $this->config['rate_limits'];

        // Match endpoint (exact then wildcard)
        $rule = $limits[$endpoint] ?? $limits[preg_replace('#/[^/]+$#', '/*', $endpoint)] ?? null;
        if (!$rule) {
            return; // No rule configured — allow
        }

        $key    = "saips:rl:{$scope}:{$endpoint}:{$identifier}";
        $window = $rule['window'];
        $limit  = $rule['limit'];

        // Sliding window counter
        $this->redis->multi();
        $this->redis->incr($key);
        $this->redis->expire($key, $window);
        $results = $this->redis->exec();
        $count   = $results[0];

        if ($count > $limit) {
            $ttl = $this->redis->ttl($key);
            $this->abort429($ttl);
        }
    }

    /**
     * Check and apply progressive login delays per SRS §3.2
     * Returns delay in seconds (caller must sleep).
     */
    public function getProgressiveDelay(string $username): int
    {
        $cfg     = $this->config['brute_force']['progressive_delay'];
        $key     = "saips:pd:{$username}";
        $count   = (int)($this->redis->get($key) ?? 0);

        if ($count < $cfg['after_failures']) {
            return 0;
        }

        $extra   = $count - $cfg['after_failures'];
        $delay   = $cfg['initial_seconds'] * (int)pow($cfg['multiplier'], $extra);
        return min($delay, $cfg['max_seconds']);
    }

    public function recordFailure(string $username, string $ip): void
    {
        $keyUser = "saips:pd:{$username}";
        $this->redis->incr($keyUser);
        $this->redis->expire($keyUser, 86400); // 24h window for hard-lock detection
    }

    public function resetFailures(string $username): void
    {
        $this->redis->del("saips:pd:{$username}");
    }

    private function abort429(int $retryAfter): never
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header("Retry-After: {$retryAfter}");
        echo json_encode([
            'status'      => 'error',
            'code'        => 'RATE_LIMITED',
            'message'     => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ]);
        exit;
    }
}
