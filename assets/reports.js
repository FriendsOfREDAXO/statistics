(function () {
  'use strict';

  function initReports(root) {
    if (!root || root.getAttribute('data-reports-init-done') === '1') {
      return;
    }
    root.setAttribute('data-reports-init-done', '1');

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

    function pickDateInput(currentRoot, name) {
      return currentRoot.querySelector('[name="' + name + '"]');
    }

    var form = root.querySelector('[data-report-form]');
    if (!form) {
      return;
    }

    var periodRadios = root.querySelectorAll('input[name="period_type"]');
    var periodPanels = root.querySelectorAll('[data-period-panel]');
    var waitBox = root.querySelector('[data-report-wait]');
    var waitText = root.querySelector('[data-report-status-text]');
    var waitBar = root.querySelector('[data-report-progress-bar]');
    var submitButton = root.querySelector('[data-report-submit]');
    var submitButtonOriginalHtml = submitButton ? submitButton.innerHTML : '';
    var quickButtons = root.querySelectorAll('[data-report-quick]');
    var waitButtonLabel = root.getAttribute('data-wait-button-label') || '';

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

      periodPanels.forEach(function (panel) {
        var isActive = panel.getAttribute('data-period-panel') === type;
        panel.classList.toggle('is-active', isActive);
        panel.classList.toggle('hidden', !isActive);
      });
    }

    function startGenerateFromQuickSelection() {
      if (!submitButton || submitButton.disabled) {
        return;
      }

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitButton);
        return;
      }

      submitButton.click();
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

        startGenerateFromQuickSelection();
      });
    });

    var isSubmitting = false;
    var progressIntervalId = null;
    var submitDelayId = null;
    var resetFallbackId = null;

    function clearSubmitTimers() {
      if (progressIntervalId !== null) {
        window.clearInterval(progressIntervalId);
        progressIntervalId = null;
      }
      if (submitDelayId !== null) {
        window.clearTimeout(submitDelayId);
        submitDelayId = null;
      }
      if (resetFallbackId !== null) {
        window.clearTimeout(resetFallbackId);
        resetFallbackId = null;
      }
    }

    function resetSubmitState() {
      if (!isSubmitting) {
        return;
      }

      isSubmitting = false;
      clearSubmitTimers();

      if (waitBox) {
        waitBox.style.display = 'none';
      }

      if (waitBar) {
        waitBar.style.width = '8%';
      }

      if (waitText) {
        waitText.textContent = waitMessages[0];
      }

      if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = submitButtonOriginalHtml;
      }
    }

    function scheduleResetAfterReturn() {
      if (!isSubmitting) {
        return;
      }

      window.setTimeout(function () {
        resetSubmitState();
      }, 350);
    }

    window.addEventListener('focus', scheduleResetAfterReturn);
    window.addEventListener('pageshow', scheduleResetAfterReturn);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        scheduleResetAfterReturn();
      }
    });

    form.addEventListener('submit', function (event) {
      if (isSubmitting) {
        event.preventDefault();
        return;
      }

      event.preventDefault();

      isSubmitting = true;

      if (submitButton) {
        submitButton.disabled = true;
        if (waitButtonLabel !== '') {
          submitButton.innerHTML = waitButtonLabel;
        }
      }

      if (waitBox) {
        waitBox.style.display = 'block';
      }

      var progress = 8;
      var messageIndex = 0;

      if (waitBar) {
        waitBar.style.width = progress + '%';
      }

      if (waitText) {
        waitText.textContent = waitMessages[messageIndex];
      }

      progressIntervalId = window.setInterval(function () {
        progress = Math.min(progress + 5, 93);
        messageIndex = Math.min(messageIndex + 1, waitMessages.length - 1);

        if (waitBar) {
          waitBar.style.width = progress + '%';
        }

        if (waitText) {
          waitText.textContent = waitMessages[messageIndex];
        }

        if (progress >= 93) {
          window.clearInterval(progressIntervalId);
          progressIntervalId = null;
        }
      }, 850);

      submitDelayId = window.setTimeout(function () {
        // Submit after a short delay so status UI is painted before download starts.
        HTMLFormElement.prototype.submit.call(form);
      }, 120);

      // Fallback: even without focus/visibility changes, reset the UI after a grace period.
      resetFallbackId = window.setTimeout(function () {
        resetSubmitState();
      }, 15000);
    });

    syncPeriodUi();
  }

  function findReportsRoot(scope) {
    if (scope && scope.querySelector) {
      return scope.querySelector('#statistics-report-root');
    }

    return document.getElementById('statistics-report-root');
  }

  function runInit(scope) {
    var root = findReportsRoot(scope);
    if (root) {
      initReports(root);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      runInit(document);
    });
  } else {
    runInit(document);
  }

  if (typeof jQuery !== 'undefined') {
    jQuery(document).on('rex:ready', function (_event, container) {
      runInit(container || document);
    });
  }
})();
