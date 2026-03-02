<?php
require_once '../config/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $idNumber = trim($_POST['id_number']);
    $department = trim($_POST['department']);

    // Check duplicate ID
    $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
    $check->execute([$idNumber]);

    if ($check->fetch()) {
        $error = "ID Number already registered.";
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO users 
            (name, id_number, department, role, wins, current_game, auto_mode, card_count)
            VALUES (?, ?, ?, 'Player', 0, NULL, 0, 1)
        ");

        if ($stmt->execute([$name, $idNumber, $department])) {
            $success = "Registration successful!";
        } else {
            $error = "Something went wrong.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Venue Registration</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow rounded-4">
                <div class="card-body p-4">

                    <h4 class="text-center mb-4">Venue Registration</h4>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ID Number</label>
                            <input type="text" name="id_number" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-3">
                            Register
                        </button>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>