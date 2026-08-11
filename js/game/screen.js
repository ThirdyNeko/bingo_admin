document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("screen-root");
  if (!root) return;

  const gameId = root.dataset.gameId;

  let currentCount = parseInt(root.dataset.playerCount, 10);
  let gameStarted = parseInt(root.dataset.started, 10);
  let currentClaimed = parseInt(root.dataset.claimedCount, 10);
  let totalWinners = parseInt(root.dataset.totalWinners, 10);

  if (currentClaimed >= totalWinners) {
    Swal.fire({
      icon: "success",
      title: "🎉 Game Finished!",
      text: "All winners have been claimed!",
      confirmButtonText: "View Winners",
      confirmButtonColor: "#28a745",
      allowOutsideClick: false,
    }).then(() => {
      window.location.href = "winners.php?game_id=" + gameId;
    });
  }

  function letterFor(n) {
    if (n >= 1 && n <= 15) return "B";
    if (n <= 30) return "I";
    if (n <= 45) return "N";
    if (n <= 60) return "G";
    return "O";
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  function buildPreviousNumbersHTML(drawnNumbers) {
    const previousNumbers = drawnNumbers.slice(0, -1);
    if (previousNumbers.length === 0) return "";

    const mostRecentPrev = previousNumbers[previousNumbers.length - 1];

    const grouped = { B: [], I: [], N: [], G: [], O: [] };
    previousNumbers.forEach((n) => grouped[letterFor(n)].push(n));
    Object.keys(grouped).forEach((k) => grouped[k].sort((a, b) => a - b));

    let html =
      '<p class="text-muted small mb-2 text-center">Previously Drawn</p>';
    html += '<div id="prev-numbers-track" class="d-flex flex-column gap-2">';

    ["B", "I", "N", "G", "O"].forEach((letter) => {
      const nums = grouped[letter];
      if (nums.length === 0) return;

      html += `<div class="prev-letter-row d-flex align-items-center gap-2 flex-wrap">`;
      html += `<div class="prev-letter-label ${letter}">${letter}</div>`;

      nums.forEach((n) => {
        const newestClass = n === mostRecentPrev ? " newest-ball" : "";
        html += `<div class="prev-ball ${letter}${newestClass}"><span class="prev-num">${n}</span></div>`;
      });

      html += `</div>`;
    });

    html += "</div>";
    return html;
  }

  /* ==============================
       RENDER DRAW RESULT (with discard-down transition)
    ================================ */
  function renderDrawResult(data) {
    const number = data.number;
    const letter = letterFor(number);

    const ballWrap = document.getElementById("current-ball-wrap");
    const existingBall = ballWrap.querySelector(".bingo-ball");

    function insertNewBall() {
      ballWrap.innerHTML = `
                <div class="bingo-ball ${letter}">
                    <div class="outer-letter">${letter}</div>
                    <div class="inner-number">${number}</div>
                </div>
                <p class="lead mt-3">Last number drawn</p>
            `;

      const prevSection = document.getElementById("prev-numbers-section");
      prevSection.innerHTML = buildPreviousNumbersHTML(data.drawnNumbers);

      currentClaimed = data.claimedCount;
      totalWinners = data.totalWinners;

      document.getElementById("winners-header").textContent =
        `Winners: ${data.claimedCount} / ${data.totalWinners}`;

      const winnersList = document.getElementById("winners-list");
      winnersList.innerHTML = data.winnerNames
        .map(
          (name, i) =>
            `<div class="fs-4 text-warning">#${i + 1} — ${escapeHtml(name)}</div>`,
        )
        .join("");

      if (data.finished) {
        Swal.fire({
          icon: "success",
          title: "🎉 Game Finished!",
          text: "All winners have been claimed!",
          confirmButtonText: "View Winners",
          confirmButtonColor: "#28a745",
          allowOutsideClick: false,
        }).then(() => {
          window.location.href = "winners.php?game_id=" + gameId;
        });
      }
    }

    if (existingBall) {
      // Play the discard animation on the outgoing ball, then swap it out.
      existingBall.classList.add("discard-ball");

      let handled = false;
      const finish = () => {
        if (handled) return;
        handled = true;
        insertNewBall();
      };

      existingBall.addEventListener("animationend", finish, { once: true });
      // Fallback in case animationend doesn't fire (e.g. tab was backgrounded)
      setTimeout(finish, 500);
    } else {
      insertNewBall();
    }
  }

  const drawBtn = document.getElementById("drawBtn");
  if (drawBtn) {
    drawBtn.addEventListener("click", function () {
      drawBtn.disabled = true;
      drawBtn.innerHTML = "Drawing...";

      fetch("functions/draw_number.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "game_id=" + gameId,
      })
        .then((res) => res.json())
        .then((data) => {
          drawBtn.disabled = false;
          drawBtn.innerHTML = "Draw Number";

          if (data.error) {
            Swal.fire("Notice", data.error, "info");
            return;
          }

          renderDrawResult(data);
        })
        .catch((err) => {
          console.error("Draw error:", err);
          drawBtn.disabled = false;
          drawBtn.innerHTML = "Draw Number";
          Swal.fire(
            "Error",
            "Something went wrong drawing the number.",
            "error",
          );
        });
    });
  }

  function checkScreenChanges() {
    fetch("functions/screen_status.php?game_id=" + gameId)
      .then((res) => res.json())
      .then((data) => {
        let newCount = parseInt(data.count);
        let newStarted = parseInt(data.started);
        let newClaimed = parseInt(data.claimed);

        if (!gameStarted && newCount !== currentCount) {
          location.reload();
        }

        if (newStarted !== gameStarted) {
          location.reload();
        }

        if (newClaimed !== currentClaimed) {
          currentClaimed = newClaimed;

          if (newClaimed >= totalWinners) {
            Swal.fire({
              icon: "success",
              title: "🎉 Game Finished!",
              text: "All winners have been claimed!",
              confirmButtonText: "View Winners",
              confirmButtonColor: "#28a745",
              allowOutsideClick: false,
            }).then(() => {
              window.location.href = "winners.php?game_id=" + gameId;
            });
          }
          // Don't force a reload here anymore — draws are handled via fetch.
          // Only reload for out-of-band claims triggered elsewhere (e.g. another admin tab).
        }
      })
      .catch((err) => console.error("Polling error:", err));
  }

  setInterval(checkScreenChanges, 3000);
});
