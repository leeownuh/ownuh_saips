<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$issues = [];
$checked = 0;

$excludeFragments = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '.env',
    DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'saips-screenshots' . DIRECTORY_SEPARATOR,
];

$textExtensions = [
    'php', 'md', 'txt', 'json', 'yml', 'yaml', 'xml', 'ps1', 'sh', 'sql', 'html', 'js', 'css', 'bat'
];

$patterns = [
    'OpenAI/Groq style key' => '/\bgsk_[A-Za-z0-9_]{20,}\b/',
    'OpenAI style secret key' => '/\bsk-(proj-)?[A-Za-z0-9_-]{20,}\b/',
    'SendGrid API key' => '/\bSG\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\b/',
    'GitHub personal access token' => '/\bghp_[A-Za-z0-9]{20,}\b/',
    'Google API key' => '/\bAIza[0-9A-Za-z\-_]{20,}\b/',
];

function isBenignAssignmentValue(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (preg_match('/^(your_|change_me|example|placeholder|<|strong_random_password|empty|blank|replace_)/i', $value)) {
        return true;
    }

    if (str_contains($value, '$') || str_contains($value, '${')) {
        return true;
    }

    return false;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    foreach ($excludeFragments as $fragment) {
        if (str_contains($path, $fragment)) {
            continue 2;
        }
    }

    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $textExtensions, true)) {
        continue;
    }

    $contents = @file_get_contents($path);
    if ($contents === false || $contents === '') {
        continue;
    }

    $checked++;

    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
            $line = 1 + substr_count(substr($contents, 0, (int)$match[0][1]), "\n");
            $issues[] = sprintf('%s at %s:%d', $label, $path, $line);
        }
    }

    if (preg_match_all('/^(OPENAI_API_KEY|SENDGRID_API_KEY|DB_PASS|DB_AUTH_PASS)\s*=\s*(.+)$/m', $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($matches as $match) {
            $value = trim($match[2][0]);
            if (isBenignAssignmentValue($value)) {
                continue;
            }
            $line = 1 + substr_count(substr($contents, 0, (int)$match[0][1]), "\n");
            $issues[] = sprintf('Inline secret-like assignment for %s at %s:%d', $match[1][0], $path, $line);
        }
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Potential secrets detected:\n - " . implode("\n - ", $issues) . "\n");
    exit(1);
}

fwrite(STDOUT, "Secret scan passed across {$checked} text files.\n");
