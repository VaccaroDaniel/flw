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

  var LANGUAGE_STORAGE_KEY = "flw.learningLanguage";
  var LANGUAGE_COOKIE_KEY = "flw_learning_language";

  function normalizeLanguageCode(code) {
    var normalized = String(code || "").trim().toLowerCase();
    return normalized === "zh_cn" ? "zh" : normalized;
  }

  function readCookie(name) {
    var parts = String(document.cookie || "").split("; ");
    for (var i = 0; i < parts.length; i += 1) {
      var pair = parts[i].split("=");
      if (pair[0] === name) {
        return decodeURIComponent(pair.slice(1).join("=") || "");
      }
    }
    return "";
  }

  function getPanelLanguageCode() {
    var stored = "";
    try {
      stored = localStorage.getItem(LANGUAGE_STORAGE_KEY) || "";
    } catch (error) {
      stored = "";
    }
    return normalizeLanguageCode(stored || readCookie(LANGUAGE_COOKIE_KEY));
  }

  function placementUrlForLanguage(code) {
    var url = new URL(window.location.href);
    url.searchParams.set("language", normalizeLanguageCode(code));
    return url.toString();
  }

  function redirectToPanelLanguage(config, code) {
    var nextCode = normalizeLanguageCode(code);
    var currentCode = normalizeLanguageCode(config.selectedLearningLanguageCode);
    if (!nextCode || nextCode === currentCode) {
      return false;
    }
    window.location.href = placementUrlForLanguage(nextCode);
    return true;
  }

  function syncPlacementLanguageFromPanel(config) {
    return redirectToPanelLanguage(config, getPanelLanguageCode());
  }

  function bindLearningLanguagePanelSync(config) {
    document.addEventListener("click", function (event) {
      var choice = event.target.closest ? event.target.closest("[data-flw-language-choice]") : null;
      if (!choice) {
        return;
      }
      var nextCode = normalizeLanguageCode(choice.getAttribute("data-language-code"));
      if (!nextCode) {
        return;
      }
      window.setTimeout(function () {
        redirectToPanelLanguage(config, nextCode);
      }, 80);
    });

    window.addEventListener("storage", function (event) {
      if (event.key === LANGUAGE_STORAGE_KEY) {
        redirectToPanelLanguage(config, event.newValue);
      }
    });
  }

  function percent(value) {
    return Math.round(Number(value || 0) * 100);
  }

  function renderLatestProfile(profile) {
    if (!profile) {
      return [
        '<div class="flw-placement-current-profile empty">',
        '<p class="flw-placement-eyebrow">Current placement</p>',
        '<strong>No saved learning map yet</strong>',
        '<span>The next completed test will create the learner profile map.</span>',
        '</div>'
      ].join("");
    }

    var supportFlags = profile.support_flags || {};
    var support = Object.keys(supportFlags).filter(function (key) {
      return supportFlags[key] && key !== "teacher_review_recommended";
    }).map(function (key) {
      return key.replace(/^needs_/, "").replace(/_support$|_repair$/g, "").replace(/_/g, " ");
    });
    var supportText = support.length ? support.slice(0, 3).join(", ") : "None";
    var viewUrl = profile.latest_result_id ? '<a class="btn btn-outline-secondary btn-sm" href="view.php?id=' + encodeURIComponent(profile.latest_result_id) + '">Open latest report</a>' : "";

    return [
      '<div class="flw-placement-current-profile">',
      '<div>',
      '<p class="flw-placement-eyebrow">Current placement</p>',
      '<strong>' + escapeHtml(profile.overall_cefr || "Pending") + '</strong>',
      '<span>' + escapeHtml(profile.course || "FLW") + '</span>',
      '</div>',
      '<div class="flw-placement-current-grid">',
      '<span>Start unit <b>' + escapeHtml(profile.recommended_start_unit || "-") + '</b></span>',
      '<span>Checkpoint <b>' + escapeHtml(profile.next_checkpoint_unit || "-") + '</b></span>',
      '<span>Confidence <b>' + escapeHtml(percent(profile.placement_confidence)) + '%</b></span>',
      '<span>Status <b>' + escapeHtml(profile.placement_status || "pending") + '</b></span>',
      '</div>',
      '<div class="flw-placement-support-line"><span>Repair support</span><b>' + escapeHtml(supportText) + '</b></div>',
      viewUrl,
      '</div>'
    ].join("");
  }

  function countAnswersBySkill(engineInstance) {
    return engineInstance.getAllObjectiveAnswers().reduce(function (counts, answer) {
      counts[answer.skill] = (counts[answer.skill] || 0) + 1;
      return counts;
    }, {});
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

    bindLearningLanguagePanelSync(config);
    if (syncPlacementLanguageFromPanel(config)) {
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
      var targetWorldOptions = config.targetWorldOptions || [
        { value: "selfstudy", label: "Self Study", categoryid: 0 }
      ];
      var targetWorldSelectOptions = targetWorldOptions.map(function (option, index) {
        var selected = index === 0 ? " selected" : "";
        return '<option value="' + escapeHtml(option.value) + '" data-label="' + escapeHtml(option.label) + '" data-categoryid="' + escapeHtml(option.categoryid || 0) + '"' + selected + '>' + escapeHtml(option.label) + '</option>';
      }).join("");
      setProgress("Ready", 0);
      workspace.innerHTML = [
        '<section class="flw-placement-start-shell">',
        '<div class="panel flw-placement-card flw-placement-start-copy">',
        '<p class="flw-placement-eyebrow">Placement learning map</p>',
        '<h3>Build learner profile</h3>',
        '<div class="flw-placement-output-grid">',
        '<span>CEFR band</span>',
        '<span>Start unit</span>',
        '<span>KP mastery</span>',
        '<span>Repair path</span>',
        '<span>Checkpoint</span>',
        '<span>Teacher status</span>',
        '</div>',
        renderLatestProfile(config.latestPlacementProfile),
        '</div>',
        '<div class="panel flw-placement-card">',
        '<form id="flw-placement-start-form" class="flw-placement-form-grid">',
        '<div class="flw-placement-static-field"><span>Learner name</span><strong>' + escapeHtml(config.learnerName || "Learner") + '</strong></div>',
        '<div class="flw-placement-static-field"><span>Learning language</span><strong>' + escapeHtml(config.learningLanguageLabel || config.defaultCourseLanguage || "English") + '</strong></div>',
        '<label class="flw-placement-full">Target world<select id="flw-placement-world">' + targetWorldSelectOptions + '</select></label>',
        '<div class="flw-placement-button-row flw-placement-full">',
        '<button class="btn btn-primary" type="submit">Start placement test</button>',
        saved && saved.phase !== "result" ? '<button id="flw-placement-resume" class="btn btn-secondary" type="button">Resume Saved Test</button>' : '',
        saved ? '<button id="flw-placement-clear" class="btn btn-light" type="button">Clear Saved Test</button>' : '',
        config.canViewReports ? '<a class="btn btn-outline-secondary" href="' + escapeHtml(config.reportsUrl) + '">Placement Reports</a>' : '',
        '<a class="btn btn-outline-secondary" href="' + escapeHtml(config.exportUrl) + '">Question Bank CSV</a>',
        '</div>',
        '</form>',
        '</div>',
        '</section>'
      ].join("");

      document.getElementById("flw-placement-start-form").addEventListener("submit", function (event) {
        event.preventDefault();
        engine.clearProgress();
        var targetWorld = document.getElementById("flw-placement-world");
        var selectedWorld = targetWorld.options[targetWorld.selectedIndex];
        engine.reset({
          learnerName: config.learnerName || "Learner",
          targetWorld: targetWorld.value,
          targetWorldName: selectedWorld ? selectedWorld.getAttribute("data-label") : targetWorld.value,
          targetWorldCategoryId: selectedWorld ? Number(selectedWorld.getAttribute("data-categoryid") || 0) : 0,
          courseLanguage: config.defaultCourseLanguage || "english",
          learningLanguageLabel: config.learningLanguageLabel || config.defaultCourseLanguage || "English",
          learningLanguageCategoryId: Number(config.learningLanguageCategoryId || 0),
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
      var diagnostics = engine.state.diagnosticAnswers;
      var bySkill = countAnswersBySkill(engine);
      return [
        '<aside class="flw-placement-side">',
        '<h4>Evidence map</h4>',
        '<div class="flw-placement-metric"><span>Adaptive items</span><strong>' + adaptive.length + '/' + engine.state.adaptiveTarget + '</strong></div>',
        '<div class="flw-placement-metric"><span>Diagnostic items</span><strong>' + diagnostics.length + '/' + (bank.diagnosticReading.length + bank.diagnosticListening.length) + '</strong></div>',
        '<div class="flw-placement-metric"><span>Reading evidence</span><strong>' + (bySkill.reading || 0) + '</strong></div>',
        '<div class="flw-placement-metric"><span>Listening evidence</span><strong>' + (bySkill.listening || 0) + '</strong></div>',
        '<div class="flw-placement-metric"><span>Writing sample</span><strong>' + (engine.state.writingResponse ? "yes" : "pending") + '</strong></div>',
        '<div class="flw-placement-metric"><span>Voice check</span><strong>' + (engine.state.speakingEvidence ? "added" : "optional") + '</strong></div>',
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
        '<div class="flw-placement-voice-grid">',
        '<label>STT confidence<input id="flw-placement-stt-confidence" type="number" min="0" max="1" step="0.01" value="' + escapeHtml((engine.state.speakingEvidence && engine.state.speakingEvidence.stt_confidence) || "") + '" placeholder="0.00-1.00"></label>',
        '<label>Sentence completion<input id="flw-placement-sentence-completion" type="number" min="0" max="1" step="0.01" value="' + escapeHtml((engine.state.speakingEvidence && engine.state.speakingEvidence.sentence_completion) || "") + '" placeholder="0.00-1.00"></label>',
        '<label>Pronunciation accuracy<input id="flw-placement-pronunciation" type="number" min="0" max="1" step="0.01" value="' + escapeHtml((engine.state.speakingEvidence && engine.state.speakingEvidence.pronunciation_accuracy) || "") + '" placeholder="0.00-1.00"></label>',
        '<label>Fluency<input id="flw-placement-fluency" type="number" min="0" max="1" step="0.01" value="' + escapeHtml((engine.state.speakingEvidence && engine.state.speakingEvidence.fluency) || "") + '" placeholder="0.00-1.00"></label>',
        '</div>',
        '</div>',
        '<div class="flw-placement-button-row"><button id="flw-placement-profile" class="btn btn-primary" type="button">Continue</button></div>',
        '</section>'
      ].join("");
      document.getElementById("flw-placement-profile").addEventListener("click", function () {
        engine.setWritingResponse(document.getElementById("flw-placement-writing").value);
        engine.setSpeakingAcknowledged(document.getElementById("flw-placement-speaking-done").checked);
        var sttConfidence = document.getElementById("flw-placement-stt-confidence").value;
        var sentenceCompletion = document.getElementById("flw-placement-sentence-completion").value;
        var pronunciation = document.getElementById("flw-placement-pronunciation").value;
        var fluency = document.getElementById("flw-placement-fluency").value;
        var hasVoiceEvidence = [sttConfidence, sentenceCompletion, pronunciation, fluency].some(function (value) {
          return value !== "";
        });
        engine.setSpeakingEvidence(hasVoiceEvidence ? {
          stt_confidence: sttConfidence === "" ? undefined : Number(sttConfidence),
          sentence_completion: sentenceCompletion === "" ? undefined : Number(sentenceCompletion),
          pronunciation_accuracy: pronunciation === "" ? undefined : Number(pronunciation),
          fluency: fluency === "" ? undefined : Number(fluency)
        } : null);
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
            speakingAcknowledged: engine.state.speakingAcknowledged,
            speakingEvidence: engine.state.speakingEvidence
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
