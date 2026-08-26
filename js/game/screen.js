document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("screen-root");
  if (!root) return;

  // ============================================================
  // DRAW REVEAL ANIMATION SETTINGS
  // durationMs comes from the game's saved setting (edited on the
  // Manage Game page, 1–5 seconds). Clamped again here as a safety
  // net in case the stored value is ever missing or out of range.
  // ============================================================
  const configuredDuration = parseInt(root.dataset.revealDuration, 10);
  const DRAW_REVEAL = {
    durationMs: Number.isFinite(configuredDuration)
      ? Math.min(5000, Math.max(1000, configuredDuration))
      : 1200,
    intervalMs: 65, // how often the displayed number changes during the cycle
  };

  const gameId = root.dataset.gameId;

  let currentCount = parseInt(root.dataset.playerCount, 10);
  let gameStarted = parseInt(root.dataset.started, 10);
  let currentClaimed = parseInt(root.dataset.claimedCount, 10);
  let totalWinners = parseInt(root.dataset.totalWinners, 10);

  // ============================================================
  // CARD CHANGE WINDOW
  // Game may be "started" but still inside the pre-draw grace period
  // where players can swap cards — no draw (manual or auto) is allowed
  // until this closes. screen.php doesn't even render #drawBtn during
  // this phase, but the auto-draw kickoff below still needs its own
  // guard since it doesn't depend on the button existing.
  // ============================================================
  let inCardChangeWindow = parseInt(root.dataset.inCardChangeWindow, 10) === 1;
  const cardChangeDeadlineRaw = root.dataset.cardChangeDeadline;

  if (inCardChangeWindow && cardChangeDeadlineRaw) {
    const cardChangeDeadline = new Date(cardChangeDeadlineRaw).getTime();
    const countdownEl = document.getElementById("card-change-countdown");

    const cardChangeTick = setInterval(() => {
      const diff = cardChangeDeadline - Date.now();

      if (diff <= 0) {
        clearInterval(cardChangeTick);
        if (countdownEl) countdownEl.textContent = "starting…";
        // The deadline has already been persisted server-side by
        // start_game.php — just reload so screen.php re-renders past
        // the card-change branch and this script's normal auto-draw
        // kickoff takes over.
        location.reload();
        return;
      }

      const m = Math.floor(diff / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      if (countdownEl) countdownEl.textContent = `${m}m ${s}s`;
    }, 1000);
  }

  // ============================================================
  // DRAW MODE (manual / auto)
  // Tracked here so the polling loop below can detect if the admin
  // changes these on the Manage Game page while this screen is open.
  // ============================================================
  let drawMode = root.dataset.drawMode === "manual" ? "manual" : "auto";
  let drawIntervalSeconds = parseInt(root.dataset.drawIntervalSeconds, 10);
  if (!Number.isFinite(drawIntervalSeconds) || drawIntervalSeconds < 1) {
    drawIntervalSeconds = 5;
  }
  let currentRevealDurationMs = DRAW_REVEAL.durationMs;

  let isDrawing = false;
  let autoDrawTimer = null;

  // ============================================================
  // LOBBY AUTO-START COUNTDOWN
  // Only relevant pre-start with start_mode === 'timer'. This is a
  // fallback trigger — functions/start_game.php already guards against
  // double-processing, so it's safe for this screen to also fire it
  // in case no admin tab is open when the timer hits zero.
  // ============================================================
  const startMode = root.dataset.startMode;
  const scheduledStartRaw = root.dataset.scheduledStart;

  if (!gameStarted && startMode === "timer" && scheduledStartRaw) {
    const scheduledStart = new Date(scheduledStartRaw).getTime();
    const countdownEl = document.getElementById("lobby-countdown");

    const countdownTick = setInterval(() => {
      const diff = scheduledStart - Date.now();

      if (diff <= 0) {
        clearInterval(countdownTick);
        if (countdownEl) countdownEl.textContent = "starting…";

        fetch("functions/start_game.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "game_id=" + gameId,
        }).then(() => location.reload());
        return;
      }

      const m = Math.floor(diff / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      if (countdownEl) countdownEl.textContent = `${m}m ${s}s`;
    }, 1000);
  }

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
       FLY-TO-POSITION TRANSITION
       Takes the ball currently shown as "Last number drawn"
       and animates it, in place, into the exact spot it will
       occupy inside the Previously Drawn row — fading out as
       it travels while the resting ball fades in on arrival.
    ================================ */
  function animateBallToHistory(ballEl, drawnNumbers, done) {
    const prevSection = document.getElementById("prev-numbers-section");

    // Snapshot everything we need from the outgoing ball before touching the DOM.
    const sourceRect = ballEl.getBoundingClientRect();
    const computed = window.getComputedStyle(ballEl);
    const backgroundImage = computed.backgroundImage;
    const backgroundColor = computed.backgroundColor;
    const textColor = computed.color;
    const fontSize = parseFloat(computed.fontSize) || 32;
    const numberText = ballEl.querySelector(".inner-number")
      ? ballEl.querySelector(".inner-number").textContent.trim()
      : "";

    // Hide the big ball immediately; the flying clone takes over visually.
    ballEl.style.visibility = "hidden";

    // Rebuild the previous-numbers row now, so we can measure exactly where
    // this number will land. Keep the landing spot hidden until the clone arrives.
    prevSection.innerHTML = buildPreviousNumbersHTML(drawnNumbers);
    const destBall = prevSection.querySelector(".prev-ball.newest-ball");

    if (!destBall) {
      // Nothing to animate into (shouldn't normally happen) — bail out cleanly.
      done();
      return;
    }

    destBall.style.visibility = "hidden";
    const destRect = destBall.getBoundingClientRect();

    const clone = document.createElement("div");
    clone.className = "ball-transit";
    clone.style.left = sourceRect.left + "px";
    clone.style.top = sourceRect.top + "px";
    clone.style.width = sourceRect.width + "px";
    clone.style.height = sourceRect.height + "px";
    clone.style.fontSize = fontSize + "px";
    clone.style.backgroundImage = backgroundImage;
    clone.style.backgroundColor = backgroundColor;
    clone.style.color = textColor;
    clone.style.opacity = "1";
    clone.textContent = numberText;
    document.body.appendChild(clone);

    const dx =
      destRect.left +
      destRect.width / 2 -
      (sourceRect.left + sourceRect.width / 2);
    const dy =
      destRect.top +
      destRect.height / 2 -
      (sourceRect.top + sourceRect.height / 2);
    const targetFontSize = fontSize * (destRect.width / sourceRect.width);

    // Two rAFs so the browser paints the clone at its start position
    // before we kick off the transition to the end position.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        clone.style.transform = `translate(${dx}px, ${dy}px)`;
        clone.style.width = destRect.width + "px";
        clone.style.height = destRect.height + "px";
        clone.style.fontSize = targetFontSize + "px";
        clone.style.opacity = "0";
      });
    });

    let finished = false;
    const finish = () => {
      if (finished) return;
      finished = true;
      clone.remove();
      destBall.style.visibility = "";
      done();
    };

    clone.addEventListener(
      "transitionend",
      (e) => {
        if (e.propertyName === "transform") finish();
      },
      { once: true },
    );
    // Fallback in case transitionend doesn't fire (e.g. tab was backgrounded).
    setTimeout(finish, 650);
  }

  /* ==============================
       NUMBER CYCLE REVEAL
       Rapidly flickers through random ball numbers in the
       current-ball slot, then settles on the real drawn number.
       Timing is controlled entirely by DRAW_REVEAL above.
    ================================ */
  function cycleThroughNumbers(ballWrap, finalNumber, finalLetter, done) {
    ballWrap.innerHTML = `
                <div class="bingo-ball cycling" id="cyclingBall">
                    <div class="outer-letter" id="cyclingLetter">B</div>
                    <div class="inner-number" id="cyclingNumber">1</div>
                </div>
                <p class="lead mt-3">Drawing...</p>
            `;

    const ball = document.getElementById("cyclingBall");
    const letterEl = document.getElementById("cyclingLetter");
    const numberEl = document.getElementById("cyclingNumber");
    const startTime = performance.now();

    function tick() {
      const elapsed = performance.now() - startTime;

      if (elapsed >= DRAW_REVEAL.durationMs) {
        ball.className = `bingo-ball ${finalLetter}`;
        letterEl.textContent = finalLetter;
        numberEl.textContent = finalNumber;
        done();
        return;
      }

      const randNumber = Math.floor(Math.random() * 75) + 1;
      const randLetter = letterFor(randNumber);
      ball.className = `bingo-ball ${randLetter} cycling`;
      letterEl.textContent = randLetter;
      numberEl.textContent = randNumber;

      setTimeout(tick, DRAW_REVEAL.intervalMs);
    }

    tick();
  }

  function renderDrawResult(data, onFullyDone) {
    const number = data.number;
    const letter = letterFor(number);

    const ballWrap = document.getElementById("current-ball-wrap");
    const existingBall = ballWrap.querySelector(".bingo-ball");

    function showNewCurrentBall() {
      ballWrap.innerHTML = `
                <div class="bingo-ball ${letter}">
                    <div class="outer-letter">${letter}</div>
                    <div class="inner-number">${number}</div>
                </div>
                <p class="lead mt-3">Last number drawn</p>
            `;

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

      // Animation is fully done — safe to let the next draw happen now.
      if (drawBtn) {
        drawBtn.disabled = false;
        drawBtn.innerHTML = "Draw Number";
      }

      if (typeof onFullyDone === "function") onFullyDone();

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

    function startCycleThenReveal() {
      cycleThroughNumbers(ballWrap, number, letter, showNewCurrentBall);
    }

    if (existingBall) {
      // Fly the outgoing ball into its slot in the Previously Drawn row,
      // then spin through numbers before revealing the freshly drawn one.
      animateBallToHistory(
        existingBall,
        data.drawnNumbers,
        startCycleThenReveal,
      );
    } else {
      // First draw of the game — nothing to animate into history yet.
      document.getElementById("prev-numbers-section").innerHTML =
        buildPreviousNumbersHTML(data.drawnNumbers);
      startCycleThenReveal();
    }
  }

  const drawBtn = document.getElementById("drawBtn");

  // ============================================================
  // SHARED DRAW TRIGGER
  // Used by the manual button AND the auto-draw timer, so both
  // paths get the same in-flight guard, error handling, and
  // animation sequencing.
  // ============================================================
  function triggerDraw() {
    if (isDrawing) return;
    if (inCardChangeWindow) return; // belt-and-suspenders; scheduleAutoDraw already guards this
    if (currentClaimed >= totalWinners) return;

    isDrawing = true;

    if (drawBtn) {
      drawBtn.disabled = true;
      drawBtn.innerHTML = "Drawing...";
    }

    fetch("functions/draw_number.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "game_id=" + gameId,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.error) {
          isDrawing = false;
          if (drawBtn) {
            drawBtn.disabled = false;
            drawBtn.innerHTML = "Draw Number";
          }
          if (drawMode === "manual") {
            Swal.fire("Notice", data.error, "info");
          } else {
            // Auto mode: no numbers left, still in card-change window,
            // or some other stop condition. Don't spam a modal every
            // interval — just stop rescheduling.
            console.warn("Auto-draw stopped:", data.error);
          }
          return;
        }

        renderDrawResult(data, function () {
          isDrawing = false;
          scheduleAutoDraw();
        });
      })
      .catch((err) => {
        console.error("Draw error:", err);
        isDrawing = false;
        if (drawBtn) {
          drawBtn.disabled = false;
          drawBtn.innerHTML = "Draw Number";
        }
        if (drawMode === "manual") {
          Swal.fire(
            "Error",
            "Something went wrong drawing the number.",
            "error",
          );
        } else {
          // Retry on the normal interval rather than hammering the endpoint.
          scheduleAutoDraw();
        }
      });
  }

  function scheduleAutoDraw() {
    clearTimeout(autoDrawTimer);
    if (drawMode !== "auto") return;
    if (!gameStarted) return;
    if (inCardChangeWindow) return; // don't start drawing while cards can still change
    if (currentClaimed >= totalWinners) return;

    autoDrawTimer = setTimeout(triggerDraw, drawIntervalSeconds * 1000);
  }

  if (drawBtn) {
    drawBtn.addEventListener("click", triggerDraw);
  }

  // Kick off the first auto-draw if the game is already running in auto
  // mode and isn't still waiting out its card-change window.
  if (gameStarted && drawMode === "auto" && !inCardChangeWindow) {
    scheduleAutoDraw();
  }

  function checkScreenChanges() {
    fetch("functions/screen_status.php?game_id=" + gameId)
      .then((res) => res.json())
      .then((data) => {
        let newCount = parseInt(data.count);
        let newStarted = parseInt(data.started);
        let newClaimed = parseInt(data.claimed);
        let newDrawMode = data.draw_mode === "manual" ? "manual" : "auto";
        let newDrawIntervalSeconds = parseInt(data.draw_interval_seconds);
        let newRevealDurationMs = parseInt(data.reveal_duration_ms);

        if (!gameStarted && newCount !== currentCount) {
          location.reload();
          return;
        }

        if (newStarted !== gameStarted) {
          location.reload();
          return;
        }

        // Settings changed on the Manage Game page — reload to pick them up,
        // but not while a draw animation is actively mid-flight.
        const settingsChanged =
          newDrawMode !== drawMode ||
          newDrawIntervalSeconds !== drawIntervalSeconds ||
          newRevealDurationMs !== currentRevealDurationMs;

        if (settingsChanged && !isDrawing) {
          location.reload();
          return;
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
