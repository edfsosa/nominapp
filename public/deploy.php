<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Read DEPLOY_TOKEN from .env
$envPath = dirname(__DIR__) . '/.env';
preg_match('/^DEPLOY_TOKEN=(.+)$/m', (string) @file_get_contents($envPath), $m);
$expectedToken = trim($m[1] ?? '');

$receivedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

if ($expectedToken === '' || ! hash_equals($expectedToken, $receivedToken)) {
    http_response_code(401);
    exit;
}

$base = dirname(__DIR__);
$log  = $base . '/storage/logs/deploy.log';

set_time_limit(300);
ignore_user_abort(true);

// Composer requires HOME when running from a web context (not set in Apache/PHP-FPM)
$homeDir = posix_getpwuid(posix_geteuid())['dir'] ?? sys_get_temp_dir();
putenv("HOME={$homeDir}");
putenv("COMPOSER_HOME={$homeDir}/.composer");

$php      = '/opt/cpanel/ea-php82/root/usr/bin/php';
$artisan  = "{$php} artisan";
$composer = "{$php} /opt/cpanel/composer/bin/composer";

// Read DB credentials from .env for backup
$envContent = (string) @file_get_contents($envPath);
$readEnv = function (string $key) use ($envContent): string {
    preg_match('/^' . preg_quote($key, '/') . '=(.+)$/m', $envContent, $m);
    return trim($m[1] ?? '');
};

$dbHost = $readEnv('DB_HOST') ?: '127.0.0.1';
$dbPort = $readEnv('DB_PORT') ?: '3306';
$dbName = $readEnv('DB_DATABASE');
$dbUser = $readEnv('DB_USERNAME');
$dbPass = $readEnv('DB_PASSWORD');

/**
 * Run a database backup before migrations.
 * Stores up to 7 daily backups in storage/backups/, deleting older ones.
 */
$backupDir  = "{$base}/storage/backups";
$backupFile = "{$backupDir}/db_" . date('Y-m-d_H-i-s') . ".sql.gz";

@mkdir($backupDir, 0750, true);

$steps = [
    "{$artisan} down 2>&1 || true",
    "git pull origin main 2>&1",
    "{$composer} install --no-dev --optimize-autoloader 2>&1",
    "mysqldump --host={$dbHost} --port={$dbPort} --user={$dbUser} --password=" . escapeshellarg($dbPass) . " --single-transaction --quick {$dbName} 2>&1 | gzip > " . escapeshellarg($backupFile) . " && echo 'Backup OK: {$backupFile}'",
    "{$artisan} migrate --force 2>&1",
    "{$artisan} optimize:clear 2>&1",
    "{$artisan} optimize 2>&1",
    "{$artisan} filament:optimize 2>&1",
    "{$artisan} queue:restart 2>&1",
    "{$artisan} up 2>&1",
];

$failed   = false;
$pulled   = false; // tracks whether git pull already ran
$prevSha  = '';

// Record the current HEAD before pulling so we can roll back if needed
exec("cd {$base} && git rev-parse HEAD 2>&1", $shaOut);
$prevSha = trim($shaOut[0] ?? '');

file_put_contents($log, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] === Deploy started (prev SHA: ' . substr($prevSha, 0, 8) . ') ===' . PHP_EOL, FILE_APPEND);

foreach ($steps as $cmd) {
    $result = [];
    $code   = 0;

    exec("cd {$base} && {$cmd}", $result, $code);

    file_put_contents(
        $log,
        '[' . date('H:i:s') . '] $ ' . $cmd . PHP_EOL . implode(PHP_EOL, $result) . PHP_EOL,
        FILE_APPEND
    );

    // Mark once git pull has run so rollback makes sense from here on
    if (str_contains($cmd, 'git pull')) {
        $pulled = true;
    }

    if ($code !== 0 && ! str_contains($cmd, '|| true')) {
        $failed = true;

        // Roll back to previous commit if pull already ran and we have a SHA
        if ($pulled && $prevSha !== '') {
            exec("cd {$base} && git reset --hard {$prevSha} 2>&1", $resetOut);
            file_put_contents(
                $log,
                '[' . date('H:i:s') . '] Rolled back to ' . substr($prevSha, 0, 8) . ': ' . implode(' ', $resetOut) . PHP_EOL,
                FILE_APPEND
            );
        }

        exec("cd {$base} && {$artisan} up 2>&1");
        file_put_contents($log, '[' . date('H:i:s') . '] Step failed — site restored.' . PHP_EOL, FILE_APPEND);
        break;
    }
}

// Keep only the 7 most recent backups
$backups = glob("{$backupDir}/db_*.sql.gz") ?: [];
if (count($backups) > 7) {
    sort($backups); // oldest first
    foreach (array_slice($backups, 0, count($backups) - 7) as $old) {
        @unlink($old);
        file_put_contents($log, '[' . date('H:i:s') . '] Removed old backup: ' . basename($old) . PHP_EOL, FILE_APPEND);
    }
}

file_put_contents($log, '[' . date('H:i:s') . '] === Deploy ' . ($failed ? 'FAILED' : 'OK') . ' ===' . PHP_EOL, FILE_APPEND);

$logContent = @file_get_contents($log) ?: '';
// Extract only the last deploy run (from the last "=== Deploy started ===" onwards)
$lastRun = $logContent;
if (($pos = strrpos($logContent, '=== Deploy started ===')) !== false) {
    $lastRun = substr($logContent, max(0, $pos - 24)); // include the timestamp prefix
}

http_response_code($failed ? 500 : 200);
header('Content-Type: application/json');
echo json_encode(['status' => $failed ? 'failed' : 'ok', 'log' => $lastRun]);
