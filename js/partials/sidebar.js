// =========================================================
// BINGO SIDEBAR
// Password toggles + AJAX password change
// =========================================================

document.addEventListener("DOMContentLoaded", function () {
  // ----- Password visibility toggles -----
  document.querySelectorAll(".toggle-password").forEach(function (button) {
    button.addEventListener("click", function () {
      const target = document.getElementById(button.dataset.target);
      const icon = button.querySelector("i");

      if (!target || !icon) return;

      const isPassword = target.type === "password";

      target.type = isPassword ? "text" : "password";

      icon.classList.toggle("bi-eye", isPassword);
      icon.classList.toggle("bi-eye-slash", !isPassword);
    });
  });

  // ----- Change Password AJAX -----
  const form = document.getElementById("changePasswordForm");

  if (!form) return;

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    const alertDiv = document.getElementById("passwordAlert");

    if (!submitBtn || !alertDiv) return;

    const originalButtonHTML = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2"
                  role="status" aria-hidden="true"></span>
            Updating...
        `;

    alertDiv.innerHTML = "";

    fetch("auth/change_password.php", {
      method: "POST",
      body: new FormData(form),
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("Server returned an error.");
        }

        return res.json();
      })

      .then(function (data) {
        alertDiv.innerHTML = "";

        const alert = document.createElement("div");

        alert.className =
          "alert alert-" +
          (data.status === "success" ? "success" : "danger") +
          " alert-dismissible fade show";

        alert.setAttribute("role", "alert");

        alert.textContent = data.message || "Something went wrong.";

        const closeButton = document.createElement("button");

        closeButton.type = "button";
        closeButton.className = "btn-close";
        closeButton.setAttribute("data-bs-dismiss", "alert");
        closeButton.setAttribute("aria-label", "Close");

        alert.appendChild(closeButton);
        alertDiv.appendChild(alert);

        if (data.status === "success") {
          form.reset();

          // Reset password visibility icons
          document
            .querySelectorAll(".toggle-password")
            .forEach(function (button) {
              const target = document.getElementById(button.dataset.target);
              const icon = button.querySelector("i");

              if (target) target.type = "password";

              if (icon) {
                icon.classList.remove("bi-eye");
                icon.classList.add("bi-eye-slash");
              }
            });
        }
      })

      .catch(function (err) {
        console.error("Change password error:", err);

        alertDiv.innerHTML = "";

        const alert = document.createElement("div");

        alert.className = "alert alert-danger";
        alert.setAttribute("role", "alert");
        alert.textContent = "Unable to process your request. Please try again.";

        alertDiv.appendChild(alert);
      })

      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalButtonHTML;
      });
  });
});
