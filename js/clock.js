/**
 * clock.js
 *
 * Fixes countdowns drifting when a player's phone clock is wrong.
 * The deadline (card_change_deadline / scheduled_start) is already
 * server-truth (ISO 8601 from PHP's date('c', ...)). The only unreliable
 * piece was comparing it against `new Date()` on the client device.
 *
 * Usage:
 *   1. Make sure the page also emits the server's current time
 *      (window.BingoCardsConfig.serverNow, or #screen-root's
 *      data-server-now) as an ISO 8601 string.
 *   2. Include this file BEFORE my_cards.js / screen.js.
 *   3. Call BingoClock.init(serverNowIso) once on page load.
 *   4. Wherever the old code did `deadline - new Date()`,
 *      use `deadline - BingoClock.now()` instead.
 */
(function (global) {
  let offsetMs = 0;
  let initialized = false;

  function init(serverNowIso) {
    if (!serverNowIso) {
      console.warn(
        "BingoClock.init: no serverNow provided, falling back to device clock",
      );
      offsetMs = 0;
    } else {
      const serverNow = new Date(serverNowIso).getTime();
      offsetMs = serverNow - Date.now();
    }
    initialized = true;
  }

  // Server-adjusted "now". Safe to call every tick — offset is fixed
  // once at init(), so this stays cheap (no repeated network calls).
  function now() {
    if (!initialized) {
      console.warn(
        "BingoClock.now() called before BingoClock.init() — using device clock",
      );
    }
    return new Date(Date.now() + offsetMs);
  }

  // Drop-in countdown helper: ticks a target element's text every
  // second until `deadline` (ISO string or Date), then calls onExpire.
  // Returns the interval id so the caller can clearInterval() it.
  function startCountdown(elementId, deadline, onExpire, formatFn) {
    const el = document.getElementById(elementId);
    if (!el) return null;

    const deadlineMs = (
      deadline instanceof Date ? deadline : new Date(deadline)
    ).getTime();

    function format(msRemaining) {
      if (typeof formatFn === "function") return formatFn(msRemaining);
      const totalSeconds = Math.max(0, Math.floor(msRemaining / 1000));
      const m = Math.floor(totalSeconds / 60);
      const s = totalSeconds % 60;
      return `${m}:${String(s).padStart(2, "0")}`;
    }

    function tick() {
      const remaining = deadlineMs - now().getTime();
      if (remaining <= 0) {
        el.textContent = format(0);
        clearInterval(intervalId);
        if (typeof onExpire === "function") onExpire();
        return;
      }
      el.textContent = format(remaining);
    }

    tick(); // paint immediately, don't wait 1s for first render
    const intervalId = setInterval(tick, 1000);
    return intervalId;
  }

  global.BingoClock = { init, now, startCountdown };
})(window);
