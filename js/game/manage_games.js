$(document).ready(function () {
  $("#gamesTable").DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    responsive: true,
    pageLength: 25,
    ajax: {
      url: "functions/fetch_games.php",
      type: "POST",
    },
    columns: [
      { data: "game_code" },
      { data: "winners" },
      { data: "winners_declared" },
      { data: "action", orderable: false, searchable: false },
    ],
  });
});
