<?php

// Chuẩn bị ứng dụng mà không đổi APP_KEY của bản cài đã tồn tại.
$root = dirname(__DIR__);
chdir($root);
if (! file_exists('.env')) {
    copy('.env.example', '.env');
}
$environment = file_get_contents('.env');
if (! preg_match('/^APP_KEY=.+$/m', $environment)) {
    passthru(escapeshellarg(PHP_BINARY).' artisan key:generate', $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}
passthru(escapeshellarg(PHP_BINARY).' artisan migrate --seed', $exitCode);
exit($exitCode);
