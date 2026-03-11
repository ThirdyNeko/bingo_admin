<?php
session_name('Bingo');
session_start();
date_default_timezone_set('Asia/Manila');

require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_number = trim($_POST['id_number'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($id_number) || empty($password)) {
        $error = "Please enter your ID and password.";
    } else {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch();

        if (!$user || !in_array($user['role'], ['admin','gamemaster'])) {
            $error = "Access denied. Admins only.";
        } else {

            if (!password_verify($password, $user['password'])) {
                $error = "Incorrect password.";
            } else {

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['user_id'] = $user['id_number'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                header("Location: ../index.php");
                exit;
            }

        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class="bg-dark d-flex align-items-center" style="min-height:100vh;">

<style>
    body {
        background: radial-gradient(circle at top, #1f1f1f, #0f0f0f);
    }
    .card {
        background-color: #1a1a1a;
        color: #fff;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .form-control {
        background-color: #2a2a2a;
        border: 1px solid #444;
        color: #fff;
    }
    .form-control:focus {
        background-color: #2a2a2a;
        color: #fff;
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
    }
    .form-control::placeholder {
        color: #aaa;
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">

            <div class="card shadow-lg rounded-4">
                <div class="card-body p-4">

                    <h3 class="text-center mb-4">🔐 Admin Login</h3>

                    <?php if ($error): ?>
                        <div class="alert alert-danger text-center">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">Admin ID Number</label>
                            <input type="text"
                                   name="id_number"
                                   class="form-control form-control-lg text-center"
                                   placeholder="Enter Admin ID"
                                   required>
                        </div>

                        <div class="input-group mb-4">
                            <input type="password"
                                id="password"
                                name="password"
                                class="form-control form-control-lg text-center"
                                placeholder="Enter Password"
                                required>
                            <span class="input-group-text toggle-password" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>

                        <style>
                        /* Make the toggle button match the dark input style */
                        .toggle-password {
                            background-color: #2a2a2a;
                            border: 1px solid #444;
                            color: #aaa;
                            cursor: pointer;
                            transition: color 0.2s;
                        }

                        .toggle-password:hover {
                            color: #fff;
                            background-color: #333;
                        }
                        </style>

                        <script>
                        const passwordInput = document.getElementById('password');
                        const togglePassword = document.getElementById('togglePassword');
                        const icon = togglePassword.querySelector('i');

                        togglePassword.addEventListener('click', () => {
                            const type = passwordInput.type === 'password' ? 'text' : 'password';
                            passwordInput.type = type;

                            icon.classList.toggle('bi-eye');
                            icon.classList.toggle('bi-eye-slash');
                        });
                        </script>

                        <button class="btn btn-primary btn-lg w-100">
                            Login
                        </button>
                    </form>

                </div>
            </div>

            <p class="text-center text-secondary small mt-3">
                Authorized administrators only
            </p>

        </div>
    </div>
</div>

</body>
</html>