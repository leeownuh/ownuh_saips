<?php
/**
 * Ownuh SAIPS — IP Check Middleware
 * Checks IP against blocked list, geo-rules, and threat intelligence.
 * SRS §3.3 — IP reputation and geo-blocking
 */

declare(strict_types=1);

namespace SAIPS\Middleware;

class IpCheckMiddleware
{
    private \PDO   $db;
    private \Redis $redis;
    private array  $config;

    public function __construct(\PDO $db, \Redis $redis, array $config)
    {
        $this->db     = $db;
        $this->redis  = $redis;
        $this->config = $config;
    }

    /**
     * Full IP check pipeline.
     * Aborts request silently (generic error) if blocked.
     */
    public function check(string $ip, ?string $userRole = null): void
    {
        // 1. Check local blocked_ips table
        if ($this->isLocallyBlocked($ip)) {
            $this->abortGeneric();
        }

        // 2. Check Redis cache for IP reputation (fast path)
        $cacheKey    = "saips:iprep:{$ip}";
        $cachedScore = $this->redis->get($cacheKey);

        if ($cachedScore !== false) {
            if ((int)$cachedScore >= 90) {
                $this->abortGeneric();
            }
        } else {
            // 3. Check threat intel feeds (cached for 6h)
            $score = $this->checkThreatIntelligence($ip);
            $this->redis->setex($cacheKey, 21600, (string)$score);
            if ($score >= 90) {
                $this->blockIp($ip, 'threat_feed', null, 60);
                $this->abortGeneric();
            }
        }

        // 4. Geo-block check
        $country = $this->getCountryCode($ip);
        if ($country && $this->isGeoBlocked($country)) {
            // Log to audit but give no indication why to the user
            $this->logGeoBlock($ip, $country);
            $this->abortGeneric();
        }

        // 5. Tor exit node check
        if ($this->isTorExitNode($ip)) {
            if ($userRole && in_array($userRole, ['admin', 'superadmin'])) {
                // Admins: block entirely
                $this->blockIp($ip, 'tor_exit', null, 0); // permanent
                $this->abortGeneric();
            }
            // Standard users: flag for enhanced MFA (handled by risk engine)
        }
    }

    public function isLocallyBlocked(string $ip): bool
    {
        // Direct query instead of stored procedure for better PDO compatibility
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM blocked_ips 
             WHERE ip_address = ? 
             AND (expires_at IS NULL OR expires_at > NOW())
             AND unblocked_at IS NULL'
        );
        $stmt->execute([$ip]);
        return (bool)$stmt->fetchColumn();
    }

    public function blockIp(string $ip, string $type, ?string $rule, int $durationMinutes): void
    {
        $expires = $durationMinutes > 0
            ? date('Y-m-d H:i:s', time() + $durationMinutes * 60)
            : null;

        $stmt = $this->db->prepare(
            'INSERT INTO blocked_ips (ip_address, block_type, trigger_rule, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             blocked_at = NOW(), expires_at = VALUES(expires_at), unblocked_at = NULL'
        );
        $stmt->execute([$ip, $type, $rule, $expires]);

        // Invalidate Redis cache
        $this->redis->del("saips:iprep:{$ip}");
    }

    public function isTorExitNode(string $ip): bool
    {
        // Check Redis-cached Tor list (updated daily)
        return (bool)$this->redis->sIsMember('saips:tor_exits', $ip);
    }

    public function getCountryCode(string $ip): ?string
    {
        // MaxMind GeoLite2 lookup (implementation via mmdb-reader or geoip2 extension)
        // Returns ISO 3166-1 alpha-2 country code
        if (function_exists('geoip_country_code_by_name')) {
            return geoip_country_code_by_name($ip) ?: null;
        }
        return null; // GeoIP not available in dev
    }

    private function isGeoBlocked(string $country): bool
    {
        $key    = "saips:geo_deny:{$country}";
        $cached = $this->redis->get($key);
        if ($cached !== false) {
            return (bool)$cached;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM geo_rules WHERE country_code = ? AND rule_type = "deny"'
        );
        $stmt->execute([$country]);
        $blocked = (bool)$stmt->fetchColumn();
        $this->redis->setex($key, 3600, (string)(int)$blocked);
        return $blocked;
    }

    private function checkThreatIntelligence(string $ip): int
    {
        // AbuseIPDB check (returns 0-100 abuse confidence score)
        $apiKey = $_ENV['ABUSEIPDB_KEY'] ?? null;
        if (!$apiKey) return 0;

        $cacheKey = "saips:abuseipdb:{$ip}";
        if ($cached = $this->redis->get($cacheKey)) {
            return (int)$cached;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.abuseipdb.com/api/v2/check?ipAddress={$ip}&maxAgeInDays=90",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Key: {$apiKey}", 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 3,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data  = json_decode($response ?? '', true);
        $score = (int)($data['data']['abuseConfidenceScore'] ?? 0);
        $this->redis->setex($cacheKey, 21600, (string)$score);
        return $score;
    }

    private function logGeoBlock(string $ip, string $country): void
    {
        $stmt = $this->db->prepare(
            'CALL sp_insert_audit_log("IPS-003","Geo-Block Triggered",NULL,?,NULL,?,NULL,80,?,NULL,NULL)'
        );
        $details = json_encode(['country' => $country, 'requested_resource' => $_SERVER['REQUEST_URI'] ?? '']);
        $stmt->execute([$ip, $country, $details]);
    }

    /**
     * Generic abort — never reveals why the request was blocked (SRS §3.3).
     */
    private function abortGeneric(): never
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'code'    => 'ACCESS_DENIED',
            'message' => 'Access denied. Contact your administrator if you believe this is an error.',
        ]);
        exit;
    }
}
