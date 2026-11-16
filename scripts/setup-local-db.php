<?php

// Chỉ khởi tạo instance riêng của đồ án trên 127.0.0.1:3307. Không thao tác MySQL80.
$root = dirname(__DIR__);
$credentialFile = $root.'/.runtime/mysql-admin.cnf';
if (file_exists($credentialFile)) {
    exit("Instance đã có thông tin quản trị trong .runtime/mysql-admin.cnf; không khởi tạo lại.\n");
}
$db = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$version = $db->query('SELECT VERSION()')->fetchColumn();
$dataDirectory = $db->query('SELECT @@datadir')->fetchColumn();
if (! str_starts_with($version, '8.4.') || strtolower((string) realpath($dataDirectory)) !== strtolower((string) realpath($root.'/.runtime/mysql'))) {
    throw new RuntimeException('Từ chối: đây không phải instance MySQL 8.4 riêng của dự án.');
}
$adminPassword = bin2hex(random_bytes(24));
$appPassword = bin2hex(random_bytes(24));
foreach (['techforge', 'techforge_test'] as $database) {
    $db->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
$db->exec("CREATE USER 'techforge'@'127.0.0.1' IDENTIFIED BY ".$db->quote($appPassword));
foreach (['techforge', 'techforge_test'] as $database) {
    $db->exec("GRANT ALL PRIVILEGES ON `$database`.* TO 'techforge'@'127.0.0.1'");
}
// Lưu thông tin cục bộ trước khi đổi mật khẩu; các file này nằm trong .gitignore.
file_put_contents($credentialFile, "[client]\nuser=root\npassword=$adminPassword\nhost=127.0.0.1\nport=3307\nprotocol=TCP\n");
$env = file_get_contents($root.'/.env');
$values = ['APP_NAME' => 'TECHFORGE', 'APP_URL' => 'http://127.0.0.1:8000', 'APP_LOCALE' => 'vi',
    'APP_FALLBACK_LOCALE' => 'vi', 'APP_FAKER_LOCALE' => 'vi_VN', 'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'techforge',
    'DB_USERNAME' => 'techforge', 'DB_PASSWORD' => $appPassword,
    'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync'];
foreach ($values as $key => $value) {
    $pattern = '/^(?:# )?'.preg_quote($key, '/').'=.*$/m';
    $env = preg_match($pattern, $env) ? preg_replace($pattern, "$key=$value", $env) : $env."\n$key=$value\n";
}
file_put_contents($root.'/.env', $env);
$db->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$db->quote($adminPassword));
echo "Đã cấu hình MySQL $version, database techforge và techforge_test; mật khẩu chỉ lưu cục bộ.\n";
