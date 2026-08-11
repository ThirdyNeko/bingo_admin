$(document).ready(function () {
  $("#usersTable").DataTable({
    processing: true,

    serverSide: true,

    responsive: true,

    pageLength: 25,

    ordering: true,

    ajax: {
      url: "functions/fetch_player_settings.php",
      type: "POST",
    },

    columns: [
      {
        data: 0,
      },
      {
        data: 1,
        orderable: true,
        searchable: false,
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
   * AUTO / MANUAL
   */
  $("#usersTable").on("change", ".player-auto-mode", function () {
    const checkbox = $(this);

    const name = checkbox.data("user-name");

    const autoMode = checkbox.is(":checked") ? 1 : 0;

    checkbox.prop("disabled", true);

    $.ajax({
      url: "functions/update_player_settings.php",

      type: "POST",

      dataType: "json",

      data: {
        name: name,
        auto_mode: autoMode,
      },

      success: function (response) {
        if (response.success) {
          showSettingsAlert(response.message, "success");
        } else {
          checkbox.prop("checked", !checkbox.is(":checked"));

          showSettingsAlert(
            response.message || "Unable to update settings.",
            "danger",
          );
        }
      },

      error: function (xhr) {
        checkbox.prop("checked", !checkbox.is(":checked"));

        let message = "Unable to update settings.";

        if (xhr.responseJSON?.message) {
          message = xhr.responseJSON.message;
        }

        showSettingsAlert(message, "danger");
      },

      complete: function () {
        checkbox.prop("disabled", false);
      },
    });
  });

  /*
   * CARD COUNT
   */
  $("#usersTable").on("change", ".player-card-count", function () {
    const input = $(this);

    const name = input.data("user-name");

    let cardCount = parseInt(input.val(), 10);

    if (isNaN(cardCount) || cardCount < 1) {
      cardCount = 1;
      input.val(1);
    }

    input.prop("disabled", true);

    $.ajax({
      url: "functions/update_player_settings.php",

      type: "POST",

      dataType: "json",

      data: {
        name: name,
        card_count: cardCount,
      },

      success: function (response) {
        if (response.success) {
          showSettingsAlert(response.message, "success");
        } else {
          showSettingsAlert(
            response.message || "Unable to update settings.",
            "danger",
          );
        }
      },

      error: function (xhr) {
        let message = "Unable to update settings.";

        if (xhr.responseJSON?.message) {
          message = xhr.responseJSON.message;
        }

        showSettingsAlert(message, "danger");
      },

      complete: function () {
        input.prop("disabled", false);
      },
    });
  });

  /*
   * Alert
   */
  function showSettingsAlert(message, type) {
    $("#settingsAlert").html(`
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
   * Escape HTML
   */
  function escapeHtml(value) {
    return $("<div>").text(value).html();
  }
});
