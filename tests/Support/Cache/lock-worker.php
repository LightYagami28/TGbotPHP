<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/**
 * Holds an exclusive cache lock, signals the parent process, then mutates the
 * cache. All inputs arrive through the environment so the launching command
 * stays fixed.
 */
$lockFile = getenv('TGBOTP_LOCK_FILE');
$readyFile = getenv('TGBOTP_READY_FILE');
$action = getenv('TGBOTP_ACTION');
$targetFile = getenv('TGBOTP_TARGET_FILE');
$payload = getenv('TGBOTP_PAYLOAD');

if (!is_string($lockFile) || !is_string($readyFile) || !is_string($action)) {
    fwrite(STDERR, "Missing lock worker configuration\n");
    exit(1);
}

$lock = fopen($lockFile, 'c');

if ($lock === false || !flock($lock, LOCK_EX)) {
    exit(1);
}

file_put_contents($readyFile, '1');
sleep(1);

if ($action === 'write' && is_string($targetFile) && is_string($payload)) {
    file_put_contents($targetFile, $payload);
} elseif ($action === 'unlink' && is_string($targetFile)) {
    unlink($targetFile);
}

flock($lock, LOCK_UN);
fclose($lock);
