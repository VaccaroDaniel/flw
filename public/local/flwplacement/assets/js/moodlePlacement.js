(function () {
  "use strict";

  function readConfig() {
    var node = document.getElementById("flw-placement-config");
    if (!node) {
      return null;
    }
    try {
      return JSON.parse(node.textContent || "{}");
    } catch (error) {
      return null;
    }
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function encodeOption(value) {
    return encodeURIComponent(value).replace(/'/g, "%27");
  }

  function boot() {
    var config = readConfig();
    var bank = window.FLWPlacementQuestionBank;
    var Engine = window.FLWPlacementEngine;
    var report = window.FLWPlacementReport;
    var root = document.getElementById("flw-placement-app");
    var workspace = document.getElementById("flw-placement-workspace");
    var phase = document.getElementById("flw-placement-phase");
    var progressText = document.getElementById("flw-placement-progress-text");
    var progressBar = document.getElementById("flw-placement-progress-bar");

    if (!config || !bank || !Engine || !report || !workspace) {
      return;
    }

    var engine = new Engine(bank);
    var currentQuestion = null;
    var diagnosticQueue = [];
    var diagnosticIndex = 0;

    function setProgress(label, percent) {
      var rounded = Math.max(0, Math.min(100, Math.round(percent)));
      phase.textContent = label;
      progressText.textContent = rounded + "%";
      progressBar.style.width = rounded + "%";
    }

    function totalProgress() {
      var adaptive = engine.state.adaptiveAnswers.length;
      var diagnostics = engine.state.diagnosticAnswers.length;
      var writing = engine.state.writingResponse ? 1 : 0;
      var profile = Object.keys(engine.state.profileAnswers).length;
      var total = engine.state.adaptiveTarget + bank.diagnosticReading.length + bank.diagnosticListening.length + 1 + bank.profileQuestions.length;
      return ((adaptive + diagnostics + writing + profile) / total) * 100;
    }

    function renderStart() {
      var saved = Engine.loadSavedState();
      var languageOptions = config.courseLanguages || [
        { value: "english", label: "English" },
        { value: "russian", label: "Russian" },
        { value: "chinese", label: "Chinese" },
        { value: "japanese", label: "Japanese" },
        { value: "german", label: "German" },
        { value: "french", label: "French" },
        { value: "spanish", label: "Spanish" }
      ];
      var defaultCourseLanguage = config.defaultCourseLanguage || "english";
      var languageSelectOptions = languageOptions.map(function (option) {
        var selected = option.value === defaultCourseLanguage ? " selected" : "";
        return '<option value="' + escapeHtml(option.value) + '"' + selected + '>' + escapeHtml(option.label) + '</option>';
      }).join("");
      setProgress("Ready", 0);
      workspace.innerHTML = [
        '<section class="panel flw-placement-card flw-placement-start">',
        '<div>',
        '<p class="flw-placement-eyebrow">Adaptive CEFR diagnosis</p>',
        '<h3>Start FLW placement</h3>',
        '<p class="flw-placement-muted">This Moodle module reuses the offline FLW engine and saves the structured placement report to Moodle.</p>',
        '</div>',
        '<form id="flw-placement-start-form" class="flw-placement-form-grid">',
        '<label>Learner name<input id="flw-placement-learner" type="text" value="' + escapeHtml(config.learnerName || "Learner") + '"></label>',
        '<label>Learner group<select id="flw-placement-age"><option value="adult">Adult / university</option><option value="junior">Junior learner</option></select></label>',
        '<label>Target world<select id="flw-placement-world"><option value="real">Real World</option><option value="adventure">Adventure World</option><option value="russian">Russian World</option><option value="chinese">Chinese World</option></select></label>',
        '<label>Course language<select id="flw-placement-language">' + languageSelectOptions + '</select></label>',
        '<div class="flw-placement-button-row flw-placement-full">',
        '<button class="btn btn-primary" type="submit">Start Test</button>',
        saved && saved.phase !== "result" ? '<button id="flw-placement-resume" class="btn btn-secondary" type="button">Resume Saved Test</button>' : '',
        saved ? '<button id="flw-placement-clear" class="btn btn-light" type="button">Clear Saved Test</button>' : '',
        config.canViewReports ? '<a class="btn btn-outline-secondary" href="' + escapeHtml(config.reportsUrl) + '">Placement Reports</a>' : '',
        '<a class="btn btn-outline-secondary" href="' + escapeHtml(config.exportUrl) + '">Question Bank CSV</a>',
        '</div>',
        '</form>',
        '</section>'
      ].join("");

      document.getElementById("flw-placement-start-form").addEventListener("submit", function (event) {
        event.preventDefault();
        engine.clearProgress();
        engine.reset({
          learnerName: document.getElementById("flw-placement-learner").value.trim() || config.learnerName || "Learner",
          ageBand: document.getElementById("flw-placement-age").value,
          targetWorld: document.getElementById("flw-placement-world").value,
          courseLanguage: document.getElementById("flw-placement-language").value,
          moodleUserId: config.userid
        });
        beginAdaptive();
      });

      var resume = document.getElementById("flw-placement-resume");
      if (resume) {
        resume.addEventListener("click", function () {
          engine.load(saved);
          resumeFromState();
        });
      }

      var clear = document.getElementById("flw-placement-clear");
      if (clear) {
        clear.addEventListener("click", function () {
          engine.clearProgress();
          renderStart();
        });
      }
    }

    function sidePanel() {
      var adaptive = engine.state.adaptiveAnswers;
      var correct = adaptive.filter(function (answer) { return answer.correct; }).length;
      return [
        '<aside class="flw-placement-side">',
        '<h4>Progress</h4>',
        '<div class="flw-placement-metric"><span>Adaptive answered</span><strong>' + adaptive.length + '/' + engine.state.adaptiveTarget + '</strong></div>',
        '<div class="flw-placement-metric"><span>Adaptive correct</span><strong>' + correct + '</strong></div>',
        '<div class="flw-placement-metric"><span>Streak correct</span><strong>' + engine.state.streakCorrect + '</strong></div>',
        '<div class="flw-placement-metric"><span>Streak wrong</span><strong>' + engine.state.streakWrong + '</strong></div>',
        '<span class="flw-placement-hidden" id="flw-placement-internal-level">' + escapeHtml(engine.getCurrentLevel()) + '</span>',
        '</aside>'
      ].join("");
    }

    function renderQuestion(question, onSelect, contextLabel) {
      setProgress(contextLabel, totalProgress());
      var options = question.options.map(function (option) {
        return '<button class="flw-placement-option" type="button" data-option="' + encodeOption(option) + '">' + escapeHtml(option) + '</button>';
      }).join("");
      var audio = question.audio ? [
        '<div class="flw-placement-audio">',
        '<strong>Audio</strong>',
        '<audio controls preload="none" src="' + escapeHtml(question.audio) + '"></audio>',
        '</div>'
      ].join("") : "";

      workspace.innerHTML = [
        '<div class="flw-placement-question-layout">',
        '<section class="panel flw-placement-card">',
        '<div class="flw-placement-meta"><span>' + escapeHtml(contextLabel) + '</span><span>' + escapeHtml(question.skill) + '</span></div>',
        audio,
        '<p class="flw-placement-question-text">' + escapeHtml(question.text) + '</p>',
        '<div class="flw-placement-options">' + options + '</div>',
        '</section>',
        sidePanel(),
        '</div>'
      ].join("");

      workspace.querySelectorAll(".flw-placement-option").forEach(function (button) {
        button.addEventListener("click", function () {
          onSelect(decodeURIComponent(button.getAttribute("data-option")));
        });
      });
    }

    function beginAdaptive() {
      currentQuestion = engine.getNextAdaptiveQuestion();
      if (!currentQuestion) {
        beginDiagnostic();
        return;
      }
      renderQuestion(currentQuestion, function (selected) {
        engine.answerAdaptive(currentQuestion, selected);
        beginAdaptive();
      }, "Core Adaptive");
    }

    function beginDiagnostic() {
      diagnosticQueue = bank.diagnosticReading.concat(bank.diagnosticListening);
      diagnosticIndex = engine.state.diagnosticAnswers.length;
      renderDiagnosticQuestion();
    }

    function renderDiagnosticQuestion() {
      if (diagnosticIndex >= diagnosticQueue.length) {
        renderWriting();
        return;
      }
      var question = diagnosticQueue[diagnosticIndex];
      renderQuestion(question, function (selected) {
        engine.answerDiagnostic(question, selected);
        diagnosticIndex += 1;
        renderDiagnosticQuestion();
      }, question.skill === "reading" ? "Reading Diagnostic" : "Listening Diagnostic");
    }

    function renderWriting() {
      setProgress("Writing", totalProgress());
      workspace.innerHTML = [
        '<section class="panel flw-placement-card">',
        '<div class="flw-placement-meta"><span>Writing Diagnostic</span><span>40-80 words</span></div>',
        '<h3>Writing response</h3>',
        '<p class="flw-placement-question-text">' + escapeHtml(bank.writingPrompt.text) + '</p>',
        '<textarea id="flw-placement-writing">' + escapeHtml(engine.state.writingResponse || "") + '</textarea>',
        '<div class="flw-placement-side">',
        '<h4>Speaking Prompt</h4>',
        '<p class="flw-placement-muted">' + escapeHtml(bank.speakingPrompt.text) + '</p>',
        '<label class="flw-placement-check"><input id="flw-placement-speaking-done" type="checkbox" ' + (engine.state.speakingAcknowledged ? "checked" : "") + '> Speaking prompt shown to learner</label>',
        '</div>',
        '<div class="flw-placement-button-row"><button id="flw-placement-profile" class="btn btn-primary" type="button">Continue</button></div>',
        '</section>'
      ].join("");
      document.getElementById("flw-placement-profile").addEventListener("click", function () {
        engine.setWritingResponse(document.getElementById("flw-placement-writing").value);
        engine.setSpeakingAcknowledged(document.getElementById("flw-placement-speaking-done").checked);
        renderProfile();
      });
    }

    function renderProfile() {
      setProgress("Learner Profile", totalProgress());
      var questions = bank.profileQuestions.map(function (question) {
        var current = engine.state.profileAnswers[question.id] || 3;
        var ratings = [1, 2, 3, 4, 5].map(function (value) {
          return [
            '<label>',
            '<input type="radio" name="' + escapeHtml(question.id) + '" value="' + value + '" ' + (Number(current) === value ? "checked" : "") + '>',
            '<span>' + value + '</span>',
            '</label>'
          ].join("");
        }).join("");
        return [
          '<div class="flw-placement-rating-row">',
          '<strong>' + escapeHtml(question.text) + '</strong>',
          '<div class="flw-placement-rating-control">' + ratings + '</div>',
          '</div>'
        ].join("");
      }).join("");

      workspace.innerHTML = [
        '<section class="panel flw-placement-card">',
        '<p class="flw-placement-eyebrow">Learner profile</p>',
        '<h3>Confidence rating</h3>',
        '<div>' + questions + '</div>',
        '<div class="flw-placement-button-row"><button id="flw-placement-finish" class="btn btn-primary" type="button">Generate Report</button></div>',
        '</section>'
      ].join("");

      document.getElementById("flw-placement-finish").addEventListener("click", function () {
        bank.profileQuestions.forEach(function (question) {
          var checked = workspace.querySelector('input[name="' + question.id + '"]:checked');
          engine.setProfileAnswer(question.id, checked ? checked.value : 3);
        });
        renderResult(engine.finish());
      });
    }

    function saveResult(resultData) {
      var status = document.getElementById("flw-placement-save-status");
      if (status) {
        status.textContent = "Saving report to Moodle...";
      }

      var separator = config.saveUrl.indexOf("?") === -1 ? "?" : "&";
      return fetch(config.saveUrl + separator + "sesskey=" + encodeURIComponent(config.sesskey), {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          result: resultData,
          attempt: {
            adaptiveAnswers: engine.state.adaptiveAnswers,
            diagnosticAnswers: engine.state.diagnosticAnswers,
            profileAnswers: engine.state.profileAnswers,
            writingResponse: engine.state.writingResponse,
            speakingAcknowledged: engine.state.speakingAcknowledged
          }
        })
      }).then(function (response) {
        return response.json().then(function (body) {
          if (!response.ok || !body.success) {
            throw new Error(body.error || "Save failed");
          }
          return body;
        });
      }).then(function (body) {
        if (status) {
          status.innerHTML = 'Saved to Moodle. <a href="' + escapeHtml(body.viewUrl) + '">Open saved report</a>';
        }
      }).catch(function (error) {
        if (status) {
          status.textContent = "Report generated, but Moodle save failed: " + error.message;
        }
      });
    }

    function renderResult(resultData) {
      setProgress("Complete", 100);
      workspace.innerHTML = report.renderReport(resultData) + '<div id="flw-placement-save-status" class="alert alert-info mt-3">Saving report to Moodle...</div>';
      document.getElementById("downloadJson").addEventListener("click", function () {
        report.downloadJson(resultData);
      });
      document.getElementById("downloadCsv").addEventListener("click", function () {
        report.downloadMoodleCsv(bank);
      });
      document.getElementById("printReport").addEventListener("click", function () {
        window.print();
      });
      document.getElementById("restartTest").addEventListener("click", function () {
        engine.clearProgress();
        renderStart();
      });
      saveResult(resultData);
    }

    function resumeFromState() {
      if (engine.state.phase === "result" && engine.state.result) {
        renderResult(engine.state.result);
        return;
      }
      if (engine.state.phase === "diagnostic") {
        beginDiagnostic();
        return;
      }
      beginAdaptive();
    }

    root.classList.add("flw-placement-loaded");
    renderStart();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
