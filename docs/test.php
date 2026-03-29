<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/api/helpers.php';

$pdo = getDB();
echo "DB OK\n";

$hash = password_hash('test123', PASSWORD_DEFAULT);
echo "Hash OK: " . $hash . "\n";

$stmt = $pdo->prepare('INSERT INTO users (session_token, role) VALUES (?, "user")');
$stmt->execute([bin2hex(random_bytes(32))]);
echo "Insert OK, id=" . $pdo->lastInsertId() . "\n";