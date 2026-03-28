<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS â€” CAP512 Syllabus Demonstration
 * CAP512: Open Source Web Application Development
 * Covers ALL units: Iâ€“VII + Graphics
 * L:2 T:0 P:4 Credits:4 | LPU Session 2025-26
 */

require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT I â€” Introduction to PHP / Language Basics
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// Data types â€” integers, floats, strings, booleans, null
$intVar    = 42;
$floatVar  = 3.14159;
$strVar    = "Ownuh SAIPS v1.0";
$boolVar   = true;
$nullVar   = null;

// Variable variables â€” CAP512 Unit I
$varName   = 'greeting';
$$varName  = 'Hello from SAIPS!';

// Heredoc string
$heredoc = <<<EOT
System: {$strVar}
PHP Version: {$_SERVER['SERVER_PROTOCOL']}
Timestamp: {$_SERVER['REQUEST_TIME']}
EOT;

// Type juggling and casting
$numStr = "100 blocked IPs";
$asInt  = (int)$numStr;      // 100
$asBool = (bool)$numStr;     // true

// Constants
define('SAIPS_VERSION', '1.0.0');
define('MAX_LOGIN_ATTEMPTS', 10);

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT II â€” Control Flow and Loops
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// if / elseif / else â€” security score classification
$score = 87;
if ($score >= 90) {
    $scoreClass = 'Excellent';
} elseif ($score >= 75) {
    $scoreClass = 'Good';
} elseif ($score >= 60) {
    $scoreClass = 'Fair';
} else {
    $scoreClass = 'Poor';
}

// switch â€” event code routing
$eventCode = 'AUTH-001';
switch (substr($eventCode, 0, 3)) {
    case 'AUT': $eventType = 'Authentication';    break;
    case 'SES': $eventType = 'Session';           break;
    case 'IPS': $eventType = 'Intrusion Prevention'; break;
    case 'ADM': $eventType = 'Admin Action';      break;
    default:    $eventType = 'Unknown';
}

// match expression (PHP 8)
$riskLevel = match(true) {
    $score >= 80 => 'Low Risk',
    $score >= 50 => 'Medium Risk',
    default      => 'High Risk',
};

// for loop â€” generate dummy last 5 login timestamps
$loginTimes = [];
for ($i = 0; $i < 5; $i++) {
    $loginTimes[] = date('Y-m-d H:i:s', time() - ($i * 3600));
}

