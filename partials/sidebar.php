<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('Bingo');
    session_start();
}

require_once 'auth/require_login.php';

$currentPage = basename($_SERVER['PHP_SELF']);

function sidebarActive($pages, $currentPage) {
    return in_array($currentPage, (array)$pages) ? 'active' : '';
}
?>

<link href="css/sidebar_design.css" rel="stylesheet">

<div class="col-md-2 bingo-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-grid-3x3-gap-fill"></i>
        </div>
        <div class="brand-text">
            <h4>BINGO</h4>
            <span>ADMIN PANEL</span>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <div class="sidebar-section-label">MENU</div>

    <nav class="sidebar-navigation">

        <a href="index.php" class="sidebar-link <?= sidebarActive('index.php', $currentPage) ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <a href="create_game.php" class="sidebar-link <?= sidebarActive('create_game.php', $currentPage) ?>">
            <i class="bi bi-plus-square"></i>
            <span>Create Game</span>
        </a>

        <a href="manage_games.php"
           class="sidebar-link <?= sidebarActive(['manage_games.php', 'manage_game.php'], $currentPage) ?>">
            <i class="bi bi-controller"></i>
            <span>Manage Games</span>
        </a>

        <a href="winners_list.php" class="sidebar-link <?= sidebarActive('winners_list.php', $currentPage) ?>">
            <i class="bi bi-trophy"></i>
            <span>Winners</span>
        </a>

        <a href="settings.php" class="sidebar-link <?= sidebarActive('settings.php', $currentPage) ?>">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php" class="sidebar-link <?= sidebarActive('admin.php', $currentPage) ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Admin</span>
            </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-divider"></div>

    <div class="sidebar-section-label">ACCOUNT</div>

    <nav class="sidebar-navigation">

        <a href="#" class="sidebar-link"
           data-bs-toggle="modal"
           data-bs-target="#changePasswordModal">
            <i class="bi bi-key"></i>
            <span>Change Password</span>
        </a>

        <a href="auth/logout.php" class="sidebar-link sidebar-logout">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-icon">
            <i class="bi bi-person-circle"></i>
        </div>
        <div class="sidebar-footer-text">
            <strong>Administrator</strong>
            <small><?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></small>
        </div>
    </div>
</div>


<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade password-modal" id="changePasswordModal"
     tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form id="changePasswordForm">

                <div class="modal-header">
                    <div class="password-modal-heading">
                        <div class="password-modal-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>

                        <div>
                            <h5 class="modal-title" id="changePasswordModalLabel">
                                Change Password
                            </h5>
                            <small>Update your account security</small>
                        </div>
                    </div>

                    <button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <div id="passwordAlert"></div>

                    <div class="password-field">
                        <label for="current_password">Current Password</label>

                        <div class="password-input-wrapper">
                            <input type="password" class="form-control"
                                   id="current_password"
                                   name="current_password"
                                   placeholder="Enter current password"
                                   autocomplete="current-password"
                                   required>

                            <button type="button" class="password-toggle toggle-password"
                                    data-target="current_password"
                                    aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-field">
                        <label for="new_password">New Password</label>

                        <div class="password-input-wrapper">
                            <input type="password" class="form-control"
                                   id="new_password"
                                   name="new_password"
                                   placeholder="Enter new password"
                                   autocomplete="new-password"
                                   required>

                            <button type="button" class="password-toggle toggle-password"
                                    data-target="new_password"
                                    aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-field">
                        <label for="confirm_password">Confirm New Password</label>

                        <div class="password-input-wrapper">
                            <input type="password" class="form-control"
                                   id="confirm_password"
                                   name="confirm_password"
                                   placeholder="Confirm new password"
                                   autocomplete="new-password"
                                   required>

                            <button type="button" class="password-toggle toggle-password"
                                    data-target="confirm_password"
                                    aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-hint">
                        <i class="bi bi-info-circle"></i>
                        Make sure your new password is secure and not easily guessed.
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn password-cancel"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn password-submit">
                        <i class="bi bi-check-circle me-1"></i>
                        Update Password
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="js/partials/sidebar.js"></script>