<?php
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}

$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

echo "User: [" . $user . "] (Len: " . strlen($user) . ")\n";
echo "Pass: [" . $pass . "] (Len: " . strlen($pass) . ")\n";

// Display hex of pass to see hidden chars
echo "Pass Hex: " . bin2hex($pass) . "\n";
