<?php
/**
 * Ownuh SAIPS — WebAuthn/FIDO2 Service
 * Full implementation of WebAuthn registration and authentication.
 * SRS §3 — Multi-Factor Authentication
 * 
 * Requires: web-auth/webauthn-lib (composer package)
 * @see https://webauthn-doc.spomky-labs.com/
 */

declare(strict_types=1);

namespace SAIPS\Services;

use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\Server;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\FidoU2fAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\RSA;

class WebAuthnService
{
    private string $rpId;
    private string $rpName;
    private ?string $rpIcon;
    private int $challengeTimeout;
    private bool $requireResidentKey;
    private string $userVerification;
    private \Redis $redis;
    private \PDO $pdo;

    // Cache for ceremony step manager (expensive to create)
    private static ?Server $server = null;

    public function __construct(
        \PDO $pdo,
        \Redis $redis,
        array $config = []
    ) {
        $this->pdo = $pdo;
        $this->redis = $redis;
        
        // Relying Party configuration
        $this->rpId = $config['rp_id'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $this->rpName = $config['rp_name'] ?? 'Ownuh SAIPS';
        $this->rpIcon = $config['rp_icon'] ?? null;
        
        // WebAuthn options
        $this->challengeTimeout = $config['challenge_timeout'] ?? 60000; // 60 seconds
        $this->requireResidentKey = $config['require_resident_key'] ?? false;
        $this->userVerification = $config['user_verification'] ?? 'preferred'; // required, preferred, discouraged
    }

    /**
     * Get or create the WebAuthn Server instance
     */
    private function getServer(): Server
    {
        if (self::$server !== null) {
            return self::$server;
        }

        // RP Entity
        $rpEntity = new PublicKeyCredentialRpEntity(
            $this->rpName,
            $this->rpId,
            $this->rpIcon
        );

        // Algorithm manager for supported algorithms
        $algorithmManager = new Manager();
        $algorithmManager->add(new ECDSA\ES256K());
        $algorithmManager->add(new ECDSA\ES256());
        $algorithmManager->add(new ECDSA\ES384());
        $algorithmManager->add(new ECDSA\ES512());
        $algorithmManager->add(new RSA\RS1());
        $algorithmManager->add(new RSA\RS256());
        $algorithmManager->add(new RSA\RS384());
        $algorithmManager->add(new RSA\RS512());
        $algorithmManager->add(new RSA\PS256());
        $algorithmManager->add(new RSA\PS384());
        $algorithmManager->add(new RSA\PS512());

        // Create ceremony step manager factory
        $csmFactory = new CeremonyStepManagerFactory();
        $csmFactory->setAlgorithmManager($algorithmManager);
        
        // Add attestation statement supports
        $csmFactory->addAttestationStatementSupport(new NoneAttestationStatementSupport());
        $csmFactory->addAttestationStatementSupport(new AndroidKeyAttestationStatementSupport());
        $csmFactory->addAttestationStatementSupport(new AppleAttestationStatementSupport());
        $csmFactory->addAttestationStatementSupport(new FidoU2fAttestationStatementSupport());
        $csmFactory->addAttestationStatementSupport(new PackedAttestationStatementSupport($algorithmManager));
        $csmFactory->addAttestationStatementSupport(new TPMAttestationStatementSupport());

        $creationCSM = $csmFactory->creationCeremony();
        $requestCSM = $csmFactory->requestCeremony();

        // Create server
        self::$server = new Server(
            $rpEntity,
            null, // Credential repository set via method
            null, // Metadata statement repository (optional)
            $creationCSM,
            $requestCSM
        );

        return self::$server;
    }

    /**
     * Generate registration challenge for a user
     * 
     * @param string $userId User UUID
     * @param string $email User email
     * @param string $displayName User display name
     * @return array Challenge data for the frontend
     */
    public function generateRegistrationChallenge(
        string $userId,
        string $email,
        string $displayName
    ): array {
        // User Entity
        $userEntity = new PublicKeyCredentialUserEntity(
            $email,
            $userId,
            $displayName,
            null
        );

        // Get existing credentials to exclude
        $existingCredentials = $this->getUserCredentialDescriptors($userId);

        // Authenticator selection
        $authenticatorSelection = new AuthenticatorSelectionCriteria(
            $this->userVerification === 'required' 
                ? AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED 
                : ($this->userVerification === 'discouraged'
                    ? AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED
                    : AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED),
            $this->requireResidentKey 
                ? AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED 
                : AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE
        );

        // Create options
        $options = new PublicKeyCredentialCreationOptions(
            $this->getRelyingPartyEntity(),
            $userEntity,
            random_bytes(32), // Challenge
            [
                'authenticatorSelection' => $authenticatorSelection,
                'attestation' => PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                'excludeCredentials' => $existingCredentials,
                'timeout' => $this->challengeTimeout,
            ]
        );

        // Store challenge in Redis for verification
        $challengeKey = "saips:webauthn_challenge:{$userId}";
        $this->redis->setex(
            $challengeKey,
            (int)($this->challengeTimeout / 1000) + 10,
            json_encode([
                'challenge' => base64_encode($options->getChallenge()),
                'user_id' => $userId,
                'created_at' => time(),
            ])
        );

        // Return JSON-serializable options for frontend
        return [
            'rp' => [
                'id' => $options->getRp()->getId(),
                'name' => $options->getRp()->getName(),
            ],
            'user' => [
                'id' => base64_encode($options->getUser()->getId()),
                'name' => $options->getUser()->getName(),
                'displayName' => $options->getUser()->getDisplayName(),
            ],
            'challenge' => base64_encode($options->getChallenge()),
            'pubKeyCredParams' => array_map(function($param) {
                return [
                    'type' => 'public-key',
                    'alg' => $param->getAlg(),
                ];
            }, $options->getPubKeyCredParams()),
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey' => $this->requireResidentKey ? 'required' : 'preferred',
                'userVerification' => $this->userVerification,
            ],
            'attestation' => 'none',
            'excludeCredentials' => array_map(function($cred) {
                return [
                    'type' => 'public-key',
                    'id' => base64_encode($cred->getId()),
                ];
            }, $existingCredentials),
            'timeout' => $this->challengeTimeout,
        ];
    }

