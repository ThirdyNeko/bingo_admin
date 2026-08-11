$(document).ready(function () {
  const table = $("#usersTable").DataTable({
    processing: true,

    serverSide: true,

    responsive: true,

    pageLength: 25,

    ordering: true,

    ajax: {
      url: "functions/fetch_users.php",
      type: "POST",
    },

    columns: [
      {
        data: 0,
      },
      {
        data: 1,
      },
      {
        data: 2,
        orderable: true,
        searchable: false,
      },
    ],

    order: [[0, "asc"]],
  });

  /*
   * Update role when changed
   */
  $("#usersTable").on("change", ".user-role", function () {
    const select = $(this);

    const userId = select.data("user-id");

    const role = select.val();

    const originalRole = select.data("original-role");

    select.prop("disabled", true);

    $.ajax({
      url: "functions/update_user_role.php",

      type: "POST",

      dataType: "json",

      data: {
        name: select.data("name"),
        department: select.data("department"),
        role: role,
      },

      success: function (response) {
        if (response.success) {
          showRoleAlert(response.message, "success");

          select.data("original-role", role);
        } else {
          showRoleAlert(response.message || "Unable to update role.", "danger");

          if (originalRole !== undefined) {
            select.val(originalRole);
          }
        }
      },

      error: function (xhr) {
        let message = "Unable to update role.";

        if (xhr.responseJSON?.message) {
          message = xhr.responseJSON.message;
        }

        showRoleAlert(message, "danger");

        if (originalRole !== undefined) {
          select.val(originalRole);
        }
      },

      complete: function () {
        select.prop("disabled", false);
      },
    });
  });

  /*
   * Alert
   */
  function showRoleAlert(message, type) {
    $("#roleAlert").html(`
            <div class="alert alert-${type} alert-dismissible fade show">
                ${escapeHtml(message)}

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>
        `);
  }

  /*
   * Escape alert text
   */
  function escapeHtml(value) {
    return $("<div>").text(value).html();
  }
});
