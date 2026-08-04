(function () {
  'use strict';

  function getIsoWeekValue(date) {
    var current = new Date(date.getTime());
    current.setHours(0, 0, 0, 0);

    var day = current.getDay();
    var dayNum = day === 0 ? 7 : day;
    current.setDate(current.getDate() + (4 - dayNum));

    var yearStart = new Date(current.getFullYear(), 0, 1);
    var weekNo = Math.ceil((((current - yearStart) / 86400000) + 1) / 7);

    return current.getFullYear() + '-W' + String(weekNo).padStart(2, '0');
  }

  function pickDateInput(root, name) {
    return root.querySelector('[name="' + name + '"]');
  }

  var root = document.getElementById('statistics-report-root');
  if (!root) {
    return;
  }

  var form = root.querySelector('[data-report-form]');
  if (!form) {
    return;
  }

  var periodRadios = root.querySelectorAll('input[name="period_type"]');
  var periodCards = root.querySelectorAll('.statistics-report__type-card');
  var periodPanels = root.querySelectorAll('[data-period-panel]');
  var waitBox = root.querySelector('[data-report-wait]');
  var waitText = root.querySelector('[data-report-status-text]');
  var waitBar = root.querySelector('[data-report-progress-bar]');
  var submitButton = root.querySelector('[data-report-submit]');
  var quickButtons = root.querySelectorAll('[data-report-quick]');

  var waitMessages = [
    root.getAttribute('data-wait-status-1') || 'Preparing data ...',
    root.getAttribute('data-wait-status-2') || 'Aggregating metrics ...',
    root.getAttribute('data-wait-status-3') || 'Building PDF ...',
    root.getAttribute('data-wait-status-4') || 'Almost done ...'
  ];

  function currentType() {
    var selected = 'month';

    periodRadios.forEach(function (radio) {
      if (radio.checked) {
        selected = radio.value;
      }
    });

    return selected;
  }

  function setType(type) {
    periodRadios.forEach(function (radio) {
      radio.checked = radio.value === type;
    });

    syncPeriodUi();
  }

  function syncPeriodUi() {
    var type = currentType();

    periodCards.forEach(function (card) {
      var radio = card.querySelector('input[name="period_type"]');
      card.classList.toggle('is-active', !!radio && radio.value === type);
    });

    periodPanels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.getAttribute('data-period-panel') === type);
    });
  }

  periodRadios.forEach(function (radio) {
    radio.addEventListener('change', syncPeriodUi);
  });

  quickButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var mode = button.getAttribute('data-report-quick');

      if (mode === 'last_week') {
        var now = new Date();
        now.setDate(now.getDate() - 7);
        pickDateInput(root, 'period_week').value = button.getAttribute('data-week-value') || getIsoWeekValue(now);
        setType('week');
      }

      if (mode === 'last_month') {
        var monthValue = button.getAttribute('data-month-value');
        if (monthValue) {
          pickDateInput(root, 'period_month').value = monthValue;
        }
        setType('month');
      }

      if (mode === 'last_year') {
        var yearValue = button.getAttribute('data-year-value');
        if (yearValue) {
          pickDateInput(root, 'period_year').value = yearValue;
        }
        setType('year');
      }
    });
  });

  var isSubmitting = false;

  form.addEventListener('submit', function () {
    if (isSubmitting) {
      return;
    }

    isSubmitting = true;

    if (submitButton) {
      submitButton.disabled = true;
    }

    if (waitBox) {
      waitBox.classList.add('is-visible');
    }

    var progress = 8;
    var messageIndex = 0;

    if (waitBar) {
      waitBar.style.width = progress + '%';
    }

    if (waitText) {
      waitText.textContent = waitMessages[messageIndex];
    }

    var intervalId = window.setInterval(function () {
      progress = Math.min(progress + 5, 93);
      messageIndex = Math.min(messageIndex + 1, waitMessages.length - 1);

      if (waitBar) {
        waitBar.style.width = progress + '%';
      }

      if (waitText) {
        waitText.textContent = waitMessages[messageIndex];
      }

      if (progress >= 93) {
        window.clearInterval(intervalId);
      }
    }, 850);
  });

  syncPeriodUi();
})();