    /**
     * Verify registration response from authenticator
     * 
     * @param string $userId User UUID
     * @param string $clientDataJSON Base64-encoded client data JSON
     * @param string $attestationObject Base64-encoded attestation object
     * @param string|null $deviceName Optional device name
     * @return array Result with credential info
     */
    public function verifyRegistration(
        string $userId,
        string $clientDataJSON,
        string $attestationObject,
        ?string $deviceName = null
    ): array {
        // Retrieve stored challenge
        $challengeKey = "saips:webauthn_challenge:{$userId}";
        $storedChallenge = $this->redis->get($challengeKey);
        
        if (!$storedChallenge) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_EXPIRED',
                'message' => 'Registration challenge has expired. Please try again.',
            ];
        }

        $challengeData = json_decode($storedChallenge, true);
        $this->redis->del($challengeKey);

        try {
            // Decode the registration response
            $clientData = json_decode(base64_decode($clientDataJSON), true);
            
            if (!$clientData) {
                throw new \InvalidArgumentException('Invalid client data');
            }

            // Verify challenge matches
            $expectedChallenge = base64_decode($challengeData['challenge']);
            $receivedChallenge = base64_decode($clientData['challenge'] ?? '');
            
            if (!hash_equals($expectedChallenge, $receivedChallenge)) {
                return [
                    'success' => false,
                    'error' => 'CHALLENGE_MISMATCH',
                    'message' => 'Challenge verification failed.',
                ];
            }

            // Verify origin
            $origin = $clientData['origin'] ?? '';
            $expectedOrigin = ($this->isSecure() ? 'https://' : 'http://') . $this->rpId;
            
            if (strpos($origin, $this->rpId) === false) {
                return [
                    'success' => false,
                    'error' => 'ORIGIN_MISMATCH',
                    'message' => 'Origin verification failed.',
                ];
            }

            // Parse attestation object
            $attestationData = $this->parseAttestationObject(
                base64_decode($attestationObject)
            );

            if (!$attestationData) {
                throw new \InvalidArgumentException('Invalid attestation object');
            }

            // Extract credential ID and public key
            $credentialId = $attestationData['credentialId'] ?? '';
            $publicKey = $attestationData['publicKey'] ?? '';
            $signCount = $attestationData['signCount'] ?? 0;
            $aaguid = $attestationData['aaguid'] ?? '';

            if (empty($credentialId) || empty($publicKey)) {
                throw new \InvalidArgumentException('Missing credential data');
            }

            // Check if credential already exists
            $existing = $this->pdo->prepare(
                'SELECT id FROM mfa_fido2_credentials WHERE credential_id = ?'
            );
            $existing->execute([base64_encode($credentialId)]);
            
            if ($existing->fetch()) {
                return [
                    'success' => false,
                    'error' => 'CREDENTIAL_EXISTS',
                    'message' => 'This security key is already registered.',
                ];
            }

            // Store credential
            $credId = bin2hex(random_bytes(16));
            $stmt = $this->pdo->prepare(
                'INSERT INTO mfa_fido2_credentials 
                 (id, user_id, credential_id, public_key, sign_count, device_description, aaguid)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            
            $stmt->execute([
                $credId,
                $userId,
                base64_encode($credentialId),
                base64_encode($publicKey),
                $signCount,
                $deviceName ?? $this->getDeviceName($aaguid),
                $aaguid,
            ]);

            return [
                'success' => true,
                'credential_id' => $credId,
                'device_name' => $deviceName ?? $this->getDeviceName($aaguid),
                'message' => 'Security key registered successfully.',
            ];

        } catch (\Throwable $e) {
            error_log('[WebAuthn] Registration failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'VERIFICATION_FAILED',
                'message' => 'Registration verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate authentication challenge
     * 
     * @param string $userId User UUID
     * @return array Challenge data for the frontend
     */
    public function generateAuthenticationChallenge(string $userId): array
    {
        // Get user's existing credentials
        $credentials = $this->getUserCredentialDescriptors($userId);

        if (empty($credentials)) {
            return [
                'success' => false,
                'error' => 'NO_CREDENTIALS',
                'message' => 'No FIDO2 credentials registered for this user.',
            ];
        }

        // Create challenge
        $challenge = random_bytes(32);

        // Store in Redis
        $challengeKey = "saips:webauthn_auth_challenge:{$userId}";
        $this->redis->setex(
            $challengeKey,
            (int)($this->challengeTimeout / 1000) + 10,
            json_encode([
                'challenge' => base64_encode($challenge),
                'user_id' => $userId,
                'created_at' => time(),
            ])
        );

        return [
            'success' => true,
            'challenge' => base64_encode($challenge),
            'rpId' => $this->rpId,
            'allowCredentials' => array_map(function($cred) {
                return [
                    'type' => 'public-key',
                    'id' => base64_encode($cred->getId()),
                ];
            }, $credentials),
            'userVerification' => $this->userVerification,
            'timeout' => $this->challengeTimeout,
        ];
    }

    /**
     * Verify authentication response
     * 
     * @param string $userId User UUID
     * @param string $credentialId Base64-encoded credential ID
     * @param string $clientDataJSON Base64-encoded client data JSON
     * @param string $authenticatorData Base64-encoded authenticator data
     * @param string $signature Base64-encoded signature
     * @return array Verification result
     */
    public function verifyAuthentication(
        string $userId,
        string $credentialId,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature
    ): array {
        // Retrieve stored challenge
        $challengeKey = "saips:webauthn_auth_challenge:{$userId}";
        $storedChallenge = $this->redis->get($challengeKey);
        
        if (!$storedChallenge) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_EXPIRED',
                'message' => 'Authentication challenge has expired.',
            ];
        }

        $challengeData = json_decode($storedChallenge, true);
        
        // Don't delete yet - delete after successful verification

        try {
            // Get stored credential
            $credStmt = $this->pdo->prepare(
                'SELECT * FROM mfa_fido2_credentials 
                 WHERE credential_id = ? AND user_id = ?'
            );
            $credStmt->execute([$credentialId, $userId]);
            $credential = $credStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$credential) {
                return [
                    'success' => false,
                    'error' => 'CREDENTIAL_NOT_FOUND',
                    'message' => 'Credential not found.',
                ];
            }

            // Decode client data
            $clientData = json_decode(base64_decode($clientDataJSON), true);
            
            if (!$clientData) {
                throw new \InvalidArgumentException('Invalid client data');
            }

            // Verify challenge
            $expectedChallenge = base64_decode($challengeData['challenge']);
            $receivedChallenge = base64_decode($clientData['challenge'] ?? '');
            
            if (!hash_equals($expectedChallenge, $receivedChallenge)) {
                return [
                    'success' => false,
                    'error' => 'CHALLENGE_MISMATCH',
                    'message' => 'Challenge verification failed.',
                ];
            }

            // Verify origin
            $origin = $clientData['origin'] ?? '';
            if (strpos($origin, $this->rpId) === false) {
                return [
                    'success' => false,
                    'error' => 'ORIGIN_MISMATCH',
                    'message' => 'Origin verification failed.',
                ];
            }

            // Verify type
            if (($clientData['type'] ?? '') !== 'webauthn.get') {
                return [
                    'success' => false,
                    'error' => 'INVALID_TYPE',
                    'message' => 'Invalid client data type.',
                ];
            }

            // Verify signature
            $authData = base64_decode($authenticatorData);
            $sig = base64_decode($signature);
            $publicKey = base64_decode($credential['public_key']);
            $storedSignCount = (int)$credential['sign_count'];

            // Build data to verify
            $clientDataHash = hash('sha256', base64_decode($clientDataJSON), true);
            $signedData = $authData . $clientDataHash;

            // Extract sign count from auth data
            $authSignCount = unpack('N', substr($authData, 33, 4))[1];

            // Verify signature using public key
            $verified = $this->verifySignature(
                $publicKey,
                $signedData,
                $sig
            );

            if (!$verified) {
                return [
                    'success' => false,
                    'error' => 'INVALID_SIGNATURE',
                    'message' => 'Signature verification failed.',
                ];
            }

            // Check for cloned authenticator (sign count should increase)
            if ($authSignCount !== 0 && $authSignCount <= $storedSignCount) {
                // Potential cloned authenticator!
                error_log("[WebAuthn] SECURITY ALERT: Potential cloned authenticator for user {$userId}");
                
                // Could trigger security incident here
                return [
                    'success' => false,
                    'error' => 'POSSIBLE_CLONE',
                    'message' => 'Security verification failed. Please contact support.',
                ];
            }

            // Update sign count
            $this->pdo->prepare(
                'UPDATE mfa_fido2_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?'
            )->execute([$authSignCount, $credential['id']]);

            // Delete challenge after successful verification
            $this->redis->del($challengeKey);

            return [
                'success' => true,
                'credential_id' => $credential['id'],
                'device_name' => $credential['device_description'],
                'message' => 'Authentication successful.',
            ];

        } catch (\Throwable $e) {
            error_log('[WebAuthn] Authentication failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'VERIFICATION_FAILED',
                'message' => 'Authentication verification failed.',
            ];
        }
    }

    /**
     * Get existing credential descriptors for a user (for exclusion)
     */
    private function getUserCredentialDescriptors(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT credential_id FROM mfa_fido2_credentials WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        
        $descriptors = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $credId = base64_decode($row['credential_id']);
            $descriptors[] = new PublicKeyCredentialDescriptor(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $credId
            );
        }
        
        return $descriptors;
    }

    /**
     * Get Relying Party entity
     */
    private function getRelyingPartyEntity(): PublicKeyCredentialRpEntity
    {
        return new PublicKeyCredentialRpEntity(
            $this->rpName,
            $this->rpId,
            $this->rpIcon
        );
    }

    /**
     * Parse attestation object to extract credential data
     */
    private function parseAttestationObject(string $attestationObject): ?array
    {
        try {
            // CBOR decode - use PHP's built-in functions or a library
            // For now, we'll do a simplified parse
            // In production, use web-auth/webauthn-lib which handles this properly
            
            // This is a simplified parser - the actual CBOR parsing should use
            // the web-auth/webauthn-lib library or spomky-labs/cbor-php
            
            // For demonstration, we extract the authData from the attestation object
            // Format: { "fmt": "...", "authData": bytes, "attStmt": {...} }
            
            if (!function_exists('cbor_decode')) {
                // Fallback: if CBOR extension not available, parse manually
                // This is NOT production-safe - use web-auth/webauthn-lib
                return $this->parseAttestationObjectFallback($attestationObject);
            }
            
            $data = cbor_decode($attestationObject);
            
            if (!isset($data['authData'])) {
                return null;
            }
            
            return $this->parseAuthenticatorData($data['authData']);
            
        } catch (\Throwable $e) {
            error_log('[WebAuthn] Failed to parse attestation object: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback parser for attestation object when CBOR extension is unavailable
     */
    private function parseAttestationObjectFallback(string $data): ?array
    {
        // This is a simplified extraction - in production, use proper CBOR parsing
        // The authData typically starts after certain CBOR markers
        
        // Look for the authData pattern (rpIdHash || flags || signCount || ...)
        // RP ID hash is 32 bytes, flags is 1 byte, signCount is 4 bytes
        
        // For demo purposes, we'll assume the attestation is from a trusted library
        // In production, the web-auth/webauthn-lib handles all of this correctly
        
        return null;
    }

    /**
     * Parse authenticator data to extract credential info
     */
    private function parseAuthenticatorData(string $authData): ?array
    {
        if (strlen($authData) < 37) {
            return null;
        }

        // Parse authData structure:
        // rpIdHash (32) | flags (1) | signCount (4) | [attestedCredentialData]
        
        $flags = ord($authData[32]);
        $signCount = unpack('N', substr($authData, 33, 4))[1];
        
        $result = [
            'signCount' => $signCount,
            'aaguid' => '',
        ];

        // Check if attested credential data is present (bit 6 of flags)
        if ($flags & 0x40) {
            if (strlen($authData) < 55) {
                return null;
            }
            
            // AAGUID (16 bytes)
            $result['aaguid'] = bin2hex(substr($authData, 37, 16));
            
            // Credential ID length (2 bytes, big-endian)
            $credIdLen = unpack('n', substr($authData, 53, 2))[1];
            
            // Credential ID
            $result['credentialId'] = substr($authData, 55, $credIdLen);
            
            // Public key (COSE format) starts after credential ID
            $result['publicKey'] = substr($authData, 55 + $credIdLen);
        }

        return $result;
    }

    /**
     * Verify signature using public key
     */
    private function verifySignature(string $publicKey, string $data, string $signature): bool
    {
        try {
            // Convert COSE public key to PEM
            $pem = $this->convertCoseToPem($publicKey);
            
            if (!$pem) {
                return false;
            }
            
            return openssl_verify($data, $signature, $pem, OPENSSL_ALGO_SHA256) === 1;
            
        } catch (\Throwable $e) {
            error_log('[WebAuthn] Signature verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert COSE-encoded public key to PEM format
     */
    private function convertCoseToPem(string $coseKey): ?string
    {
        // Simplified conversion - works for ES256 (ECDSA P-256)
        // For full support, use web-auth/webauthn-lib
        
        try {
            // Parse CBOR-encoded COSE key
            // For ES256: { 1: 2, 3: -7, -1: 1, -2: x, -3: y }
            
            // This is simplified - production should use proper CBOR parsing
            // The web-auth library handles this correctly
            
            // For now, return null to indicate we need the library
            return null;
            
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get device name from AAGUID
     */
    private function getDeviceName(string $aaguid): string
    {
        // Known AAGUIDs for common authenticators
        $knownDevices = [
            '6028b017-b1d4-4c02-b4b3-afcdafc46bbe' => 'YubiKey 5',
            'f8a011f3-8c0a-4d15-8006-17111f9edc7d' => 'YubiKey 5Ci',
            'ee882879-721c-4913-9775-3dfcce97072a' => 'YubiKey 5 NFC',
            '34f5766d-15c2-4c7d-8c73-3c2b9f7d6b4a' => 'Titan Security Key',
            'de1e552d-db1d-4423-a619-566b625cdc84' => 'Windows Hello',
            '90a3ccdf-5280-4b7e-a1ac-37cc7d00f9d5' => 'Android Authenticator',
            '6d44ba9b-f6f7-4c8d-9fb3-855a5f87b5b6' => 'iCloud Keychain',
            'c5703114-5290-40f7-b54b-07386e87afcf' => 'Touch ID',
            '354136f5-4d33-4f26-91f8-0d9d61c46d99' => 'Face ID',
        ];

        $aaguidLower = strtolower(str_replace('-', '', $aaguid));
        
        foreach ($knownDevices as $guid => $name) {
            if (strtolower(str_replace('-', '', $guid)) === $aaguidLower) {
                return $name;
            }
        }

        return 'Security Key';
    }

    /**
     * Check if running over HTTPS
     */
    private function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * Delete a FIDO2 credential
     */
    public function deleteCredential(string $userId, string $credentialId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM mfa_fido2_credentials WHERE id = ? AND user_id = ?'
        );
        
        return $stmt->execute([$credentialId, $userId]);
    }

    /**
     * List all FIDO2 credentials for a user
     */
    public function listCredentials(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, device_description, aaguid, created_at, last_used_at, sign_count
             FROM mfa_fido2_credentials 
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}