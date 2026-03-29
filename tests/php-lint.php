<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checked = 0;
$excludeDirs = [
    $root . DIRECTORY_SEPARATOR . '.git',
    $root . DIRECTORY_SEPARATOR . 'vendor',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'data',
    $root . DIRECTORY_SEPARATOR . 'saips-screenshots',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $skip = false;
    foreach ($excludeDirs as $dir) {
        if (str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || $path === $dir) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $checked++;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        $failures[] = $path . PHP_EOL . implode(PHP_EOL, $output);
    }
    $output = [];
}

if ($failures !== []) {
    fwrite(STDERR, "PHP lint failures detected:\n\n" . implode("\n\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Linted {$checked} PHP files successfully.\n");
