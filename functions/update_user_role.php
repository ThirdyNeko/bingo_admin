<?php

require_once '../config/db.php';

header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }


    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $role = trim($_POST['role'] ?? '');


    if ($name === '') {
        throw new Exception('User name is required.');
    }


    if ($department === '') {
        throw new Exception('Department is required.');
    }


    $allowedRoles = [
        'player',
        'priority',
        'gamemaster'
    ];


    if (!in_array($role, $allowedRoles, true)) {
        throw new Exception('Invalid role.');
    }


    /*
     * Game Master
     *
     * Set default password.
     */
    if ($role === 'gamemaster') {

        $defaultPassword = password_hash(
            'Password',
            PASSWORD_DEFAULT
        );


        $stmt = $pdo->prepare("
            UPDATE users

            SET
                role = ?,
                password = ?

            WHERE
                name = ?
                AND department = ?
        ");


        $stmt->execute([
            $role,
            $defaultPassword,
            $name,
            $department
        ]);

    } else {

        /*
         * Player / Priority
         *
         * Remove password.
         */
        $stmt = $pdo->prepare("
            UPDATE users

            SET
                role = ?,
                password = NULL

            WHERE
                name = ?
                AND department = ?
        ");


        $stmt->execute([
            $role,
            $name,
            $department
        ]);
    }


    /*
     * Make sure a user was actually updated.
     */
    if ($stmt->rowCount() === 0) {
        throw new Exception('User was not found or no changes were made.');
    }


    echo json_encode([
        'success' => true,
        'message' => 'Role updated successfully.'
    ]);


} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}