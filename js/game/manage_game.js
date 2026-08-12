document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("manage-game-root");
  if (!root) return;

  const gameId = root.dataset.gameId;
  let currentCount = parseInt(root.dataset.playerCount, 10);

  const playersTable = $("#playersTable").DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    responsive: true,
    pageLength: 25,
    language: {
      emptyTable: "No players joined yet.",
    },
    ajax: {
      url: "functions/game_players_list.php",
      type: "POST",
      data: function (d) {
        d.game_id = gameId;
      },
    },
    columns: [
      { data: "row_num", orderable: false, searchable: false, width: "50px" },
      { data: "name" },
      { data: "mode", orderable: false, searchable: false, width: "120px" },
      {
        data: "card_count",
        orderable: false,
        searchable: false,
        width: "120px",
      },
    ],
  });

  function checkForNewPlayers() {
    fetch("functions/player_count.php?game_id=" + encodeURIComponent(gameId))
      .then((res) => res.text())
      .then((count) => {
        count = parseInt(count, 10);

        if (!Number.isFinite(count)) {
          console.error("player_count.php returned a non-numeric value");
          return;
        }

        if (count !== currentCount) {
          currentCount = count;
          document.getElementById("players-count").textContent = count;
          playersTable.ajax.reload(null, false); // reload data, keep current page
        }
      })
      .catch((err) => console.error("Player count check failed:", err));
  }

  // Check every 3 seconds
  setInterval(checkForNewPlayers, 3000);
});
