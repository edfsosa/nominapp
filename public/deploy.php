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

// Composer requires HOME when running from a web context
putenv('HOME=/home/sedvouco');
putenv('COMPOSER_HOME=/home/sedvouco/.composer');

$php    = '/opt/cpanel/ea-php82/root/usr/bin/php';
$artisan  = "{$php} artisan";
$composer = "{$php} /opt/cpanel/composer/bin/composer";

$steps = [
    "{$artisan} down 2>&1 || true",
    "git pull origin main 2>&1",
    "{$composer} install --no-dev --optimize-autoloader 2>&1",
    "{$artisan} migrate --force 2>&1",
    "{$artisan} optimize:clear 2>&1",
    "{$artisan} optimize 2>&1",
    "{$artisan} filament:optimize 2>&1",
    "{$artisan} queue:restart 2>&1",
    "{$artisan} up 2>&1",
];

$failed = false;

file_put_contents($log, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] === Deploy started ===' . PHP_EOL, FILE_APPEND);

foreach ($steps as $cmd) {
    $result = [];
    $code   = 0;

    exec("cd {$base} && {$cmd}", $result, $code);

    file_put_contents(
        $log,
        '[' . date('H:i:s') . '] $ ' . $cmd . PHP_EOL . implode(PHP_EOL, $result) . PHP_EOL,
        FILE_APPEND
    );

    if ($code !== 0 && ! str_contains($cmd, '|| true')) {
        $failed = true;
        exec("cd {$base} && {$artisan} up 2>&1");
        file_put_contents($log, '[' . date('H:i:s') . '] Step failed — site restored.' . PHP_EOL, FILE_APPEND);
        break;
    }
}

file_put_contents($log, '[' . date('H:i:s') . '] === Deploy ' . ($failed ? 'FAILED' : 'OK') . ' ===' . PHP_EOL, FILE_APPEND);

http_response_code($failed ? 500 : 200);
header('Content-Type: application/json');
echo json_encode(['status' => $failed ? 'failed' : 'ok']);
