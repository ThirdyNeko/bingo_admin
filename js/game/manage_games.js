$(document).ready(function () {
  const table = $("#gamesTable").DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    responsive: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    ajax: {
      url: "functions/fetch_games.php",
      type: "POST",
    },
    columns: [
      { data: "game_code" },
      { data: "winners" },
      { data: "winners_declared" },
      {
        data: "action",
        orderable: false,
        searchable: false,
      },
    ],
    language: {
      processing: "Loading games...",
      emptyTable: "No games have been created yet.",
      zeroRecords: "No matching games found.",
      search: "",
      searchPlaceholder: "Search game records...",
      lengthMenu: "Show _MENU_ games",
      info: "Showing _START_ to _END_ of _TOTAL_ games",
      infoEmpty: "No games available",
      infoFiltered: "(filtered from _MAX_ total games)",
      paginate: {
        previous: "‹",
        next: "›",
      },
    },
    drawCallback: function () {
      // Ensure backend-generated action buttons remain visually consistent.
      $("#gamesTable tbody .btn").addClass("btn-sm");
    },
  });
});