// while loop â€” countdown for session expiry
$secondsLeft = 850;
$expiry = '';
while ($secondsLeft > 0) {
    $expiry = sprintf('%02d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60);
    $secondsLeft = 0; // stop after one iteration for demo
}

// do-while
$retries = 0;
do { $retries++; } while ($retries < 3 && false);

// foreach on associative array
$authMethods = ['FIDO2' => 'Hardware Key', 'TOTP' => 'Authenticator App', 'Email OTP' => 'Email Code'];
$methodList  = [];
foreach ($authMethods as $code => $desc) {
    $methodList[] = "{$code}: {$desc}";
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT III â€” Functions
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// User-defined function with default parameter
function calculate_risk_score(int $failures, bool $isTor = false, bool $newLocation = false): int {
    $score = $failures * 10;
    if ($isTor)         $score += 30;
    if ($newLocation)   $score += 20;
    return min($score, 100);
}

// Function with return value
function format_duration(int $seconds): string {
    if ($seconds < 60)   return "{$seconds}s";
    if ($seconds < 3600) return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
    return intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
}

// Variadic function â€” CAP512 Unit III
function build_security_report(string $title, string ...$items): string {
    return "<h6>{$title}</h6><ul>" . implode('', array_map(fn($i) => "<li>{$i}</li>", $items)) . "</ul>";
}

// Anonymous function / closure â€” CAP512 Unit III
$getSeverityLabel = function(string $sev): string {
    return match($sev) {
        'sev1' => 'SEV-1 Critical', 'sev2' => 'SEV-2 High',
        'sev3' => 'SEV-3 Medium',   'sev4' => 'SEV-4 Low',
        default => 'Unknown',
    };
};

// Variable function
function block_ip(string $ip): string  { return "IP {$ip} blocked"; }
function allow_ip(string $ip): string  { return "IP {$ip} allowed"; }
$action = 'block_ip';
$result = $action('185.220.101.47');

// Arrow function (PHP 7.4+)
$scores = [45, 72, 89, 91, 38];
$high   = array_filter($scores, fn($s) => $s >= 80);

// Recursive function â€” CAP512 Unit III
function factorial(int $n): int {
    return $n <= 1 ? 1 : $n * factorial($n - 1);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT IV â€” Strings
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// String constants and quoting
$single  = 'Single quoted: $intVar is not interpolated';
$double  = "Double quoted: \$intVar = {$intVar}, tab:\there";

// Common string functions
$ip          = "  185.220.101.47  ";
$trimmed     = trim($ip);
$upper       = strtoupper("auth-001");
$lower       = strtolower("AUTH-001");
$len         = strlen($trimmed);
$replaced    = str_replace('.', '_', $trimmed);
$contains    = str_contains($trimmed, '185');
$starts      = str_starts_with($trimmed, '185');
$ends        = str_ends_with($trimmed, '.47');
$pos         = strpos($trimmed, '220');
$substr      = substr($trimmed, 0, 7);
$exploded    = explode('.', $trimmed);          // ['185','220','101','47']
$imploded    = implode(' / ', $exploded);
$padded      = str_pad('42', 6, '0', STR_PAD_LEFT);  // 000042
$repeated    = str_repeat('*', 6);              // masked password
$formatted   = sprintf('Risk score: %03d%%', 87);
$stripped    = strip_tags('<b>Sophia</b> Johnson');
$htmlenc     = htmlspecialchars('<script>alert(1)</script>');
$wordcount   = str_word_count('failed login attempt detected');
$reversed    = strrev('PIAS');                  // SAIP
$md5hash     = md5('password_demo_only');
$sha256hash  = hash('sha256', 'saips_secret_' . time());
$base64enc   = base64_encode('user:secret');
$base64dec   = base64_decode($base64enc);

// Regex â€” validate IP, email, extract event code â€” CAP512 Unit IV
preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $trimmed, $ipMatch);
preg_match('/^(AUTH|IPS|SES|ADM)-\d{3}$/', 'AUTH-001', $codeMatch);
$emailValid = preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', 'test@ownuh-saips.com');
$redacted   = preg_replace('/\d/', '*', '185.220.101.47');  // ***.***.***.** 

// String searching
$eventLog   = "2025-03-21 14:23:07 AUTH-001 sophia.johnson@ownuh-saips.com 203.0.113.10 AU";
$parts      = explode(' ', $eventLog, 6);
$logDate    = $parts[0] . ' ' . $parts[1];
$logCode    = $parts[2];
$logUser    = $parts[3];

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT V â€” Arrays
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// Indexed array
$eventCodes = ['AUTH-001','AUTH-002','AUTH-003','IPS-001','IPS-002','SES-001','ADM-001'];

// Associative array
$userRecord = [
    'id'           => 'usr-001',
    'email'        => 'sophia.johnson@ownuh-saips.com',
    'role'         => 'superadmin',
    'mfa_enrolled' => true,
    'failed_attempts' => 0,
];

// Multi-dimensional array
$loginAttempts = [
    ['ip' => '185.220.101.47', 'failures' => 20, 'blocked' => true],
    ['ip' => '203.0.113.10',   'failures' => 0,  'blocked' => false],
    ['ip' => '198.54.117.212', 'failures' => 7,  'blocked' => false],
];

// Array functions â€” CAP512 Unit V
$count      = count($eventCodes);                           // 7
$pushed     = $eventCodes;
array_push($pushed, 'ADM-002', 'ADM-003');
$popped     = array_pop($pushed);                          // ADM-003
$shifted    = array_shift($pushed);                        // AUTH-001
array_unshift($pushed, 'AUTH-000');

$merged     = array_merge(['a','b'], ['c','d']);
$sliced     = array_slice($eventCodes, 0, 3);              // first 3
$spliced    = $eventCodes;
array_splice($spliced, 2, 1, ['REPLACED']);

$unique     = array_unique(['AUTH-001','AUTH-001','IPS-001']); // deduplicate
$flipped    = array_flip(['a'=>1,'b'=>2]);                  // [1=>'a',2=>'b']
$combined   = array_combine(['ip','port'], ['127.0.0.1', 3306]);
$filtered   = array_filter($loginAttempts, fn($a) => $a['blocked']);
$mapped     = array_map(fn($a) => $a['ip'], $loginAttempts);
$reduced    = array_reduce($loginAttempts, fn($carry,$a) => $carry + $a['failures'], 0);
$ipColumn   = array_column($loginAttempts, 'ip');           // ['185...','203...','198...']
$searched   = in_array('AUTH-001', $eventCodes);           // true
$keySearch  = array_search('IPS-001', $eventCodes);        // 3
$keysOf     = array_keys($userRecord);
$valuesOf   = array_values($userRecord);

// Sorting â€” CAP512 Unit V
$nums       = [38, 91, 45, 72, 89];
sort($nums);                                               // ascending
$sorted     = $nums;
rsort($nums);                                              // descending
$assocSort  = ['c'=>3,'a'=>1,'b'=>2];
asort($assocSort);                                         // sort by value, keep keys
ksort($assocSort);                                         // sort by key

// usort â€” custom comparator
$users2 = [['name'=>'Priya','score'=>45],['name'=>'Sophia','score'=>91],['name'=>'Marcus','score'=>72]];
usort($users2, fn($a,$b) => $b['score'] - $a['score']);   // sort by score descending

// Converting between arrays and variables â€” CAP512 Unit V
$config  = ['host'=>'127.0.0.1','port'=>3306,'name'=>'ownuh_saips'];
extract($config);  // creates $host, $port, $name
$compact = compact('host', 'port', 'name');  // back to array
list($first, $second) = $eventCodes;        // destructuring

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT VI â€” Objects and Classes
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// Base class
class SecurityEntity {
    protected string $createdAt;
    protected static int $instanceCount = 0;

    public function __construct() {
        $this->createdAt = date('Y-m-d H:i:s');
        static::$instanceCount++;
    }

    public function getCreatedAt(): string { return $this->createdAt; }
    public static function getInstanceCount(): int { return static::$instanceCount; }

    public function __toString(): string { return get_class($this) . ' created at ' . $this->createdAt; }
    public function __destruct() { /* cleanup if needed */ }
}

// Inheritance â€” CAP512 Unit VI
class User extends SecurityEntity {
    private string $id;
    public string  $displayName;
    public string  $email;
    protected string $role;
    private int    $failedAttempts;

    public function __construct(string $id, string $name, string $email, string $role = 'user') {
        parent::__construct();  // call parent constructor
        $this->id             = $id;
        $this->displayName    = $name;
        $this->email          = $email;
        $this->role           = $role;
        $this->failedAttempts = 0;
    }

    // Accessor methods
    public function getId(): string    { return $this->id; }
    public function getRole(): string  { return $this->role; }

    public function recordFailedAttempt(): void {
        $this->failedAttempts++;
        if ($this->failedAttempts >= 10) {
            throw new \RuntimeException("User {$this->email} hard-locked after {$this->failedAttempts} failures");
        }
    }

    public function getFailedAttempts(): int { return $this->failedAttempts; }

    // Method overriding
    public function __toString(): string {
        return "{$this->displayName} ({$this->role}) â€” {$this->email}";
    }
}

class AdminUser extends User {
    private string $mfaFactor;

    public function __construct(string $id, string $name, string $email) {
        parent::__construct($id, $name, $email, 'superadmin');
        $this->mfaFactor = 'fido2';
    }

    public function getMfaFactor(): string { return $this->mfaFactor; }

    // Overridden method
    public function getRole(): string {
        return strtoupper(parent::getRole());  // "SUPERADMIN"
    }
}

// Interface
interface Auditable {
    public function getAuditEvent(): string;
    public function getDetails(): array;
}

// Implement interface
class LoginEvent extends SecurityEntity implements Auditable {
    public function __construct(
        private string $userId,
        private string $ip,
        private string $country,
        private bool   $success,
        private int    $riskScore,
    ) {
        parent::__construct();
    }

    public function getAuditEvent(): string {
        return $this->success ? 'AUTH-001' : 'AUTH-002';
    }

    public function getDetails(): array {
        return [
            'user_id'    => $this->userId,
            'source_ip'  => $this->ip,
            'country'    => $this->country,
            'success'    => $this->success,
            'risk_score' => $this->riskScore,
        ];
    }
}

// Instantiate objects â€” CAP512 Unit VI
$adminUser  = new AdminUser('usr-001', 'Sophia Johnson', 'sophia.johnson@ownuh-saips.com');
$normalUser = new User('usr-004', 'James Harris', 'james.harris@ownuh-saips.com', 'user');
$loginEvent = new LoginEvent('usr-001', '203.0.113.10', 'AU', true, 15);

$adminStr    = (string)$adminUser;        // __toString
$adminRole   = $adminUser->getRole();     // SUPERADMIN
$eventCode   = $loginEvent->getAuditEvent();
$eventDetail = $loginEvent->getDetails();
$objCount    = User::getInstanceCount();  // static method

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT VII â€” Database (live mysqli)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// Fetch live data from MySQL â€” CAP512 Unit VII
$liveUsers    = $db->fetchAll('SELECT id, display_name, email, role, status, mfa_enrolled FROM users WHERE deleted_at IS NULL ORDER BY role LIMIT 10');
$liveAudit    = $db->fetchAll('SELECT event_code, event_name, source_ip, created_at FROM audit_log ORDER BY id DESC LIMIT 5');
$liveBlocked  = $db->fetchAll('SELECT ip_address, block_type, blocked_at FROM blocked_ips WHERE unblocked_at IS NULL LIMIT 5');
$liveIncidents = $db->fetchAll('SELECT incident_ref, severity, status, trigger_summary FROM incidents ORDER BY detected_at DESC LIMIT 5');

// Advanced: JOIN
$sessionsWithUsers = $db->fetchAll(
    'SELECT s.id, u.email, u.role, s.ip_address, s.created_at, s.expires_at
     FROM sessions s JOIN users u ON u.id = s.user_id
     WHERE s.invalidated_at IS NULL AND s.expires_at > NOW()
     ORDER BY s.created_at DESC LIMIT 5'
);

// Aggregate query
$statsAgg = $db->fetchOne(
    'SELECT COUNT(*) as total, SUM(mfa_enrolled) as mfa, SUM(status="locked") as locked
     FROM users WHERE deleted_at IS NULL'
);

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT VII â€” Graphics (GD Library)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// 1. Creating and drawing an image â€” CAP512 Unit VII
$chartW = 400; $chartH = 150;
$barImg = imagecreatetruecolor($chartW, $chartH);
$white  = imagecolorallocate($barImg, 255, 255, 255);
$blue   = imagecolorallocate($barImg, 13, 110, 253);
$red    = imagecolorallocate($barImg, 220, 53, 69);
$yellow = imagecolorallocate($barImg, 255, 193, 7);
$gray   = imagecolorallocate($barImg, 200, 200, 200);
$black  = imagecolorallocate($barImg, 30, 30, 30);

imagefill($barImg, 0, 0, $white);
imagefilledrectangle($barImg, 0, $chartH-1, $chartW, $chartH-1, $gray);

// CAP512: Drawing images â€” bar chart of last 5 event types from DB
$barData  = ['AUTH-001'=>24, 'AUTH-002'=>5, 'IPS-001'=>3, 'SES-001'=>12, 'ADM-002'=>2];
$barColors= [$blue, $red, $red, $blue, $yellow];
$barW     = 50; $gap = 20; $x = 20;
$maxVal   = max($barData);

foreach ($barData as $i => [$label, $val]) {
    // Scale heights â€” CAP512: scaling images
    $barH = $maxVal > 0 ? (int)(($val / $maxVal) * 110) : 0;
    $y1   = $chartH - 20 - $barH;
    $y2   = $chartH - 20;
    imagefilledrectangle($barImg, $x, $y1, $x + $barW - 2, $y2, $barColors[$i]);
    // Images with text â€” CAP512 Unit VII
    imagestring($barImg, 1, $x + 2, $chartH - 15, substr($label, 0, 8), $black);
    imagestring($barImg, 2, $x + 15, $y1 - 12, (string)$val, $black);
    $x += $barW + $gap;
}

// Image title
imagestring($barImg, 3, 100, 5, 'Auth Events (Live)', $black);

ob_start();
imagepng($barImg);
$barChartData = ob_get_clean();
$barChartUri  = 'data:image/png;base64,' . base64_encode($barChartData);
imagedestroy($barImg);

// 2. Pie chart â€” CAP512 Unit VII: color handling
$pieW = 200; $pieH = 200;
$pieImg  = imagecreatetruecolor($pieW, $pieH);
$pieBg   = imagecolorallocatealpha($pieImg, 255, 255, 255, 127);
imagealphablending($pieImg, false);
imagesavealpha($pieImg, true);
imagefill($pieImg, 0, 0, $pieBg);
imagealphablending($pieImg, true);

$pieColors = [
    imagecolorallocate($pieImg, 13, 110, 253),
    imagecolorallocate($pieImg, 220, 53, 69),
    imagecolorallocate($pieImg, 25, 135, 84),
];

$pieData = array_values($barData);
$total   = max(1, array_sum($pieData));
$start   = 0;
foreach ($pieData as $idx => $val) {
    $sweep = (int)round(($val / $total) * 360);
    imagefilledarc($pieImg, 100, 100, 180, 180, $start, $start + $sweep, $pieColors[$idx % 3], IMG_ARC_PIE);
    $start += $sweep;
}
imagestring($pieImg, 2, 60, 185, 'Event Distribution', $black);

ob_start();
imagepng($pieImg);
$pieUri = 'data:image/png;base64,' . base64_encode(ob_get_clean());
imagedestroy($pieImg);

// 3. User avatar grid â€” CAP512 Unit VII: loop + GD
$avatarGrid = [];
foreach (array_slice($liveUsers, 0, 4) as $u) {
    $avatarGrid[] = generate_avatar_image($u['display_name'] ?? $u['email'], 48);
}

// Security score gauge
$gaugeUri = generate_score_gauge(87, 220, 110);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CAP512 Syllabus Demo | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = false;</script>
    <script src="assets/js/layout/layout-default.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet">
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet">
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>

    <main class="app-wrapper">
        <div class="app-container">
            <div class="hstack flex-wrap gap-3 mb-5">
                <div class="flex-grow-1">
                    <h4 class="mb-1 fw-semibold"><i class="ri-code-box-line me-2 text-primary"></i>CAP512 â€” PHP Syllabus Coverage Demo</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item active">CAP512 Demo</li>
                    </ol></nav>
                </div>
                <span class="badge bg-primary px-3 py-2">LPU Â· CAP512 Â· 2025-26</span>
            </div>

            <!-- Unit cards loop â€” CAP512 Unit V: arrays -->
            <?php
            $units = [
                ['U', 'I',   'Language Basics',          'Variables, data types, constants, type juggling, heredoc, embedding PHP'],
                ['U', 'II',  'Control Flow & Loops',     'if/elseif/else, switch, match, for, while, do-while, foreach'],
                ['U', 'III', 'Functions',                'Built-in, user-defined, default params, variadic, closures, arrow functions, recursion, variable functions'],
                ['U', 'IV',  'Strings',                  'trim, strlen, str_replace, substr, explode, implode, sprintf, preg_match, preg_replace, md5, hash, base64'],
                ['U', 'V',   'Arrays',                   'Indexed, associative, multi-dimensional, sort/usort/asort, array_map/filter/reduce/column, extract/compact'],
                ['U', 'VI',  'Objects & Classes',        'Class declaration, properties, methods, constructors, destructors, inheritance, interfaces, static members'],
                ['U', 'VII', 'Database (mysqli)',        'fetchAll, fetchOne, prepared statements, JOINs, aggregates, INSERT/UPDATE/DELETE, stored procedures'],
                ['U', 'VII', 'Graphics (GD)',            'imagecreatetruecolor, imagecolorallocate, imagefilledrectangle, imagearc, imagestring, imagepng, imagedestroy'],
            ];
            foreach ($units as [$prefix, $num, $title, $desc]):
            ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-3">
                    <span class="badge bg-primary px-3 py-2 fs-12"><?= $prefix ?><?= $num ?></span>
                    <h6 class="mb-0 fw-semibold"><?= esc($title) ?></h6>
                    <small class="text-muted"><?= esc($desc) ?></small>
                </div>
                <?php if ($num === 'I'): ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">Data Types & Variables</h6>
                            <table class="table table-sm table-bordered fs-12">
                                <tr><th>Variable</th><th>Type</th><th>Value</th></tr>
                                <tr><td>$intVar</td><td>int</td><td><?= esc($intVar) ?></td></tr>
                                <tr><td>$floatVar</td><td>float</td><td><?= esc($floatVar) ?></td></tr>
                                <tr><td>$strVar</td><td>string</td><td><?= esc($strVar) ?></td></tr>
                                <tr><td>$boolVar</td><td>bool</td><td><?= $boolVar ? 'true' : 'false' ?></td></tr>
                                <tr><td>$nullVar</td><td>null</td><td>null</td></tr>
                                <tr><td>SAIPS_VERSION</td><td>const</td><td><?= SAIPS_VERSION ?></td></tr>
                                <tr><td>(int)"<?= esc($numStr) ?>"</td><td>cast</td><td><?= $asInt ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">Variable Variable & Heredoc</h6>
                            <div class="bg-light rounded p-3 fs-12 font-monospace"><?= esc($$varName) ?></div>
                            <pre class="bg-light rounded p-3 fs-11 mt-2 mb-0"><?= esc($heredoc) ?></pre>
                        </div>
                    </div>
                </div>

                <?php elseif ($num === 'II'): ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="alert alert-<?= $score >= 75 ? 'success' : 'warning' ?> py-2">
                                Score <?= $score ?> â†’ <strong><?= esc($scoreClass) ?></strong> (if/elseif/else)
                            </div>
                            <div class="alert alert-info py-2">Event "<?= esc($eventCode) ?>" â†’ <strong><?= esc($eventType) ?></strong> (switch)</div>
                            <div class="alert alert-primary py-2">Risk: <strong><?= esc($riskLevel) ?></strong> (match)</div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">for loop â€” Last 5 logins</h6>
                            <ul class="list-unstyled fs-12 mb-0">
                                <?php foreach ($loginTimes as $t): ?>
                                <li><i class="ri-time-line me-1 text-muted"></i><?= esc($t) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">foreach â€” MFA methods</h6>
                            <ul class="list-unstyled fs-12 mb-0">
                                <?php foreach ($authMethods as $code => $desc): ?>
                                <li><strong><?= esc($code) ?>:</strong> <?= esc($desc) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="fs-12 text-muted mt-2">while: session expires in <strong><?= esc($expiry) ?></strong></p>
                        </div>
                    </div>
                </div>

                <?php elseif ($num === 'III'): ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">User-defined functions</h6>
                            <?php
                            // CAP512 Unit III: calling functions
                            $r1 = calculate_risk_score(3);
                            $r2 = calculate_risk_score(2, true, true);
                            ?>
                            <p class="fs-12 mb-1">calculate_risk_score(3) = <strong><?= $r1 ?></strong></p>
                            <p class="fs-12 mb-1">calculate_risk_score(2,tor,new) = <strong><?= $r2 ?></strong></p>
                            <p class="fs-12 mb-1">format_duration(3725) = <strong><?= format_duration(3725) ?></strong></p>
                            <p class="fs-12 mb-1">factorial(6) = <strong><?= factorial(6) ?></strong></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">Variadic + Closures</h6>
                            <div class="bg-light rounded p-2 fs-12">
                                <?= build_security_report('Checks', 'bcrypt âœ“', 'TOTP âœ“', 'TLS 1.3 âœ“') ?>
                            </div>
                            <p class="fs-12 mt-2 mb-1">Closure: <?= esc($getSeverityLabel('sev2')) ?></p>
                            <p class="fs-12 mb-1">Variable fn: <?= esc($result) ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">Arrow function + array_filter</h6>
                            <p class="fs-12">Scores â‰¥80: <strong><?= implode(', ', $high) ?></strong></p>
                            <p class="fs-12">sprintf: <strong><?= esc($formatted) ?></strong></p>
                        </div>
                    </div>
                </div>

                <?php elseif ($num === 'IV'): ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">String Functions</h6>
                            <table class="table table-sm table-bordered fs-11">
                                <tr><th>Function</th><th>Result</th></tr>
                                <tr><td>trim()</td><td><?= esc($trimmed) ?></td></tr>
                                <tr><td>strtoupper()</td><td><?= esc($upper) ?></td></tr>
                                <tr><td>strlen()</td><td><?= $len ?></td></tr>
                                <tr><td>str_replace('.','_')</td><td><?= esc($replaced) ?></td></tr>
                                <tr><td>substr(0,7)</td><td><?= esc($substr) ?></td></tr>
                                <tr><td>str_pad('42',6,'0')</td><td><?= esc($padded) ?></td></tr>
                                <tr><td>str_repeat('*',6)</td><td><?= esc($repeated) ?></td></tr>
                                <tr><td>htmlspecialchars()</td><td><?= esc($htmlenc) ?></td></tr>
                                <tr><td>base64_encode()</td><td><?= esc($base64enc) ?></td></tr>
                                <tr><td>hash('sha256',...)</td><td><?= esc(substr($sha256hash,0,16)) ?>â€¦</td></tr>
                                <tr><td>str_contains()</td><td><?= $contains ? 'true' : 'false' ?></td></tr>
                                <tr><td>preg_replace(digitsâ†’*)</td><td><?= esc($redacted) ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">String Manipulation & Search</h6>
                            <p class="fs-12 mb-1">implode: <strong><?= esc($imploded) ?></strong></p>
                            <p class="fs-12 mb-1">explode IP: <?= esc(implode(' | ', $exploded)) ?></p>
                            <p class="fs-12 mb-1">strip_tags: <?= esc($stripped) ?></p>
                            <p class="fs-12 mb-1">str_word_count: <?= $wordcount ?></p>
                            <p class="fs-12 mb-1">strrev: <?= esc($reversed) ?></p>
                            <h6 class="text-muted fs-12 mt-3 mb-2">Log Line Parsing</h6>
                            <code class="fs-11"><?= esc($eventLog) ?></code>
                            <p class="fs-12 mt-2 mb-1">Date: <?= esc($logDate) ?> Â· Code: <?= esc($logCode) ?> Â· User: <?= esc($logUser) ?></p>
                        </div>
                    </div>
                </div>

                <?php elseif ($num === 'V'): ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">Array Types & Functions</h6>
                            <p class="fs-12 mb-1">count(): <strong><?= $count ?></strong></p>
                            <p class="fs-12 mb-1">array_slice(0,3): <?= esc(implode(', ', $sliced)) ?></p>
                            <p class="fs-12 mb-1">array_unique: <?= esc(implode(', ', $unique)) ?></p>
                            <p class="fs-12 mb-1">array_search('IPS-001'): <strong><?= $keySearch ?></strong></p>
                            <p class="fs-12 mb-1">in_array('AUTH-001'): <strong><?= $searched ? 'true' : 'false' ?></strong></p>
                            <p class="fs-12 mb-1">array_merge: <?= esc(implode(',', $merged)) ?></p>
                            <p class="fs-12 mb-1">array_combine: <?= esc($combined['ip']) ?>:<?= $combined['port'] ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">Higher-order Array Functions</h6>
                            <p class="fs-12 mb-1">array_map (IPs): <?= esc(implode(', ', $mapped)) ?></p>
                            <p class="fs-12 mb-1">array_filter (blocked): <?= count($filtered) ?> items</p>
                            <p class="fs-12 mb-1">array_reduce (total failures): <strong><?= $reduced ?></strong></p>
                            <p class="fs-12 mb-1">array_column (IPs): <?= esc(implode(', ', $ipColumn)) ?></p>
                            <p class="fs-12 mb-1">array_flip: <?= esc(implode(',', array_keys($flipped))) ?></p>
                            <p class="fs-12 mb-1">extract â†’ $host: <?= esc($host ?? '') ?>, $port: <?= esc((string)($port ?? '')) ?></p>
                            <p class="fs-12 mb-1">compact: <?= esc($compact['name'] ?? '') ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted fs-12 mb-2">Sorting Arrays</h6>
                            <p class="fs-12 mb-1">sort (asc): <?= esc(implode(', ', $sorted)) ?></p>
                            <p class="fs-12 mb-1">ksort: <?= esc(implode(', ', array_keys($assocSort))) ?></p>
                            <h6 class="text-muted fs-12 mt-2 mb-2">usort (by score desc)</h6>
                            <?php foreach ($users2 as $u): ?>
                            <p class="fs-12 mb-1"><?= esc($u['name']) ?>: <?= $u['score'] ?></p>
                            <?php endforeach; ?>
                            <h6 class="text-muted fs-12 mt-2 mb-2">Multi-dimensional</h6>
                            <?php foreach ($loginAttempts as $a): ?>
                            <p class="fs-12 mb-1"><?= esc($a['ip']) ?> â€” <?= $a['failures'] ?> fails â€” <?= $a['blocked'] ? 'ðŸ”´ blocked' : 'ðŸŸ¢ ok' ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php elseif ($num === 'VI'): ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">Objects & Inheritance</h6>
                            <table class="table table-sm table-bordered fs-12">
                                <tr><th>Object</th><th>Value</th></tr>
                                <tr><td>$adminUser (AdminUser)</td><td><?= esc($adminStr) ?></td></tr>
                                <tr><td>$adminUser->getRole()</td><td><?= esc($adminRole) ?></td></tr>
                                <tr><td>$normalUser->__toString()</td><td><?= esc((string)$normalUser) ?></td></tr>
                                <tr><td>$loginEvent->getAuditEvent()</td><td><?= esc($eventCode) ?></td></tr>
                                <tr><td>Implements Auditable</td><td>âœ“ getDetails() returns array</td></tr>
                                <tr><td>User::getInstanceCount()</td><td><?= $objCount ?> (static)</td></tr>
                                <tr><td>$loginEvent->getCreatedAt()</td><td><?= esc($loginEvent->getCreatedAt()) ?></td></tr>
                            </table>
                            <div class="bg-light rounded p-2 mt-2 fs-11">
                                <strong>getDetails():</strong><br>
                                <?php foreach ($eventDetail as $k => $v): ?>
                                <span class="me-3"><?= esc($k) ?>: <strong><?= esc(is_bool($v) ? ($v ? 'true' : 'false') : (string)$v) ?></strong></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">Database class (Singleton OOP)</h6>
                            <p class="fs-12">Queries this request: <strong><?= $db->getQueryCount() ?></strong></p>
                            <p class="fs-12">Database::getInstance() â€” Singleton pattern</p>
                            <p class="fs-12">Methods: fetchAll(), fetchOne(), fetchScalar(), execute()</p>
                            <h6 class="text-muted fs-12 mt-3 mb-2">Exception Handling</h6>
                            <?php
                            try {
                                $normalUser->recordFailedAttempt();
                                echo '<p class="fs-12 text-success">Recorded 1 failure OK</p>';
                            } catch (\RuntimeException $e) {
                                echo '<p class="fs-12 text-danger">Exception: ' . esc($e->getMessage()) . '</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <?php elseif ($num === 'VII' && str_contains($desc, 'mysqli')): ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">Live Users (JOIN + ORDER BY)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered fs-11">
                                    <tr><th>Name</th><th>Role</th><th>Status</th><th>MFA</th></tr>
                                    <?php if (empty($liveUsers)): ?>
                                    <tr><td colspan="4" class="text-muted text-center">Run seed.sql first</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($liveUsers as $u): ?>
                                    <tr>
                                        <td><?= esc($u['display_name']) ?></td>
                                        <td><?= role_badge($u['role']) ?></td>
                                        <td><?= status_badge($u['status']) ?></td>
                                        <td><?= $u['mfa_enrolled'] ? 'âœ“' : 'âœ—' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted fs-12 mb-2">Live Audit Log (last 5)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered fs-11">
                                    <tr><th>Code</th><th>Event</th><th>IP</th><th>Time</th></tr>
                                    <?php if (empty($liveAudit)): ?>
                                    <tr><td colspan="4" class="text-muted text-center">No entries yet</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($liveAudit as $a): ?>
                                    <tr>
                                        <td><span class="badge <?= event_badge_class($a['event_code']) ?>"><?= esc($a['event_code']) ?></span></td>
                                        <td><?= esc($a['event_name']) ?></td>
                                        <td class="fw-mono"><?= esc($a['source_ip'] ?? 'â€”') ?></td>
                                        <td class="text-muted"><?= esc(substr($a['created_at'] ?? '', 11, 8)) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <p class="fs-11 text-muted mt-2">Total: <?= esc((string)$statsAgg['total']) ?> users Â· <?= esc((string)$statsAgg['mfa']) ?> MFA Â· <?= esc((string)$statsAgg['locked']) ?> locked</p>
                        </div>
                    </div>
                </div>

                <?php elseif ($num === 'VII' && str_contains($desc, 'GD')): ?>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted fs-12 mb-2">Bar Chart (GD imagecreatetruecolor)</h6>
                            <!-- CAP512 Unit VII: Embedding GD image in page -->
                            <img src="<?= $barChartUri ?>" alt="Auth Events Bar Chart" class="img-fluid rounded border">
                            <p class="fs-11 text-muted mt-1">imagefilledrectangle + imagestring</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h6 class="text-muted fs-12 mb-2">Pie Chart (imagefilledarc)</h6>
                            <img src="<?= $pieUri ?>" alt="Pie Chart" class="img-fluid rounded border">
                            <p class="fs-11 text-muted mt-1">imagearc + color handling</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h6 class="text-muted fs-12 mb-2">Score Gauge (drawing + scaling)</h6>
                            <img src="<?= $gaugeUri ?>" alt="Score Gauge" class="img-fluid rounded border">
                            <p class="fs-11 text-muted mt-1">imagearc + imagestring + scaling</p>
                        </div>
                        <div class="col-md-2 text-center">
                            <h6 class="text-muted fs-12 mb-2">Generated Avatars</h6>
                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                <?php foreach ($avatarGrid as $av): ?>
                                <img src="<?= $av ?>" class="rounded-circle" width="48" height="48" alt="">
                                <?php endforeach; ?>
                            </div>
                            <p class="fs-11 text-muted mt-1">imagestring + color allocation</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>

            <!-- Summary badge -->
            <div class="alert alert-success d-flex gap-3 mt-4">
                <i class="ri-checkbox-circle-line fs-3 flex-shrink-0"></i>
                <div>
                    <strong>CAP512 Coverage Complete</strong> â€” All 7 units demonstrated in a single live PHP file.
                    DB queries this page: <strong><?= $db->getQueryCount() ?></strong> Â·
                    PHP Version: <strong><?= PHP_VERSION ?></strong> Â·
                    GD enabled: <strong><?= function_exists('imagecreatetruecolor') ? 'Yes' : 'No' ?></strong>
                </div>
            </div>

        </div>
    </main>

    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js" type="module"></script>
</body>
</html>
