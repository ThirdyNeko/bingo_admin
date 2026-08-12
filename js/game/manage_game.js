document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("manage-game-root");
  if (!root) return;

  const gameId = root.dataset.gameId;
  const gameStarted = root.dataset.gameStarted === "1";
  let currentCount = parseInt(root.dataset.playerCount, 10);

  // Which field determines "no cards" depends on game state:
  // - Before start: card_count is just the player's requested setting,
  //   nothing has been generated yet, so flag card_count === 0.
  // - After start: cards actually exist (or don't) in user_cards,
  //   so flag actual_cards === 0 instead.
  function hasNoCards(rowData) {
    if (gameStarted) {
      return !rowData.actual_cards || rowData.actual_cards <= 0;
    }
    return !rowData.card_count || rowData.card_count <= 0;
  }

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
    ],
    // Highlight the whole row for players with no cards, so it's obvious
    // at a glance before Start Game (missing setting) or after (generation gap).
    createdRow: function (row, data, dataIndex) {
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
