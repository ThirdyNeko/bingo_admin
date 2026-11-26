// Winners list page: click a session row to load its winners into a modal.
// Loaded by winners_list.php (needs Bootstrap's JS bundle from partials/header.php).

document.addEventListener("DOMContentLoaded", function () {
  const winnersModalEl = document.getElementById("winnersModal");
  if (!winnersModalEl) return;

  const winnersModal = new bootstrap.Modal(winnersModalEl);
  const detailBody = document.querySelector("#winnersDetailTable tbody");
  const loading = document.getElementById("winnersLoading");

  function renderTable(rounds) {
    detailBody.innerHTML = "";

    if (!rounds.length) {
      detailBody.innerHTML =
        '<tr><td colspan="6" class="text-center text-muted">No winners found for this session.</td></tr>';
      return;
    }

    rounds.forEach(function (round) {
      round.winners.forEach(function (w, index) {
        const tr = document.createElement("tr");

        // Only render game-code and screen-link cells on the round's first row,
        // spanning all of that round's winner rows
        if (index === 0) {
          const tdCode = document.createElement("td");
          tdCode.textContent = round.game_code || "";
          tdCode.rowSpan = round.winners.length;
          tr.appendChild(tdCode);
        }

        const tdRank = document.createElement("td");
        tdRank.textContent = "#" + (index + 1);
        tr.appendChild(tdRank);

        const tdWinner = document.createElement("td");
        tdWinner.textContent = w.name || "";
        tr.appendChild(tdWinner);

        const tdPrize = document.createElement("td");
        tdPrize.textContent = round.prize ? round.prize.name || "" : "";
        tr.appendChild(tdPrize);

        const tdDesc = document.createElement("td");
        tdDesc.textContent = round.prize ? round.prize.description || "" : "";
        tr.appendChild(tdDesc);

        const tdPic = document.createElement("td");
        if (round.prize && round.prize.picture) {
          const img = document.createElement("img");
          img.src = "data:image/jpeg;base64," + round.prize.picture;
          img.style.maxWidth = "60px";
          img.style.maxHeight = "60px";
          img.style.objectFit = "cover";
          img.style.borderRadius = "4px";
          tdPic.appendChild(img);
        } else {
          tdPic.textContent = "—";
        }
        tr.appendChild(tdPic);

        detailBody.appendChild(tr);
      });
    });
  }

  document
    .querySelectorAll("#sessionsTable tbody tr.session-row")
    .forEach(function (row) {
      row.addEventListener("click", function () {
        const sessionId = row.dataset.sessionId;
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
            detailBody.innerHTML =
              '<tr><td colspan="6" class="text-center text-danger">Failed to load winners.</td></tr>';
          });
      });
    });
});
