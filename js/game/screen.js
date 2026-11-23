document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("screen-root");
  if (!root) return;

  // Correct for this device's clock being wrong, once, up front — every
  // countdown below reads time through BingoClock.now() instead of
  // Date.now() so it hits zero at the same real-world moment as the
  // player-facing my_cards.js countdown, regardless of either device's
  // own clock. Requires js/clock.js loaded before this file.
  BingoClock.init(root.dataset.serverNow);

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
  // MIRROR MODE
  // Open extra screens as screen.php?game_id=5&mirror=1. A mirror never
  // draws; it just follows the game and reloads when a new number lands.
  // knownDrawnCount is also sent with every draw request so the server can
  // refuse a draw from a screen that has fallen behind.
  // ============================================================
  const isMirror = new URLSearchParams(location.search).get("mirror") === "1";
  let knownDrawnCount = parseInt(root.dataset.drawnCount, 10) || 0;

  // ============================================================
  // DRIVER ELECTION (same browser)
  // Only ONE tab should run the auto-draw timer. Tabs in the same browser
  // elect a driver through a heartbeat in localStorage: the driver rewrites
  // its record every second, and any other tab only takes over if that
  // record goes stale (driver closed or crashed). Non-driver tabs just
  // follow along and reload when the driver draws.
  // Screens on OTHER devices can't see this, so open those as ?mirror=1.
  // ============================================================
  const DRIVER_KEY = "bingoDriver_" + gameId;
  const DRIVER_STALE_MS = 4000;
  const tabId = Math.random().toString(36).slice(2);
  let isDriver = false;

  function electDriver() {
    if (isMirror) {
      isDriver = false;
      return;
    }
    try {
      let rec = null;
      try {
        rec = JSON.parse(localStorage.getItem(DRIVER_KEY));
      } catch (e) {
        rec = null;
      }

      const now = Date.now();
      if (!rec || rec.id === tabId || now - rec.ts > DRIVER_STALE_MS) {
        localStorage.setItem(
          DRIVER_KEY,
          JSON.stringify({ id: tabId, ts: now }),
        );
        // Read back: if two tabs wrote at once, the last writer wins.
        const check = JSON.parse(localStorage.getItem(DRIVER_KEY));
        isDriver = !!check && check.id === tabId;
      } else {
        isDriver = false;
      }
    } catch (e) {
      // localStorage unavailable — fall back to every tab driving; the
      // server-side guard in draw_number.php still prevents double draws.
      isDriver = !isMirror;
    }
  }

  electDriver();

  setInterval(() => {
    const wasDriver = isDriver;
    electDriver();

    // This tab just became the driver (previous driver closed) — start the
    // auto-draw timer that was being skipped while it was a follower.
    if (
      isDriver &&
      !wasDriver &&
      gameStarted &&
      drawMode === "auto" &&
      !inCardChangeWindow &&
      !isDrawing
    ) {
      scheduleAutoDraw();
    }
  }, 1000);

  // Hand over immediately when the driver tab closes or reloads, so the
  // next tab doesn't have to wait for the stale timeout.
  window.addEventListener("pagehide", () => {
    try {
      const rec = JSON.parse(localStorage.getItem(DRIVER_KEY));
      if (rec && rec.id === tabId) localStorage.removeItem(DRIVER_KEY);
    } catch (e) {
      /* ignore */
    }
  });

  // ============================================================
  // SOUND (BGM + NUMBER VOICE-OVER)
  // Browsers block audio until the page has had a user interaction,
  // so the floating #soundToggle button starts as "Click to enable
  // sound". Any click/keypress on the page unlocks audio; after that
  // the same button toggles mute. The mute choice is remembered in
  // localStorage so it survives the location.reload() calls below.
  // ============================================================
  const SOUND_MUTED_KEY = "bingoScreenMuted";
  const VOICE_FOLDER = "js/audio/numbers/";

  let soundMuted = false;
  try {
    soundMuted = localStorage.getItem(SOUND_MUTED_KEY) === "1";
  } catch (e) {
    /* localStorage unavailable — default to unmuted */
  }

  let audioUnlocked = false;
  const soundBtn = document.getElementById("soundToggle");

  function updateSoundBtn() {
    if (!soundBtn) return;
    if (!audioUnlocked) {
      soundBtn.textContent = "🔊 Click to enable sound";
    } else {
      soundBtn.textContent = soundMuted ? "🔇 Muted" : "🔊 Sound on";
    }
  }

  function unlockAudio() {
    if (audioUnlocked) return;
    audioUnlocked = true;
    updateSoundBtn();
  }

  // Button handler runs before the document-level one (bubbling), so the
  // very first click on the button only unlocks; later clicks toggle mute.
  if (soundBtn) {
    soundBtn.addEventListener("click", function () {
      if (!audioUnlocked) {
        unlockAudio();
        return;
      }
      soundMuted = !soundMuted;
      try {
        localStorage.setItem(SOUND_MUTED_KEY, soundMuted ? "1" : "0");
      } catch (e) {
        /* ignore */
      }
      if (soundMuted) {
        spinBgm.pause();
        stopVoice();
      }
      updateSoundBtn();
    });
  }
  document.addEventListener("click", unlockAudio);
  document.addEventListener("keydown", unlockAudio);
  updateSoundBtn();

  // ---------- Number voice-over ----------
  // functions/voice_manifest.php lists which clips exist for each number
  // (b1.mp3, b1_2.mp3, ...). Numbers can have 1, 2 or more clips — one is
  // picked at random each time. Numbers with no clip stay silent.
  let voiceManifest = {};
  const lastVoiceFile = {};
  let currentVoice = null;

  fetch("functions/voice_manifest.php?v=" + Date.now())
    .then((res) => res.json())
    .then((m) => {
      voiceManifest = m || {};

      const missing = [];
      [
        ["b", 1, 15],
        ["i", 16, 30],
        ["n", 31, 45],
        ["g", 46, 60],
        ["o", 61, 75],
      ].forEach(([l, from, to]) => {
        for (let n = from; n <= to; n++) {
          if (!voiceManifest[l + n]) missing.push(l + n);
        }
      });
      if (missing.length) {
        console.warn("No voice clip for:", missing.join(", "));
      }
    })
    .catch((err) => console.warn("Voice manifest failed:", err));

  function stopVoice() {
    if (currentVoice) {
      currentVoice.pause();
      currentVoice = null;
    }
  }

  function playNumberVoice(letter, number) {
    if (soundMuted) return;

    const key = letter.toLowerCase() + number;
    const files = voiceManifest[key];
    if (!files || files.length === 0) return; // no clip recorded — stay silent

    // With 2+ clips avoid repeating the last one; with 1 clip just use it.
    const pool =
      files.length > 1 ? files.filter((f) => f !== lastVoiceFile[key]) : files;

    const file = pool[Math.floor(Math.random() * pool.length)];
    lastVoiceFile[key] = file;

    stopVoice();
    currentVoice = new Audio(VOICE_FOLDER + file);
    currentVoice.play().catch((err) => console.warn("Voice blocked:", err));
  }

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
      const diff = cardChangeDeadline - BingoClock.now().getTime();

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
      const diff = scheduledStart - BingoClock.now().getTime();

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

  const spinBgm = new Audio("js/audio/bgm.mp3");
  spinBgm.loop = true;
  spinBgm.volume = 0.5; // adjust to taste

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

    // Cut off any voice clip still playing from the previous draw.
    stopVoice();

    if (!soundMuted) {
      spinBgm.currentTime = 0;
      spinBgm.play().catch((err) => console.warn("Audio play blocked:", err));
    }

    function tick() {
      const elapsed = performance.now() - startTime;

      if (elapsed >= DRAW_REVEAL.durationMs) {
        spinBgm.pause();
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
    knownDrawnCount = data.drawnNumbers.length;

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

      // Announce the number now that the spin has settled on it.
      playNumberVoice(letter, number);

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
  if (isMirror && drawBtn) drawBtn.style.display = "none";

  // ============================================================
  // FOLLOWING DRAWS MADE BY ANOTHER SCREEN
  // When a different screen draws a number, this one plays the same reveal
  // (fly-to-history, spin, voice) instead of just reloading. Same-browser
  // tabs hear about it instantly over a BroadcastChannel; screens on other
  // devices notice it through the status poll below (up to ~3s later).
  // ============================================================
  const drawChannel =
    "BroadcastChannel" in window
      ? new BroadcastChannel("bingo-draw-" + gameId)
      : null;

  function applyRemoteDraw(data) {
    if (isDrawing) return; // already busy with a reveal

    const count = data.drawnNumbers.length;
    if (count <= knownDrawnCount) return; // already showing this number

    // Missed more than one draw, or the reveal area isn't on the page
    // (e.g. still in the card-change view) — just reload to catch up.
    if (
      count > knownDrawnCount + 1 ||
      !document.getElementById("current-ball-wrap")
    ) {
      location.reload();
      return;
    }

    isDrawing = true;
    if (drawBtn) {
      drawBtn.disabled = true;
      drawBtn.innerHTML = "Drawing...";
    }

    renderDrawResult(data, function () {
      isDrawing = false;
      scheduleAutoDraw(); // no-op unless this tab is the driver
    });
  }

  if (drawChannel) {
    drawChannel.onmessage = (e) => {
      if (e.data && e.data.type === "draw" && e.data.data) {
        applyRemoteDraw(e.data.data);
      }
    };
  }

  // ============================================================
  // SHARED DRAW TRIGGER
  // Used by the manual button AND the auto-draw timer, so both
  // paths get the same in-flight guard, error handling, and
  // animation sequencing.
  // ============================================================
  function triggerDraw() {
    if (isMirror) return; // mirror screens never draw
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
      body: "game_id=" + gameId + "&expected_count=" + knownDrawnCount,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.outOfSync) {
          // Another screen already drew; catch up by reloading.
          location.reload();
          return;
        }

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

        // Tell other tabs in this browser so they play the same spin + voice
        // at the same moment (other devices catch up through polling).
        if (drawChannel) {
          try {
            drawChannel.postMessage({ type: "draw", data: data });
          } catch (e) {
            /* ignore */
          }
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
    if (isMirror || !isDriver) return; // only the driver tab auto-draws
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

        // Another screen drew a number this one hasn't seen — catch up.
        // Skipped while this screen is mid-draw so it can't reload itself.
        const newDrawnCount = parseInt(data.drawn_count);
        if (
          !isDrawing &&
          Number.isFinite(newDrawnCount) &&
          newDrawnCount > knownDrawnCount
        ) {
          const nums = (data.drawn_numbers || []).map((n) => parseInt(n, 10));

          if (nums.length === newDrawnCount) {
            applyRemoteDraw({
              number: nums[nums.length - 1],
              drawnNumbers: nums,
              claimedCount: newClaimed,
              totalWinners: totalWinners,
              winnerNames: data.winner_names || [],
              finished: newClaimed >= totalWinners,
            });
          } else {
            location.reload();
          }
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
