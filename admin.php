<?php
require_once 'config/db.php';
require_once 'partials/header.php';
require_once 'partials/sidebar.php';

// Update role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $userId = $_POST['user_id'] ?? null;
    $role = $_POST['role'] ?? null;

    if ($userId && $role) {

        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id_number = ?");
        $stmt->execute([$role, $userId]);

        $success = "Role updated successfully.";
    }
}

// Get users
$users = $pdo->query("SELECT id_number, name, role FROM users ORDER BY name ASC")->fetchAll();
?>

<div class="col-md-10 p-4">

<h2 class="mb-4">User Role Management</h2>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="card shadow-sm">
<div class="card-body">

<table class="table table-striped">
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Role</th>
<th>Change Role</th>
</tr>
</thead>

<tbody>

<?php foreach ($users as $u): ?>

<tr>
<td><?= htmlspecialchars($u['id_number']) ?></td>
<td><?= htmlspecialchars($u['name']) ?></td>

<td>
<span class="badge bg-primary">
<?= htmlspecialchars($u['role']) ?>
</span>
</td>

<td>
<form method="POST" class="d-flex gap-2">

<input type="hidden" name="user_id" value="<?= $u['id_number'] ?>">

<select name="role" class="form-select form-select-sm">

<option value="player" <?= $u['role']=='player'?'selected':'' ?>>Player</option>
<option value="gamemaster" <?= $u['role']=='gamemaster'?'selected':'' ?>>Game Master</option>
<option value="admin" <?= $u['role']=='admin'?'selected':'' ?>>Admin</option>

</select>

<button class="btn btn-sm btn-success">
Update
</button>

</form>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>

</div>