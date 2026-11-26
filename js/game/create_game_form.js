// =========================================================
// CREATE GAME FORM VALIDATION
// =========================================================

document.addEventListener("DOMContentLoaded", function () {
  // ----- Character counters -----
  function bindCounter(inputId, countId, max) {
    const input = document.getElementById(inputId);
    const count = document.getElementById(countId);

    if (!input || !count) return;

    function update() {
      count.textContent = input.value.length;
      count.classList.toggle("text-danger", input.value.length >= max);
    }

    input.addEventListener("input", update);
    update();
  }

  bindCounter("prize_name", "prize_name_count", 50);
  bindCounter("prize_description", "prize_description_count", 100);

  // ----- Numeric validation -----
  function validateNumericInput(input) {
    if (!input) return true;

    const feedback = document.getElementById(input.id + "_feedback");

    const min = parseInt(input.min, 10);
    const max = parseInt(input.max, 10);
    const value = parseInt(input.value, 10);

    let message = "";

    if (input.value.trim() === "" || isNaN(value)) {
      message = "Enter a value.";
    } else if (value < min) {
      message = "Too low — minimum is " + min + ".";
    } else if (value > max) {
      message = "Too high — maximum is " + max + ".";
    }

    input.classList.toggle("is-invalid", message !== "");

    if (feedback) {
      feedback.textContent = message;
    }

    return message === "";
  }

  const winnersInput = document.getElementById("winners");
  const timerMinutesInput = document.getElementById("timer_minutes");
  const cardChangeMinutesInput = document.getElementById("card_change_minutes");
  const cardChangeLimitInput = document.getElementById("card_change_limit");

  const cardChangeSwitch = document.getElementById("enable_card_change_limit");
  const cardChangeWrap = document.getElementById("cardChangeInputWrap");

  // ----- Card change toggle -----
  if (cardChangeSwitch && cardChangeWrap) {
    cardChangeSwitch.addEventListener("change", function () {
      cardChangeWrap.classList.toggle("d-none", !this.checked);

      if (this.checked) {
        validateNumericInput(cardChangeMinutesInput);
        validateNumericInput(cardChangeLimitInput);
      }
    });
  }

  // ----- Numeric input events -----
  [
    winnersInput,
    timerMinutesInput,
    cardChangeMinutesInput,
    cardChangeLimitInput,
  ].forEach(function (input) {
    if (!input) return;

    input.addEventListener("input", function () {
      validateNumericInput(input);
    });

    input.addEventListener("blur", function () {
      validateNumericInput(input);
    });
  });

  // ----- Prize validation -----
  const prizeNameInput = document.getElementById("prize_name");
  const prizeDescInput = document.getElementById("prize_description");
  const prizePicInput = document.getElementById("prize_picture");

  function validatePrizeFields() {
    if (!prizeNameInput || !prizeDescInput || !prizePicInput) {
      return true;
    }

    const nameFilled = prizeNameInput.value.trim() !== "";
    const descFilled = prizeDescInput.value.trim() !== "";
    const picFilled = prizePicInput.files && prizePicInput.files.length > 0;

    const anyFilled = nameFilled || descFilled || picFilled;

    const nameMissing = anyFilled && !nameFilled;
    const descMissing = anyFilled && !descFilled;
    const picMissing = anyFilled && !picFilled;

    prizeNameInput.classList.toggle("is-invalid", nameMissing);
    prizeDescInput.classList.toggle("is-invalid", descMissing);
    prizePicInput.classList.toggle("is-invalid", picMissing);

    let picFeedback = document.querySelector(".js-prize-picture-feedback");

    if (picMissing) {
      if (!picFeedback) {
        picFeedback = document.createElement("div");
        picFeedback.className =
          "invalid-feedback d-block js-prize-picture-feedback";
        picFeedback.textContent = "A prize picture is required.";

        prizePicInput.insertAdjacentElement("afterend", picFeedback);
      }
    } else if (picFeedback) {
      picFeedback.remove();
    }

    return !(nameMissing || descMissing || picMissing);
  }

  if (prizeNameInput && prizeDescInput && prizePicInput) {
    prizeNameInput.addEventListener("input", validatePrizeFields);
    prizeNameInput.addEventListener("blur", validatePrizeFields);

    prizeDescInput.addEventListener("input", validatePrizeFields);
    prizeDescInput.addEventListener("blur", validatePrizeFields);

    prizePicInput.addEventListener("change", validatePrizeFields);
  }

  // ----- Form submit validation -----
  const form = document.getElementById("createGameForm");

  if (form) {
    form.addEventListener("submit", function (e) {
      let valid = true;

      if (!validateNumericInput(winnersInput)) {
        valid = false;
      }

      const timerMode = document.getElementById("mode_timer");

      if (timerMode && timerMode.checked) {
        if (!validateNumericInput(timerMinutesInput)) {
          valid = false;
        }
      }

      if (cardChangeSwitch && cardChangeSwitch.checked) {
        if (!validateNumericInput(cardChangeMinutesInput)) {
          valid = false;
        }

        if (!validateNumericInput(cardChangeLimitInput)) {
          valid = false;
        }
      }

      if (!validatePrizeFields()) {
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
      }
    });
  }
});
