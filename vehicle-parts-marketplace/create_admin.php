<?php
session_start();
include 'includes/config.php';

// Only run once
if (!isset($_GET['run']) || $_GET['run'] !== 'true') {
    die("Access denied.");
}

// Check if admin already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute(['admin@gmail.com']);
if ($stmt->fetch()) {
    die("Admin already exists.");
}

$password = password_hash('123456', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password, role, role_status) 
    VALUES (?, ?, ?, 'admin', 'approved')
");
$stmt->execute([
    'Main Admin',
    'admin@gmail.com',
    $password
]);

echo "Admin created successfully!";
// http://localhost/create_admin.php?run=true