document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("manage-game-root");
  if (!root) return;

  const gameId = root.dataset.gameId;
  const gameStarted = root.dataset.gameStarted === "1";
  let currentCount = parseInt(root.dataset.playerCount, 10) || 0;

  // ============================================================
  // CARD VALIDATION
  // ============================================================
  function hasNoCards(rowData) {
    if (gameStarted) {
      return !rowData.actual_cards || rowData.actual_cards <= 0;
    }

    return !rowData.card_count || rowData.card_count <= 0;
  }

  // ============================================================
  // PLAYER DATATABLE
  // ============================================================
  const playersTable = $("#playersTable").DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    responsive: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],

    language: {
      processing: "Loading players...",
      emptyTable: "No players joined yet.",
      zeroRecords: "No matching players found.",
      search: "",
      searchPlaceholder: "Search players...",
      lengthMenu: "Show _MENU_ players",
      info: "Showing _START_ to _END_ of _TOTAL_ players",
      infoEmpty: "No players available",
      infoFiltered: "(filtered from _MAX_ total players)",
      paginate: {
        previous: "‹",
        next: "›",
      },
    },

    ajax: {
      url: "functions/game_players_list.php",
      type: "POST",
      data: function (d) {
        d.game_id = gameId;
      },
      error: function (xhr, status, error) {
        console.error("=== GAME PLAYERS AJAX ERROR ===");
        console.error("HTTP Status:", xhr.status);
        console.error("Status:", status);
        console.error("Error:", error);
        console.error("Response:", xhr.responseText);
      },
    },

    columns: [
      {
        data: "row_num",
        orderable: false,
        searchable: false,
        width: "50px",
      },

      {
        data: "name",
      },

      {
        data: "department",
        orderable: false,
        searchable: false,
        render: function (data, type) {
          if (type !== "display") return data;
          return data ? data : '<span class="text-muted">—</span>';
        },
      },

      {
        data: "mode",
        orderable: false,
        searchable: false,
        width: "120px",
      },

      {
        data: "card_count",
        orderable: false,
        searchable: false,
        width: "140px",
        render: function (data, type, row) {
          if (type !== "display") return data;

          const label = gameStarted
            ? (row.actual_cards ?? 0) + " generated"
            : data + " requested";

          if (hasNoCards(row)) {
            return (
              '<span class="text-danger fw-bold">' +
              label +
              ' <i class="bi bi-exclamation-triangle-fill" title="No cards"></i>' +
              "</span>"
            );
          }

          return label;
        },
      },

      {
        data: "wins",
        orderable: false,
        searchable: false,
        width: "180px",
        render: function (data, type, row) {
          if (type !== "display") return data;

          const wins = parseInt(data, 10) || 0;
          const restricted = wins > 0;

          // Restriction cannot be changed once the game starts.
          if (gameStarted) {
            return restricted
              ? '<span class="badge bg-warning text-dark">Restricted</span>'
              : '<span class="text-muted">—</span>';
          }

          const action = restricted ? "unrestrict" : "restrict";
          const btnClass = restricted
            ? "btn-outline-success"
            : "btn-outline-warning";

          const label = restricted ? "Unrestrict" : "Restrict";

          const badge = restricted
            ? '<span class="badge bg-warning text-dark me-2">Restricted</span>'
            : "";

          return (
            badge +
            '<form method="POST" action="manage_game.php?game_id=' +
            encodeURIComponent(gameId) +
            '" class="d-inline">' +
            '<input type="hidden" name="user_id" value="' +
            row.id +
            '">' +
            '<button type="submit" name="set_restriction" value="' +
            action +
            '" class="btn btn-sm ' +
            btnClass +
            '">' +
            label +
            "</button>" +
            "</form>"
          );
        },
      },
    ],

    createdRow: function (row, data) {
      if (hasNoCards(data)) {
        $(row).addClass("table-danger");

        $(row).attr(
          "title",
          gameStarted
            ? "This player has no cards in the database for this game."
            : "This player has 0 requested cards and will not receive one when the game starts.",
        );
      }
    },
  });

  // ============================================================
  // PLAYER COUNT BADGES
  // ============================================================
  function updatePlayerCountDisplay(count) {
    const countElement = document.getElementById("players-count");
    const badgeElement = document.getElementById("players-count-badge");

    if (countElement) {
      countElement.textContent = count;
    }

    if (badgeElement) {
      badgeElement.textContent = count;
    }
  }

  // ============================================================
  // LIVE PLAYER COUNT
  // ============================================================
  function checkForNewPlayers() {
    fetch("functions/player_count.php?game_id=" + encodeURIComponent(gameId), {
      credentials: "same-origin",
    })
      .then((res) => {
        if (!res.ok) {
          throw new Error("HTTP " + res.status);
        }

        return res.text();
      })
      .then((count) => {
        const newCount = parseInt(count.trim(), 10);

        if (!Number.isFinite(newCount)) {
          console.error(
            "player_count.php returned a non-numeric value:",
            count,
          );
          return;
        }

        if (newCount !== currentCount) {
          console.log("Player count changed:", currentCount, "→", newCount);

          currentCount = newCount;
          updatePlayerCountDisplay(newCount);

          playersTable.ajax.reload(null, false);
        }
      })
      .catch((err) => {
        console.error("Player count check failed:", err);
      });
  }

  // Check every 3 seconds
  setInterval(checkForNewPlayers, 3000);

  // ============================================================
  // AUTO-START COUNTDOWN
  // ============================================================
  const startMode = root.dataset.startMode;
  const scheduledStartRaw = root.dataset.scheduledStart;
  const countdownEl = document.getElementById("countdown");

  if (!gameStarted && startMode === "timer" && scheduledStartRaw) {
    const scheduledStart = new Date(scheduledStartRaw).getTime();

    if (!Number.isNaN(scheduledStart)) {
      const tick = setInterval(() => {
        const diff = scheduledStart - Date.now();

        if (diff <= 0) {
          clearInterval(tick);

          if (countdownEl) {
            countdownEl.textContent = "Starting...";
          }

          fetch("functions/start_game.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "game_id=" + encodeURIComponent(gameId),
          })
            .then(() => location.reload())
            .catch((err) => {
              console.error("Auto-start failed:", err);
            });

          return;
        }

        const m = Math.floor(diff / 60000);
        const s = Math.floor((diff % 60000) / 1000);

        if (countdownEl) {
          countdownEl.textContent = `${m}m ${s}s remaining`;
        }
      }, 1000);
    }
  }

  // ============================================================
  // DRAW MODE TOGGLE
  // ============================================================
  const drawModeAuto = document.getElementById("drawModeAuto");
  const drawModeManual = document.getElementById("drawModeManual");
  const drawIntervalWrap = document.getElementById("drawIntervalWrap");

  function toggleDrawIntervalWrap() {
    if (!drawIntervalWrap || !drawModeAuto) return;

    drawIntervalWrap.style.display = drawModeAuto.checked ? "" : "none";
  }

  if (drawModeAuto) {
    drawModeAuto.addEventListener("change", toggleDrawIntervalWrap);
  }

  if (drawModeManual) {
    drawModeManual.addEventListener("change", toggleDrawIntervalWrap);
  }

  // Initialize visibility
  toggleDrawIntervalWrap();
});
