<?php

require_once '../config/db.php';

header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }


    $name = trim($_POST['name'] ?? '');

    $autoMode = isset($_POST['auto_mode'])
        ? (int) $_POST['auto_mode']
        : null;

    $cardCount = isset($_POST['card_count'])
        ? (int) $_POST['card_count']
        : null;


    if ($name === '') {
        throw new Exception('User name is required.');
    }


    /*
     * At least one setting must be updated.
     */
    if ($autoMode === null && $cardCount === null) {
        throw new Exception('No settings to update.');
    }


    /*
     * Validate auto mode
     */
    if ($autoMode !== null) {

        if (!in_array($autoMode, [0, 1], true)) {
            throw new Exception('Invalid auto mode.');
        }
    }


    /*
     * Validate card count
     */
    if ($cardCount !== null) {

        if ($cardCount < 1) {
            $cardCount = 1;
        }
    }


    /*
     * Update both settings
     */
    if ($autoMode !== null && $cardCount !== null) {

        $stmt = $pdo->prepare("
            UPDATE users

            SET
                auto_mode = ?,
                card_count = ?

            WHERE
                name = ?
                AND role IN ('admin', 'priority')
        ");

        $stmt->execute([
            $autoMode,
            $cardCount,
            $name
        ]);


    /*
     * Update auto mode only
     */
    } elseif ($autoMode !== null) {

        $stmt = $pdo->prepare("
            UPDATE users

            SET
                auto_mode = ?

            WHERE
                name = ?
                AND role IN ('admin', 'priority')
        ");

        $stmt->execute([
            $autoMode,
            $name
        ]);


    /*
     * Update card count only
     */
    } else {

        $stmt = $pdo->prepare("
            UPDATE users

            SET
                card_count = ?

            WHERE
                name = ?
                AND role IN ('admin', 'priority')
        ");

        $stmt->execute([
            $cardCount,
            $name
        ]);
    }


    echo json_encode([
        'success' => true,
        'message' => 'Player settings updated successfully.'
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}