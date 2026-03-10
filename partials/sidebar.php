<div class="col-md-2 sidebar p-3">
    <h4 class="text-center">BINGO ADMIN</h4>
    <hr>
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])=='index.php'?'active-link':'' ?>">
        Dashboard
    </a>

    <a href="create_game.php" class="<?= basename($_SERVER['PHP_SELF'])=='create_game.php'?'active-link':'' ?>">
        Create Game
    </a>

    <a href="manage_games.php" 
        class="<?= in_array(basename($_SERVER['PHP_SELF']), ['manage_games.php','manage_game.php']) ? 'active-link' : '' ?>">
        Manage Games
    </a>

    <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF'])=='settings.php'?'active-link':'' ?>">
        Settings
    </a>

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="admin.php" class="<?= basename($_SERVER['PHP_SELF'])=='admin.php'?'active-link':'' ?>">
            Admin
        </a>
    <?php endif; ?>

    <!-- Change Password Sidebar Link (Modal Trigger) -->
    <a href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
        Change Password
    </a>

    <a href="auth/logout.php" class="text-danger">
        Logout
    </a>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
        <form id="changePasswordForm">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <div id="passwordAlert"></div> <!-- For success/error messages -->

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <span class="input-group-text toggle-password" data-target="current_password" style="cursor:pointer;">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <span class="input-group-text toggle-password" data-target="new_password" style="cursor:pointer;">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <span class="input-group-text toggle-password" data-target="confirm_password" style="cursor:pointer;">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
  </div>
</div>

<!-- JS -->
<script>
document.querySelectorAll('.toggle-password').forEach(span => {
    span.addEventListener('click', () => {
        const target = document.getElementById(span.dataset.target);
        const icon = span.querySelector('i');
        if (target.type === 'password') {
            target.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            target.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
});

// AJAX form submission
document.getElementById('changePasswordForm').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    fetch('auth/change_password.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        const alertDiv = document.getElementById('passwordAlert');
        alertDiv.innerHTML = `<div class="alert alert-${data.status}" role="alert">${data.message}</div>`;
        if(data.status === 'success') this.reset();

        // auto-hide alert after 5 seconds
        setTimeout(() => alertDiv.innerHTML = '', 5000);
    })
    .catch(err => console.error(err))
    .finally(() => submitBtn.disabled = false);
});
</script>