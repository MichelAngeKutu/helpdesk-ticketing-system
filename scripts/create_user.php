<?php
// Command-line tool to create accounts (there is no public sign-up page).
// Usage:
//   php scripts/create_user.php <username> "<Full Name>" <user|technician|admin>
// You will be asked to type the password.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this script from the command line.');
}
if ($argc !== 4) {
    exit("Usage: php scripts/create_user.php <username> \"<Full Name>\" <user|technician|admin>\n");
}

[, $username, $fullName, $role] = $argv;
if (!in_array($role, ['user', 'technician', 'admin'], true)) {
    exit("Role must be user, technician or admin.\n");
}

echo 'Password: ';
$password = trim((string) fgets(STDIN));
if (strlen($password) < 8) {
    exit("Password must be at least 8 characters.\n");
}

$config = require __DIR__ . '/../config/config.php';
$pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $pdo->prepare('INSERT INTO users (username, full_name, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$username, $fullName, password_hash($password, PASSWORD_DEFAULT), $role, date('Y-m-d H:i:s')]);

echo "Created {$role} account '{$username}'.\n";
