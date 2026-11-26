// Winners pages (winners.php and session_winner.php): congratulations popup.
// The title comes from <body data-complete-title="..."> so both pages share this file.
// Needs SweetAlert2 loaded first.

document.addEventListener("DOMContentLoaded", function () {
  Swal.fire({
    icon: "success",
    title: document.body.dataset.completeTitle || "Complete!",
    text: "Congratulations to all winners!",
    timer: 2500,
    showConfirmButton: false,
  });
});
