<?php
/**
 * Ownuh SAIPS â€” Security Configuration
 * Implements thresholds from SRS Â§2â€“3.
 */

return [

    // â”€â”€ Password Policy (SRS Â§2.2) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'password' => [
        'min_length'        => 12,
        'max_length'        => 128,
        'require_classes'   => 3,        // 3-of-4: upper, lower, digit, special
        'history_count'     => 12,       // block last N passwords
        'expiry_standard'   => 180,      // days â€” standard accounts
        'expiry_privileged' => 90,       // days â€” admin/superadmin
        'hibp_check'        => true,
        'similarity_check'  => true,     // Levenshtein vs email/username
        'similarity_min'    => 3,        // Levenshtein distance threshold
        'bcrypt_cost'       => 12,
        'bcrypt_upgrade_to' => 14,       // auto-upgrade on next login
    ],

    // â”€â”€ MFA Policy (SRS Â§2.4) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'mfa' => [
        'required_roles'       => ['superadmin', 'admin'],
        'totp_window'          => 1,     // Â±1 step tolerance (RFC 6238)
        'totp_digits'          => 6,
        'totp_period'          => 30,    // seconds
        'email_otp_length'     => 6,
        'email_otp_ttl'        => 600,   // 10 minutes
        'email_otp_rate'       => 5,     // per hour
        'sms_otp_ttl'          => 300,   // 5 minutes
        'backup_codes_count'   => 10,
        'bypass_ttl'           => 14400, // 4 hours in seconds
        'fido2_required_roles' => ['superadmin', 'admin'],
    ],

    // â”€â”€ JWT Configuration (SRS Â§3.4) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'jwt' => [
        'algorithm'          => 'RS256',
        'access_ttl'         => 900,     // 15 minutes
        'refresh_ttl'        => 604800,  // 7 days
        'admin_refresh_ttl'  => 28800,   // 8 hours
        'issuer'             => 'ownuh-saips',
        'private_key_path'   => $_ENV['JWT_PRIVATE_KEY_PATH'] ?? '/etc/saips/keys/private.pem',
        'public_key_path'    => $_ENV['JWT_PUBLIC_KEY_PATH']  ?? '/etc/saips/keys/public.pem',
    ],

    // â”€â”€ Session Policy (SRS Â§3.4) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'session' => [
        'max_concurrent_standard' => 3,
        'max_concurrent_admin'    => 1,
        'idle_timeout_standard'   => 3600,  // 60 minutes
        'idle_timeout_admin'      => 900,   // 15 minutes
    ],

    // â”€â”€ Brute-Force Detection (SRS Â§3.1 & Â§3.2) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'brute_force' => [
        'per_user' => [
            'failures'        => 5,
            'window_minutes'  => 15,
            'lockout_minutes' => 30,
        ],
        'per_user_hard' => [
            'failures'        => 10,
            'window_hours'    => 24,
            'lockout'         => 'hard',  // requires admin unlock
        ],
        'per_ip' => [
            'failures'        => 20,
            'window_minutes'  => 10,
            'block_minutes'   => 60,
        ],
        'distributed' => [
            'failures'        => 100,
            'window_minutes'  => 5,
            'action'          => 'waf_rule',
        ],
        'progressive_delay' => [
            'after_failures'  => 3,
            'initial_seconds' => 5,
            'multiplier'      => 2,
            'max_seconds'     => 60,
        ],
        'captcha_after'       => 5,   // soft-lock threshold
    ],

    // â”€â”€ Rate Limits (SRS Â§3.3) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'rate_limits' => [
        '/auth/login'         => ['limit' => 60,  'window' => 60,   'scope' => 'per_ip'],
        '/auth/token'         => ['limit' => 60,  'window' => 60,   'scope' => 'per_ip'],
        '/auth/mfa/verify'    => ['limit' => 5,   'window' => 900,  'scope' => 'per_user'],
        '/auth/mfa/email-otp' => ['limit' => 5,   'window' => 3600, 'scope' => 'per_user'],
        '/api/*'              => ['limit' => 300, 'window' => 60,   'scope' => 'per_token'],
    ],

    // â”€â”€ Threat Intelligence (SRS Â§3.3) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'threat_intel' => [
        'feeds' => [
            'abuseipdb'       => ['url' => 'https://api.abuseipdb.com/api/v2/check', 'api_key_env' => 'ABUSEIPDB_KEY'],
            'spamhaus'        => ['url' => 'https://query.spamhaus.org/'],
            'emerging_threats'=> ['url' => 'https://rules.emergingthreats.net/'],
        ],
        'update_interval'     => 21600, // 6 hours
        'tor_block_admins'    => true,
        'tor_challenge_users' => true,
        'vpn_log_only'        => true,  // VPNs logged but not blocked
    ],

    // â”€â”€ Alerts & Notifications (SRS Â§5.2) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    'alerts' => [
        'email_from'          => 'security@ownuh-saips.com',
        'admin_email'         => 'sophia.johnson@ownuh-saips.com',
        'webhook_url'         => $_ENV['ALERT_WEBHOOK_URL'] ?? null,
        'sms_enabled'         => false,
        'dispatch_within_seconds' => 60,
    ],

];
