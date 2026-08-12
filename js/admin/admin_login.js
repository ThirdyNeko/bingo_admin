document.addEventListener("DOMContentLoaded", function () {
  const togglePassword = document.getElementById("togglePassword");
  const password = document.getElementById("password");

  if (togglePassword && password) {
    togglePassword.addEventListener("click", function () {
      const isPassword = password.getAttribute("type") === "password";

      password.setAttribute("type", isPassword ? "text" : "password");

      const icon = this.querySelector("i");

      if (icon) {
        icon.classList.toggle("bi-eye-slash");
        icon.classList.toggle("bi-eye");
      }
    });
  }
});
