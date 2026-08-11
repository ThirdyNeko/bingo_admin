<?php

session_name('Bingo');
session_start();

require_once '../config/db.php';

header('Content-Type: application/json');


// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'status' => 'danger',
        'message' => 'You must be logged in.'
    ]);

    exit;
}


// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'status' => 'danger',
        'message' => 'Invalid request method.'
    ]);

    exit;
}


// Get inputs
$currentPassword = trim(
    $_POST['current_password'] ?? ''
);

$newPassword = trim(
    $_POST['new_password'] ?? ''
);

$confirmPassword = trim(
    $_POST['confirm_password'] ?? ''
);


// Validate fields
if (
    empty($currentPassword) ||
    empty($newPassword) ||
    empty($confirmPassword)
) {

    echo json_encode([
        'status' => 'danger',
        'message' => 'All fields are required.'
    ]);

    exit;
}


// Confirm new password
if ($newPassword !== $confirmPassword) {

    echo json_encode([
        'status' => 'danger',
        'message' => 'New password and confirmation do not match.'
    ]);

    exit;
}


try {

    /*
     * Session stores users.id
     */
    $stmt = $pdo->prepare("
        SELECT password
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([
        $_SESSION['user_id']
    ]);

    $user = $stmt->fetch();


    if (!$user) {

        echo json_encode([
            'status' => 'danger',
            'message' => 'User not found.'
        ]);

        exit;
    }


    /*
     * Verify current password
     */
    if (
        empty($user['password']) ||
        !password_verify(
            $currentPassword,
            $user['password']
        )
    ) {

        echo json_encode([
            'status' => 'danger',
            'message' => 'Current password is incorrect.'
        ]);

        exit;
    }


    /*
     * Hash new password
     */
    $newHashedPassword = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );


    /*
     * Update password using users.id
     */
    $update = $pdo->prepare("
        UPDATE users
        SET password = ?
        WHERE id = ?
    ");

    $update->execute([
        $newHashedPassword,
        $_SESSION['user_id']
    ]);


    echo json_encode([
        'status' => 'success',
        'message' => 'Password changed successfully!'
    ]);

    exit;


} catch (PDOException $e) {

    echo json_encode([
        'status' => 'danger',
        'message' => 'Database error: ' . $e->getMessage()
    ]);

    exit;
}
