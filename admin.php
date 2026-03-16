<?php
require_once 'config/db.php';
require_once 'partials/header.php';
require_once 'partials/sidebar.php';

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roles'])) {

    foreach ($_POST['roles'] as $userId => $role) {

        if ($role === 'gamemaster') {

            $defaultPassword = password_hash("Password", PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "UPDATE users SET role = ?, password = ? WHERE id_number = ?"
            );
            $stmt->execute([$role, $defaultPassword, $userId]);

        } else {

            $stmt = $pdo->prepare(
                "UPDATE users SET role = ?, password = NULL WHERE id_number = ?"
            );
            $stmt->execute([$role, $userId]);
        }
    }

    $success = "Roles updated successfully.";
}

// Fetch users
$stmt = $pdo->prepare(
    "SELECT id_number, department, name, role FROM users WHERE role != ? ORDER BY id_number ASC"
);
$stmt->execute(['admin']);
$users = $stmt->fetchAll();
?>

<div class="col-md-10 p-4">

<h2 class="mb-4">User Role Management</h2>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">

<div class="card shadow-sm">

<div class="card-header d-flex justify-content-between align-items-center">
<strong>Users</strong>

<button class="btn btn-success btn-sm">
<i class="bi bi-save"></i> Update All
</button>
</div>

<div class="card-body">

<table class="table table-striped">

<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Department</th>
<th>Role</th>
</tr>
</thead>

<tbody>

<?php foreach ($users as $u): ?>

<tr>

<td><?= htmlspecialchars($u['id_number']) ?></td>
<td><?= htmlspecialchars($u['name']) ?></td>
<td><?= htmlspecialchars($u['department']) ?></td>

<td>

<select name="roles[<?= $u['id_number'] ?>]" class="form-select form-select-sm">

<option value="player" <?= $u['role']=='player'?'selected':'' ?>>Player</option>
<option value="priority" <?= $u['role']=='priority'?'selected':'' ?>>Priority</option>
<option value="gamemaster" <?= $u['role']=='gamemaster'?'selected':'' ?>>Game Master</option>

</select>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
</div>

</form>

</div>