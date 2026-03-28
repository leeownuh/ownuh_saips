<?php
/**
 * Ownuh SAIPS — Audit Middleware
 * Writes tamper-evident, SHA-256 chained audit log entries.
 * SRS §4 — Audit Logging and Monitoring
 *
 * Usage: AuditMiddleware::log('AUTH-001', 'Successful Login', $userId, $ip, ...)
 */

declare(strict_types=1);

namespace SAIPS\Middleware;

class AuditMiddleware
{
    private static ?\PDO $db = null;

    public static function init(\PDO $db): void
    {
        self::$db = $db;
    }

    /**
     * Write a single audit log entry via stored procedure (handles SHA-256 chaining).
     *
     * @param string      $eventCode    e.g. 'AUTH-001'
     * @param string      $eventName    e.g. 'Successful Login'
     * @param string|null $userId       Authenticated user UUID (null for IP-only events)
     * @param string|null $sourceIp     Request IP address
     * @param string|null $userAgent    HTTP User-Agent header
     * @param string|null $countryCode  ISO 3166-1 alpha-2
     * @param string|null $mfaMethod    'fido2','totp','email_otp','sms','backup_code'
     * @param int|null    $riskScore    0–100
     * @param array|null  $details      Event-specific data per SRS §4.1
     * @param string|null $adminId      Admin performing the action (ADM-* events)
     * @param string|null $targetUserId Target of the admin action
     */
    public static function log(
        string  $eventCode,
        string  $eventName,
        ?string $userId      = null,
        ?string $sourceIp    = null,
        ?string $userAgent   = null,
        ?string $countryCode = null,
        ?string $mfaMethod   = null,
        ?int    $riskScore   = null,
        array|string|null $details = null,
        ?string $adminId     = null,
        ?string $targetUserId = null
    ): void {
        if (!self::$db) {
            error_log('[SAIPS Audit] Database not initialised');
            return;
        }

        try {
            $resolvedSourceIp = $sourceIp ?? self::getClientIp();
            $resolvedUserAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
            $normalizedDetails = self::normalizeDetails($details);

            $stmt = self::$db->prepare(
                'CALL sp_insert_audit_log(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $eventCode,
                $eventName,
                $userId,
                $resolvedSourceIp,
                $resolvedUserAgent,
                $countryCode,
                $mfaMethod,
                $riskScore,
                $normalizedDetails,
                $adminId,
                $targetUserId,
            ]);
        } catch (\PDOException $e) {
            if (self::isMissingProcedureError($e)) {
                try {
                    self::insertDirectly(
                        $eventCode,
                        $eventName,
                        $userId,
                        $resolvedSourceIp ?? $sourceIp ?? self::getClientIp(),
                        $resolvedUserAgent ?? $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
                        $countryCode,
                        $mfaMethod,
                        $riskScore,
                        $normalizedDetails ?? self::normalizeDetails($details),
                        $adminId,
                        $targetUserId
                    );
                    return;
                } catch (\Throwable $fallbackError) {
                    error_log('[SAIPS Audit CRITICAL] Failed stored procedure and direct fallback: '
                        . $fallbackError->getMessage()
                        . ' | Event: ' . $eventCode
                        . ' | User: ' . ($userId ?? 'null'));
                    return;
                }
            }

            error_log('[SAIPS Audit CRITICAL] Failed to write audit entry: ' . $e->getMessage()
                . ' | Event: ' . $eventCode . ' | User: ' . ($userId ?? 'null'));
        } catch (\Throwable $e) {
            // Audit failures must not break the request, but MUST be logged to file
            error_log('[SAIPS Audit CRITICAL] Failed to write audit entry: ' . $e->getMessage()
                . ' | Event: ' . $eventCode . ' | User: ' . ($userId ?? 'null'));
        }
    }

    /**
     * Convenience wrappers for common event types
     */
    public static function authSuccess(string $userId, string $ip, string $country, string $mfa, int $risk): void
    {
        self::log('AUTH-001', 'Successful Login', $userId, $ip, null, $country, $mfa, $risk, [
            'timestamp' => date('c'),
        ]);
    }

    public static function authFailure(string $username, string $ip, string $reason, int $attempt): void
    {
        self::log('AUTH-002', 'Failed Login Attempt', null, $ip, null, null, null, null, [
            'username_attempted' => $username,
            'failure_reason'     => $reason,
            'attempt_count'      => $attempt,
        ]);
    }

    public static function accountLocked(string $userId, string $lockType, string $triggerRule, string $adminId = null): void
    {
        self::log('AUTH-003', 'Account Locked', $userId, null, null, null, null, null, [
            'lock_type'    => $lockType,
            'trigger_rule' => $triggerRule,
        ], $adminId, $userId);
    }

    public static function accountUnlocked(string $userId, string $adminId, string $justification): void
    {
        self::log('AUTH-004', 'Account Unlocked', $userId, null, null, null, null, null, [
            'justification' => $justification,
        ], $adminId, $userId);
    }

    public static function passwordChanged(string $userId, string $method): void
    {
        self::log('AUTH-005', 'Password Changed', $userId, null, null, null, null, null, [
            'change_method' => $method, // 'self_service' or 'admin_reset'
        ]);
    }

    public static function mfaEnrolled(string $userId, string $factor, string $device = null): void
    {
        self::log('AUTH-006', 'MFA Enrolled', $userId, null, null, null, $factor, null, [
            'factor'            => $factor,
            'device_description'=> $device,
        ]);
    }

    public static function mfaBypassIssued(string $targetUserId, string $adminId, string $reason): void
    {
        self::log('AUTH-007', 'MFA Bypass Issued', $targetUserId, null, null, null, null, null, [
            'bypass_reason' => $reason,
            'expiry_hours'  => 4,
        ], $adminId, $targetUserId);
    }

    public static function mfaReset(string $targetUserId, string $adminId, string $reason, bool $invalidateSessions): void
    {
        self::log('AUTH-008', 'MFA Reset', $targetUserId, null, null, null, null, null, [
            'reset_reason'       => $reason,
            'sessions_invalidated'=> $invalidateSessions,
        ], $adminId, $targetUserId);
    }

    public static function backupCodesGenerated(string $userId, int $codeCount): void
    {
        self::log('AUTH-009', 'Backup Codes Generated', $userId, null, null, null, null, null, [
            'codes_count' => $codeCount,
        ]);
    }

    public static function passwordResetInitiated(string $targetUserId, string $adminId, string $tokenExpiry): void
    {
        self::log('AUTH-010', 'Password Reset Initiated', $targetUserId, null, null, null, null, null, [
            'reset_method' => 'admin_initiated',
            'token_expiry' => $tokenExpiry,
        ], $adminId, $targetUserId);
    }

    public static function sessionCreated(string $sessionId, string $userId, string $ip): void
    {
        self::log('SES-001', 'Session Created', $userId, $ip, null, null, null, null, [
            'session_id' => $sessionId,
        ]);
    }

    public static function sessionInvalidated(string $sessionId, string $userId, string $adminId = null, string $reason = null): void
    {
        self::log('SES-003', 'Session Invalidated', $userId, null, null, null, null, null, [
            'session_id' => $sessionId,
            'reason'     => $reason,
        ], $adminId, $userId);
    }

    public static function sessionsRevoked(string $userId, string $adminId, int $count, string $reason = null): void
    {
        self::log('SES-004', 'Sessions Revoked', $userId, null, null, null, null, null, [
            'sessions_count' => $count,
            'reason'         => $reason,
        ], $adminId, $userId);
    }

    public static function incidentCreated(string $userId, string $incidentRef, string $severity, string $title): void
    {
        self::log('INC-001', 'Incident Created', $userId, null, null, null, null, null, [
            'incident_ref' => $incidentRef,
            'severity'     => $severity,
            'title'        => $title,
        ]);
    }

    public static function incidentUpdated(string $userId, string $incidentRef, string $oldStatus, string $newStatus): void
    {
        self::log('INC-002', 'Incident Updated', $userId, null, null, null, null, null, [
            'incident_ref' => $incidentRef,
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
        ]);
    }

    public static function userSoftDeleted(string $targetUserId, string $adminId, string $reason, int $recoveryDays): void
    {
        self::log('USR-001', 'User Soft Deleted', $targetUserId, null, null, null, null, null, [
            'deletion_reason' => $reason,
            'recovery_days'   => $recoveryDays,
        ], $adminId, $targetUserId);
    }

    public static function userDeleted(string $targetUserId, string $adminId, string $reason): void
    {
        self::log('USR-002', 'User Permanently Deleted', $targetUserId, null, null, null, null, null, [
            'deletion_reason' => $reason,
        ], $adminId, $targetUserId);
    }

    public static function ipBlocked(string $ip, string $rule, int $durationMin): void
    {
        self::log('IPS-001', 'IP Blocked', null, $ip, null, null, null, null, [
            'trigger_rule'    => $rule,
            'duration_minutes'=> $durationMin,
        ]);
    }

    public static function bruteForceDetected(string $ip, array $targets, string $classification): void
    {
        self::log('IPS-002', 'Brute-Force Detected', null, $ip, null, null, null, null, [
            'source_ips'     => [$ip],
            'target_users'   => $targets,
            'classification' => $classification,
        ]);
    }

    public static function adminLogin(string $adminId, string $ip, string $mfaMethod): void
    {
        self::log('ADM-001', 'Admin Login', $adminId, $ip, null, null, $mfaMethod);
    }

    public static function userRecordModified(string $adminId, string $targetUserId, array $fieldsChanged): void
    {
        self::log('ADM-002', 'User Record Modified', null, null, null, null, null, null, [
            'fields_changed' => $fieldsChanged,
        ], $adminId, $targetUserId);
    }

    public static function roleChanged(string $adminId, string $targetUserId, string $oldRole, string $newRole): void
    {
        self::log('ADM-003', 'Role/Permission Changed', null, null, null, null, null, null, [
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ], $adminId, $targetUserId);
    }

    private static function normalizeDetails(array|string|null $details): ?string
    {
        if ($details === null) {
            return null;
        }

        if (is_string($details)) {
            json_decode($details);
            return json_last_error() === JSON_ERROR_NONE
                ? $details
                : json_encode(['message' => $details], JSON_UNESCAPED_SLASHES);
        }

        return json_encode($details, JSON_UNESCAPED_SLASHES);
    }

    private static function isMissingProcedureError(\PDOException $e): bool
    {
        $errorInfo = $e->errorInfo;
        $driverCode = is_array($errorInfo) ? (int)($errorInfo[1] ?? 0) : 0;

        return $e->getCode() === '42000'
            && $driverCode === 1305
            && str_contains($e->getMessage(), 'sp_insert_audit_log');
    }

    private static function insertDirectly(
        string $eventCode,
        string $eventName,
        ?string $userId,
        ?string $sourceIp,
        ?string $userAgent,
        ?string $countryCode,
        ?string $mfaMethod,
        ?int $riskScore,
        ?string $detailsJson,
        ?string $adminId,
        ?string $targetUserId
    ): void {
        $previousHashStmt = self::$db->query('SELECT entry_hash FROM audit_log ORDER BY id DESC LIMIT 1');
        $previousHash = $previousHashStmt ? $previousHashStmt->fetchColumn() : null;
        $createdAt = self::$db->query('SELECT DATE_FORMAT(NOW(3), "%Y-%m-%d %H:%i:%s.%f")')->fetchColumn();

        $entryHash = hash('sha256', implode('|', [
            $previousHash ?: 'GENESIS',
            $eventCode,
            $userId ?? '',
            $createdAt,
            $detailsJson ?? '',
        ]));

        $stmt = self::$db->prepare(
            'INSERT INTO audit_log (
                event_code, event_name, user_id, source_ip, user_agent,
                country_code, mfa_method, risk_score, details,
                admin_id, target_user_id, entry_hash, prev_hash
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $eventCode,
            $eventName,
            $userId,
            $sourceIp,
            $userAgent,
            $countryCode,
            $mfaMethod,
            $riskScore,
            $detailsJson,
            $adminId,
            $targetUserId,
            $entryHash,
            $previousHash ?: null,
        ]);
    }

    private static function getClientIp(): string
    {
        $remoteAddr   = $_SERVER['REMOTE_ADDR'] ?? '';
        $trustedProxy = $_ENV['TRUSTED_PROXY'] ?? '';
        $proxyTrusted = $trustedProxy !== ''
                     && ($trustedProxy === 'any' || $remoteAddr === $trustedProxy);

        // Only read forwarded-IP headers when request comes from a trusted proxy.
        // Accepting these headers unconditionally allows clients to spoof their IP
        // address in audit logs, defeating rate-limiting and geo-blocking.
        if ($proxyTrusted) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $ip = trim(explode(',', $_SERVER[$key])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
    }
}
