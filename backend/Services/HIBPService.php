<?php
/**
 * Ownuh SAIPS — HIBP (Have I Been Pwned) Integration Service
 * Uses k-anonymity API to check passwords against known breaches.
 * SRS §2.2 — Password Policy (breached password check)
 * 
 * API Documentation: https://haveibeenpwned.com/API/v3#PwnedPasswords
 */

declare(strict_types=1);

namespace SAIPS\Services;

class HIBPService
{
    private const API_BASE = 'https://api.pwnedpasswords.com';
    private const RANGE_ENDPOINT = '/range/';
    private const USER_AGENT = 'Ownuh-SAIPS-Password-Check/1.0';
    private const TIMEOUT = 10;
    
    private int $cacheTtl;
    private $redis;
    
    public function __construct($redis = null, int $cacheTtl = 86400)
    {
        $this->redis = $redis;
        $this->cacheTtl = $cacheTtl;
    }
    
    /**
     * Check if a password has been breached using k-anonymity.
     * Returns the count of times the password appears in breaches.
     * 
     * @param string $password The password to check
     * @return int Number of times password appears in breaches (0 = not found)
     */
    public function checkPassword(string $password): int
    {
        // Hash the password with SHA-1 (required by HIBP API)
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);
        
        // Check cache first
        if ($this->redis) {
            $cacheKey = "saips:hibp:{$prefix}";
            $cached = $this->redis->get($cacheKey);
            if ($cached !== false) {
                return $this->findSuffixInResponse($cached, $suffix);
            }
        }
        
        // Query HIBP API
        $response = $this->fetchRange($prefix);
        
        if ($response === null) {
            // API unavailable - fail open but log
            error_log("[HIBP] API unavailable for prefix {$prefix}");
            return 0;
        }
        
        // Cache the response
        if ($this->redis) {
            $this->redis->setex("saips:hibp:{$prefix}", $this->cacheTtl, $response);
        }
        
        return $this->findSuffixInResponse($response, $suffix);
    }
    
    /**
     * Check if password is compromised (appears in breaches).
     * 
     * @param string $password The password to check
     * @param int $threshold Minimum breach count to consider compromised
     * @return bool True if password appears in breaches above threshold
     */
    public function isPasswordCompromised(string $password, int $threshold = 1): bool
    {
        return $this->checkPassword($password) >= $threshold;
    }
    
    /**
     * Check password and return detailed result.
     * 
     * @param string $password The password to check
     * @return array Detailed result with breach count and recommendation
     */
    public function checkPasswordDetailed(string $password): array
    {
        $count = $this->checkPassword($password);
        
        return [
            'password' => str_repeat('*', strlen($password)), // Never return actual password
            'breach_count' => $count,
            'is_compromised' => $count > 0,
            'severity' => $this->getSeverity($count),
            'recommendation' => $this->getRecommendation($count),
        ];
    }
    
    /**
     * Batch check multiple passwords.
     * 
     * @param array $passwords Array of passwords to check
     * @return array Associative array of password hash => breach count
     */
    public function batchCheckPasswords(array $passwords): array
    {
        $results = [];
        
        // Group by hash prefix to minimize API calls
        $byPrefix = [];
        foreach ($passwords as $password) {
            $hash = strtoupper(sha1($password));
            $prefix = substr($hash, 0, 5);
            $byPrefix[$prefix][] = [
                'password' => $password,
                'suffix' => substr($hash, 5),
            ];
        }
        
        foreach ($byPrefix as $prefix => $items) {
            $response = $this->fetchRange($prefix);
            
            if ($response === null) {
                foreach ($items as $item) {
                    $results[sha1($item['password'])] = 0;
                }
                continue;
            }
            
            foreach ($items as $item) {
                $count = $this->findSuffixInResponse($response, $item['suffix']);
                $results[sha1($item['password'])] = $count;
            }
        }
        
        return $results;
    }
    
    /**
     * Fetch the hash range from HIBP API.
     */
    private function fetchRange(string $prefix): ?string
    {
        $url = self::API_BASE . self::RANGE_ENDPOINT . $prefix;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . self::USER_AGENT,
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[HIBP] cURL error: {$error}");
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("[HIBP] API returned HTTP {$httpCode}");
            return null;
        }
        
        return $response;
    }
    
    /**
     * Find suffix in the API response and return breach count.
     */
    private function findSuffixInResponse(string $response, string $suffix): int
    {
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $parts = explode(':', trim($line), 2);
            if (count($parts) === 2 && $parts[0] === $suffix) {
                return (int)$parts[1];
            }
        }
        
        return 0;
    }
    
    /**
     * Get severity level based on breach count.
     */
    private function getSeverity(int $count): string
    {
        if ($count === 0) return 'none';
        if ($count < 10) return 'low';
        if ($count < 100) return 'medium';
        if ($count < 1000) return 'high';
        return 'critical';
    }
    
    /**
     * Get recommendation based on breach count.
     */
    private function getRecommendation(int $count): string
    {
        if ($count === 0) {
            return 'This password has not been found in known data breaches.';
        }
        if ($count < 10) {
            return 'This password has been found in a small number of breaches. Consider using a different password.';
        }
        if ($count < 100) {
            return 'This password has been found in multiple breaches. Choose a different password.';
        }
        return 'This password has been found in numerous breaches and should never be used.';
    }
}