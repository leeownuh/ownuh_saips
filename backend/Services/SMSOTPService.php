<?php
/**
 * Ownuh SAIPS — SMS OTP Service (Twilio Integration)
 * Handles sending and verifying SMS-based one-time passwords.
 * SRS §2.4 — Multi-Factor Authentication (SMS OTP)
 */

declare(strict_types=1);

namespace SAIPS\Services;

class SMSOTPService
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private string $verifyServiceSid;
    private bool $enabled;
    private $redis;
    private int $otpLength;
    private int $otpTtl;
    private int $maxAttempts;
    
    /**
     * @param array $config Configuration array with Twilio credentials
     * @param $redis Redis client for caching
     */
    public function __construct(array $config, $redis = null)
    {
        $this->accountSid = $config['twilio_account_sid'] ?? $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
        $this->authToken = $config['twilio_auth_token'] ?? $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        $this->fromNumber = $config['twilio_from_number'] ?? $_ENV['TWILIO_FROM_NUMBER'] ?? '';
        $this->verifyServiceSid = $config['twilio_verify_service_sid'] ?? $_ENV['TWILIO_VERIFY_SERVICE_SID'] ?? '';
        $this->enabled = !empty($this->accountSid) && !empty($this->authToken) && !empty($this->fromNumber);
        $this->redis = $redis;
        $this->otpLength = $config['otp_length'] ?? 6;
        $this->otpTtl = $config['otp_ttl'] ?? 300; // 5 minutes
        $this->maxAttempts = $config['max_attempts'] ?? 3;
    }
    
    /**
     * Check if SMS service is configured and enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Send OTP via SMS to the specified phone number.
     * 
     * @param string $phoneNumber E.164 formatted phone number
     * @param string $userId User ID for tracking
     * @return array Result with success status and message
     */
    public function sendOTP(string $phoneNumber, string $userId): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'SMS service not configured',
                'code' => 'SMS_NOT_ENABLED',
            ];
        }
        
        // Validate phone number format (E.164)
        if (!$this->isValidPhoneNumber($phoneNumber)) {
            return [
                'success' => false,
                'error' => 'Invalid phone number format. Use E.164 format (+1234567890)',
                'code' => 'INVALID_PHONE',
            ];
        }
        
        // Check rate limiting
        if ($this->redis && !$this->checkRateLimit($userId)) {
            return [
                'success' => false,
                'error' => 'Too many OTP requests. Please wait before requesting another.',
                'code' => 'RATE_LIMITED',
            ];
        }
        
        // Use Twilio Verify service if configured (recommended)
        if ($this->verifyServiceSid) {
            return $this->sendViaVerify($phoneNumber);
        }
        
        // Fallback to custom SMS with generated OTP
        return $this->sendCustomOTP($phoneNumber, $userId);
    }
    
    /**
     * Verify OTP code.
     * 
     * @param string $phoneNumber Phone number that received the OTP
     * @param string $code OTP code to verify
     * @param string $userId User ID for tracking
     * @return array Result with success status
     */
    public function verifyOTP(string $phoneNumber, string $code, string $userId): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'SMS service not configured',
                'code' => 'SMS_NOT_ENABLED',
            ];
        }
        
        // Check attempt limit
        if ($this->redis) {
            $attemptKey = "saips:sms_attempts:{$userId}";
            $attempts = (int)$this->redis->get($attemptKey);
            
            if ($attempts >= $this->maxAttempts) {
                return [
                    'success' => false,
                    'error' => 'Maximum verification attempts exceeded',
                    'code' => 'MAX_ATTEMPTS',
                ];
            }
        }
        
        // Use Twilio Verify service if configured
        if ($this->verifyServiceSid) {
            return $this->verifyViaVerify($phoneNumber, $code, $userId);
        }
        
        // Fallback to custom verification
        return $this->verifyCustomOTP($phoneNumber, $code, $userId);
    }
    
    /**
     * Send OTP using Twilio Verify API.
     */
    private function sendViaVerify(string $phoneNumber): array
    {
        $url = "https://verify.twilio.com/v2/Services/{$this->verifyServiceSid}/Verifications";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'To' => $phoneNumber,
                'Channel' => 'sms',
            ]),
            CURLOPT_USERPWD => "{$this->accountSid}:{$this->authToken}",
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[SMS OTP] Twilio error: {$error}");
            return [
                'success' => false,
                'error' => 'Failed to send SMS',
                'code' => 'SMS_FAILED',
            ];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && ($data['status'] ?? '' === 'pending')) {
            return [
                'success' => true,
                'message' => 'OTP sent successfully',
                'sid' => $data['sid'] ?? null,
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['message'] ?? 'Failed to send SMS',
            'code' => 'SMS_FAILED',
        ];
    }
    
    /**
     * Verify OTP using Twilio Verify API.
     */
    private function verifyViaVerify(string $phoneNumber, string $code, string $userId): array
    {
        $url = "https://verify.twilio.com/v2/Services/{$this->verifyServiceSid}/VerificationCheck";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'To' => $phoneNumber,
                'Code' => $code,
            ]),
            CURLOPT_USERPWD => "{$this->accountSid}:{$this->authToken}",
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && ($data['status'] ?? '' === 'approved')) {
            // Clear attempt counter
            if ($this->redis) {
                $this->redis->del("saips:sms_attempts:{$userId}");
            }
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully',
            ];
        }
        
        // Increment attempt counter
        if ($this->redis) {
            $attemptKey = "saips:sms_attempts:{$userId}";
            $this->redis->incr($attemptKey);
            $this->redis->expire($attemptKey, $this->otpTtl);
        }
        
        return [
            'success' => false,
            'error' => 'Invalid OTP code',
            'code' => 'INVALID_OTP',
        ];
    }
    
    /**
     * Send custom OTP via Twilio SMS API.
     */
    private function sendCustomOTP(string $phoneNumber, string $userId): array
    {
        // Generate OTP
        $otp = str_pad((string)random_int(0, pow(10, $this->otpLength) - 1), $this->otpLength, '0', STR_PAD_LEFT);
        
        // Store OTP in Redis
        if ($this->redis) {
            $otpKey = "saips:sms_otp:{$userId}";
            $this->redis->setex($otpKey, $this->otpTtl, json_encode([
                'otp' => $otp,
                'phone' => $phoneNumber,
                'created_at' => time(),
            ]));
        }
        
        // Send SMS
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'From' => $this->fromNumber,
                'To' => $phoneNumber,
                'Body' => "Your verification code is: {$otp}. Valid for {$this->getTtlMinutes()} minutes. Do not share this code.",
            ]),
            CURLOPT_USERPWD => "{$this->accountSid}:{$this->authToken}",
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[SMS OTP] Twilio error: {$error}");
            return [
                'success' => false,
                'error' => 'Failed to send SMS',
                'code' => 'SMS_FAILED',
            ];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && ($data['sid'] ?? null)) {
            return [
                'success' => true,
                'message' => 'OTP sent successfully',
                'sid' => $data['sid'],
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['message'] ?? 'Failed to send SMS',
            'code' => 'SMS_FAILED',
        ];
    }
    
    /**
     * Verify custom OTP.
     */
    private function verifyCustomOTP(string $phoneNumber, string $code, string $userId): array
    {
        if (!$this->redis) {
            return [
                'success' => false,
                'error' => 'OTP verification unavailable',
                'code' => 'SERVICE_UNAVAILABLE',
            ];
        }
        
        $otpKey = "saips:sms_otp:{$userId}";
        $stored = $this->redis->get($otpKey);
        
        if (!$stored) {
            return [
                'success' => false,
                'error' => 'OTP expired or not found',
                'code' => 'OTP_EXPIRED',
            ];
        }
        
        $data = json_decode($stored, true);
        
        // Verify phone number matches
        if ($data['phone'] !== $phoneNumber) {
            return [
                'success' => false,
                'error' => 'Phone number mismatch',
                'code' => 'PHONE_MISMATCH',
            ];
        }
        
        // Verify OTP (constant-time comparison)
        if (!hash_equals($data['otp'], $code)) {
            // Increment attempt counter
            $attemptKey = "saips:sms_attempts:{$userId}";
            $this->redis->incr($attemptKey);
            $this->redis->expire($attemptKey, $this->otpTtl);
            
            return [
                'success' => false,
                'error' => 'Invalid OTP code',
                'code' => 'INVALID_OTP',
            ];
        }
        
        // Clear OTP and attempts
        $this->redis->del($otpKey);
        $this->redis->del("saips:sms_attempts:{$userId}");
        
        return [
            'success' => true,
            'message' => 'OTP verified successfully',
        ];
    }
    
    /**
     * Validate phone number format (E.164).
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // E.164 format: +[country code][number], max 15 digits
        return (bool)preg_match('/^\+[1-9]\d{1,14}$/', $phone);
    }
    
    /**
     * Check rate limit for OTP requests.
     */
    private function checkRateLimit(string $userId): bool
    {
        if (!$this->redis) return true;
        
        $key = "saips:sms_rate:{$userId}";
        $count = (int)$this->redis->get($key);
        
        if ($count >= 5) { // Max 5 OTPs per hour
            return false;
        }
        
        $this->redis->incr($key);
        $this->redis->expire($key, 3600);
        
        return true;
    }
    
    /**
     * Get TTL in minutes for display.
     */
    private function getTtlMinutes(): int
    {
        return (int)ceil($this->otpTtl / 60);
    }
}