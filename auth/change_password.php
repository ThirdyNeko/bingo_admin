<?php
session_name('Bingo');
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'You must be logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method.']);
    exit;
}

// Trim inputs
$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

// Validate fields
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['status' => 'danger', 'message' => 'All fields are required.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'danger', 'message' => 'New password and confirmation do not match.']);
    exit;
}

try {
    // Use id_number because your session stores it
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id_number = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['status' => 'danger', 'message' => 'User not found.']);
        exit;
    }

    // Verify current password (hashed)
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode(['status' => 'danger', 'message' => 'Current password is incorrect.']);
        exit;
    }

    // Hash new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update in database
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id_number = ?");
    $update->execute([$newHashedPassword, $_SESSION['user_id']]);

    echo json_encode(['status' => 'success', 'message' => 'Password changed successfully!']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'danger', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}