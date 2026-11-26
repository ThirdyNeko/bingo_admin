// Create Game page: live validation + small UI toggles.
// Loaded by create_game.php (alongside js/game/create_game.js, which
// handles the winning-pattern grid).

document.addEventListener("DOMContentLoaded", function () {
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

  // Highlight winners/timer/card-change inputs red when out of range,
  // and say whether the value is too high or too low.
  function validateMinutesInput(input) {
    if (!input) return true;

    const feedback = document.getElementById(input.id + "_feedback");
    const min = parseInt(input.min, 10);
    const max = parseInt(input.max, 10);
    const value = parseInt(input.value, 10);

    let message = "";

    if (isNaN(value)) {
      message = "Enter a value.";
    } else if (value < min) {
      message = "Too low — minimum is " + min + ".";
    } else if (value > max) {
      message = "Too high — maximum is " + max + ".";
    }

    const isInvalid = message !== "";
    input.classList.toggle("is-invalid", isInvalid);
    if (feedback) feedback.textContent = message;

    return !isInvalid;
  }

  const winnersInput = document.getElementById("winners");
  const timerMinutesInput = document.getElementById("timer_minutes");
  const cardChangeMinutesInput = document.getElementById("card_change_minutes");
  const cardChangeLimitInput = document.getElementById("card_change_limit");

  // Toggle the card change inputs based on the switch
  const cardChangeSwitch = document.getElementById("enable_card_change_limit");
  const cardChangeWrap = document.getElementById("cardChangeInputWrap");

  if (cardChangeSwitch && cardChangeWrap) {
    cardChangeSwitch.addEventListener("change", function () {
      cardChangeWrap.classList.toggle("d-none", !cardChangeSwitch.checked);
      validateMinutesInput(cardChangeMinutesInput);
      validateMinutesInput(cardChangeLimitInput);
    });
  }

  [
    winnersInput,
    timerMinutesInput,
    cardChangeMinutesInput,
    cardChangeLimitInput,
  ].forEach(function (input) {
    if (!input) return;
    input.addEventListener("input", function () {
      validateMinutesInput(input);
    });
    input.addEventListener("blur", function () {
      validateMinutesInput(input);
    });
  });

  // Prize fields: optional as a whole, but if any one is filled the
  // other two become required — checked live and again on submit.
  const prizeNameInput = document.getElementById("prize_name");
  const prizeDescInput = document.getElementById("prize_description");
  const prizePicInput = document.getElementById("prize_picture");

  function validatePrizeFields() {
    if (!prizeNameInput || !prizeDescInput || !prizePicInput) return true;

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

    let picFeedback = prizePicInput.parentElement.querySelector(
      ".js-prize-picture-feedback",
    );
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
    [prizeNameInput, prizeDescInput].forEach(function (input) {
      input.addEventListener("input", validatePrizeFields);
      input.addEventListener("blur", validatePrizeFields);
    });
    prizePicInput.addEventListener("change", validatePrizeFields);
  }

  // Only enforce validation on submit for whichever mode/switch is active.
  // Selected by id: the page also contains the sidebar's Change Password
  // form, so a bare querySelector('form') would pick that one instead.
  const form = document.getElementById("createGameForm");
  if (form) {
    form.addEventListener("submit", function (e) {
      let valid = true;

      if (!validateMinutesInput(winnersInput)) valid = false;

      const timerMode = document.getElementById("mode_timer");
      if (timerMode && timerMode.checked) {
        if (!validateMinutesInput(timerMinutesInput)) valid = false;
      }

      if (cardChangeSwitch && cardChangeSwitch.checked) {
        if (!validateMinutesInput(cardChangeMinutesInput)) valid = false;
        if (!validateMinutesInput(cardChangeLimitInput)) valid = false;
      }

      if (!validatePrizeFields()) valid = false;

      if (!valid) e.preventDefault();
    });
  }
});
