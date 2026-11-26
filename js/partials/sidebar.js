// Sidebar: show/hide password toggles + Change Password modal (AJAX).
// Loaded by partials/sidebar.php.

document.addEventListener("DOMContentLoaded", function () {
  // ----- Show / hide password -----
  document.querySelectorAll(".toggle-password").forEach(function (span) {
    span.addEventListener("click", function () {
      const target = document.getElementById(span.dataset.target);
      const icon = span.querySelector("i");
      if (!target || !icon) return;

      if (target.type === "password") {
        target.type = "text";
        icon.classList.replace("bi-eye-slash", "bi-eye");
      } else {
        target.type = "password";
        icon.classList.replace("bi-eye", "bi-eye-slash");
      }
    });
  });

  // ----- AJAX form submission -----
  const changePasswordForm = document.getElementById("changePasswordForm");
  if (!changePasswordForm) return;

  changePasswordForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(changePasswordForm);
    const submitBtn = changePasswordForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    fetch("auth/change_password.php", { method: "POST", body: formData })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        const alertDiv = document.getElementById("passwordAlert");
        alertDiv.innerHTML =
          '<div class="alert alert-' +
          data.status +
          '" role="alert">' +
          data.message +
          "</div>";

        if (data.status === "success") changePasswordForm.reset();

        // auto-hide alert after 5 seconds
        setTimeout(function () {
          alertDiv.innerHTML = "";
        }, 5000);
      })
      .catch(function (err) {
        console.error(err);
      })
      .finally(function () {
        submitBtn.disabled = false;
      });
  });
});
