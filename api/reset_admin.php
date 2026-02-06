<?php
// api/reset_admin.php - SECURITY: DELETE THIS FILE AFTER USE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

header("Content-Type: text/plain; charset=UTF-8");

echo "Starting Admin Reset...\n";

try {
    $db = getDB();
    $username = 'admin';
    $password = 'Admin123';
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, active = 1, failed_attempts = 0, lockout_until = NULL WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
        echo "SUCCESS: Admin password reset to 'Admin123' and account reactivated.\n";
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, active) VALUES (?, ?, 'Administrator', 'admin', 1)");
        $stmt->execute([$username, $hash]);
        echo "SUCCESS: Admin user created with password 'Admin123'.\n";
    }

    echo "CRITICAL: Delete this file (api/reset_admin.php) immediately for security.";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
