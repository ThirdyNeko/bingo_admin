// =========================================================
// BINGO WINNING PATTERN GRID
// =========================================================

document.addEventListener("DOMContentLoaded", function () {
  const size = 5;
  const pattern = Array.from({ length: size }, () => Array(size).fill(0));

  const hiddenInput = document.getElementById("pattern_json");
  const cells = document.querySelectorAll(".pattern-cell");
  const resetPatternBtn = document.getElementById("resetPattern");
  const resetFormBtn = document.getElementById("resetForm");
  const form = document.getElementById("createGameForm");

  function syncPattern() {
    if (hiddenInput) {
      hiddenInput.value = JSON.stringify(pattern);
    }
  }

  function clearPattern() {
    cells.forEach(function (cell) {
      cell.classList.remove("active");
    });

    for (let r = 0; r < size; r++) {
      for (let c = 0; c < size; c++) {
        pattern[r][c] = 0;
      }
    }

    if (hiddenInput) {
      hiddenInput.value = "";
    }
  }

  // Select/deselect pattern squares
  cells.forEach(function (cell) {
    cell.addEventListener("click", function () {
      const row = parseInt(cell.dataset.row, 10);
      const col = parseInt(cell.dataset.col, 10);

      pattern[row][col] = pattern[row][col] ? 0 : 1;

      cell.classList.toggle("active");

      syncPattern();
    });
  });

  // Reset only the winning pattern
  if (resetPatternBtn) {
    resetPatternBtn.addEventListener("click", clearPattern);
  }

  // Reset entire form and pattern
  if (resetFormBtn && form) {
    resetFormBtn.addEventListener("click", function () {
      form.reset();

      clearPattern();

      // Reset UI toggles
      const timerWrap = document.getElementById("timerInputWrap");
      const cardChangeWrap = document.getElementById("cardChangeInputWrap");

      if (timerWrap) {
        timerWrap.classList.add("d-none");
      }

      if (cardChangeWrap) {
        cardChangeWrap.classList.add("d-none");
      }
    });
  }

  // Manual/Timer toggle
  const timerWrap = document.getElementById("timerInputWrap");

  document
    .querySelectorAll('input[name="start_mode"]')
    .forEach(function (radio) {
      radio.addEventListener("change", function () {
        if (timerWrap) {
          timerWrap.classList.toggle("d-none", this.value !== "timer");
        }
      });
    });
});
