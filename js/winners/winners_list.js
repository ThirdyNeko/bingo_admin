// Winners list page: click a session row to load its winners into a modal.
// Loaded by winners_list.php (needs Bootstrap's JS bundle from partials/header.php).

document.addEventListener("DOMContentLoaded", function () {
  const winnersModalEl = document.getElementById("winnersModal");
  if (!winnersModalEl) return;

  const winnersModal = new bootstrap.Modal(winnersModalEl);
  const detailBody = document.querySelector("#winnersDetailTable tbody");
  const loading = document.getElementById("winnersLoading");
  const sessionsTableEl = document.getElementById("sessionsTable");

  // ----- Sessions table (same options/labels as the games table) -----
  // Rows are already ordered by the SQL query, so client-side ordering is off.
  if (window.jQuery && jQuery.fn.DataTable && sessionsTableEl) {
    jQuery(sessionsTableEl).DataTable({
      ordering: false,
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      language: {
        emptyTable: "No winners have been declared yet.",
        zeroRecords: "No matching sessions found.",
        search: "",
        searchPlaceholder: "Search sessions...",
        lengthMenu: "Show _MENU_ sessions",
        info: "Showing _START_ to _END_ of _TOTAL_ sessions",
        infoEmpty: "No sessions available",
        infoFiltered: "(filtered from _MAX_ total sessions)",
        paginate: {
          previous: "‹",
          next: "›",
        },
      },
    });
  }

  // ----- Modal table helpers -----
  function messageRow(text, extraClass) {
    detailBody.innerHTML = "";
    const tr = document.createElement("tr");
    const td = document.createElement("td");
    td.colSpan = 6;
    td.className = "mg-empty " + (extraClass || "");
    td.textContent = text;
    tr.appendChild(td);
    detailBody.appendChild(tr);
  }

  function renderTable(rounds) {
    detailBody.innerHTML = "";

    if (!rounds.length) {
      messageRow("No winners found for this session.");
      return;
    }

    rounds.forEach(function (round) {
      round.winners.forEach(function (w, index) {
        const tr = document.createElement("tr");
        if (index === 0) tr.className = "round-start";

        // Game code only on the round's first row, spanning all its winner rows
        if (index === 0) {
          const tdCode = document.createElement("td");
          tdCode.textContent = round.game_code || "";
          tdCode.rowSpan = round.winners.length;
          tr.appendChild(tdCode);
        }

        const tdRank = document.createElement("td");
        const rank = document.createElement("span");
        rank.className = "mg-rank" + (index === 0 ? " first" : "");
        rank.textContent = "#" + (index + 1);
        tdRank.appendChild(rank);
        tr.appendChild(tdRank);

        const tdWinner = document.createElement("td");
        tdWinner.className = "mg-winner-name";
        tdWinner.textContent = w.name || "";
        tr.appendChild(tdWinner);

        const tdPrize = document.createElement("td");
        tdPrize.textContent = (round.prize && round.prize.name) || "—";
        tr.appendChild(tdPrize);

        const tdDesc = document.createElement("td");
        tdDesc.textContent = (round.prize && round.prize.description) || "—";
        tr.appendChild(tdDesc);

        const tdPic = document.createElement("td");
        if (round.prize && round.prize.picture) {
          const img = document.createElement("img");
          img.src = "data:image/jpeg;base64," + round.prize.picture;
          img.className = "mg-prize-thumb";
          img.alt = "";
          tdPic.appendChild(img);
        } else {
          tdPic.textContent = "—";
        }
        tr.appendChild(tdPic);

        detailBody.appendChild(tr);
      });
    });
  }

  function openSession(sessionId) {
    document.getElementById("modalSessionId").textContent = sessionId;
    document.getElementById("screenViewLink").href =
      "session_winner.php?session_id=" + encodeURIComponent(sessionId);
    detailBody.innerHTML = "";
    loading.classList.remove("d-none");
    winnersModal.show();

    fetch(
      "functions/get_session_winners.php?session_id=" +
        encodeURIComponent(sessionId),
    )
      .then(function (res) {
        if (!res.ok) throw new Error("Request failed");
        return res.json();
      })
      .then(function (rounds) {
        loading.classList.add("d-none");
        renderTable(rounds);
      })
      .catch(function () {
        loading.classList.add("d-none");
        messageRow("Failed to load winners.", "text-danger");
      });
  }

  // Delegated click: DataTables removes/re-adds rows when paging or searching,
  // so listeners attached directly to each row would be lost.
  if (sessionsTableEl) {
    sessionsTableEl
      .querySelector("tbody")
      .addEventListener("click", function (e) {
        const row = e.target.closest("tr.session-row");
        if (!row) return;
        openSession(row.dataset.sessionId);
      });
  }
});
