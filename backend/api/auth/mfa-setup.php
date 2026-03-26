<?php
/**
 * Ownuh SAIPS — POST /auth/mfa/setup
 * Alias/wrapper for mfa-enroll.php — called from settings-mfa.html
 * Allows authenticated users to initiate MFA enrollment.
 * SRS §2.4 — Multi-Factor Authentication
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Delegate to the full enrollment implementation
require __DIR__ . '/mfa-enroll.php';
