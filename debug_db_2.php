<?php
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}
$db_host = getenv('DB_HOST') ?: 'localhost';
if (PHP_OS_FAMILY === 'Windows' && $db_host === 'db') {
    $db_host = 'localhost';
}
$db_name = getenv('DB_NAME') ?: 'coticefacil-db';
$db_user = getenv('DB_USER') ?: 'cotice-user';
$db_pass = getenv('DB_PASSWORD') ?: 'cotice_temp_123';

echo "Host: $db_host\nUser: $db_user\nPass: $db_pass\nDB: $db_name\n";
?>
