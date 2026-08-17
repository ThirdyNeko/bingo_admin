const size = 5;
const pattern = Array.from({ length: size }, () => Array(size).fill(0));
const hiddenInput = document.getElementById("pattern_json");

document.querySelectorAll(".pattern-cell").forEach((cell) => {
  cell.addEventListener("click", function () {
    const row = parseInt(this.dataset.row);
    const col = parseInt(this.dataset.col);

    pattern[row][col] = pattern[row][col] ? 0 : 1;
    this.classList.toggle("active");

    hiddenInput.value = JSON.stringify(pattern);
  });
});

// Reset Button
document.getElementById("resetPattern").addEventListener("click", function () {
  document.querySelectorAll(".pattern-cell").forEach((cell) => {
    cell.classList.remove("active");
  });

  for (let r = 0; r < size; r++) {
    for (let c = 0; c < size; c++) {
      pattern[r][c] = 0;
    }
  }

  hiddenInput.value = "";
});

// Start Mode toggle (manual vs timer)
const timerWrap = document.getElementById("timerInputWrap");
document.querySelectorAll('input[name="start_mode"]').forEach((radio) => {
  radio.addEventListener("change", function () {
    timerWrap.classList.toggle("d-none", this.value !== "timer");
  });
});
