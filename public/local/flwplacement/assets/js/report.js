(function () {
  "use strict";

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function downloadText(filename, text, mimeType) {
    const blob = new Blob([text], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  function renderSkillBars(skillPercentages) {
    return Object.entries(skillPercentages)
      .map(([skill, value]) => `
        <div class="meter-row">
          <strong>${escapeHtml(skill)}</strong>
          <div class="meter-shell" aria-label="${escapeHtml(skill)} score">
            <div class="meter-fill" style="--value: ${Math.round(value)}%"></div>
          </div>
          <span>${Math.round(value)}</span>
        </div>
      `)
      .join("");
  }

  function renderWarnings(warnings) {
    if (!warnings.length) {
      return "<li>Skill balance is strong enough to begin the recommended FLW unit.</li>";
    }
    return warnings.map((warning) => `<li>${escapeHtml(warning.message)} Score: ${Math.round(warning.score)}.</li>`).join("");
  }

  function renderReport(result) {
    const safeJson = escapeHtml(JSON.stringify({
      cefr_level: result.cefr_level,
      skill_profile: result.skill_profile,
      recommended_course: result.recommended_course,
      starting_unit: result.starting_unit,
      confidence_score: result.confidence_score
    }, null, 2));

    return `
      <div class="panel">
        <div class="report-grid">
          <div class="level-badge">
            <div>
              <span>CEFR Level</span>
              <strong>${escapeHtml(result.cefr_level)}</strong>
              <span>${Math.round(result.weighted_score)} weighted score</span>
            </div>
          </div>

          <div class="question-card">
            <div>
              <p class="eyebrow">Placement report</p>
              <h2>${escapeHtml(result.learner_profile.learnerName || "Learner")}</h2>
              <p class="muted">${escapeHtml(result.study_recommendation)}</p>
            </div>

            <div class="recommendation-grid">
              <div class="mini-card"><span>FLW Track</span><strong>${escapeHtml(result.recommended_course)}</strong></div>
              <div class="mini-card"><span>Starting Unit</span><strong>${escapeHtml(result.starting_unit)}</strong></div>
              <div class="mini-card"><span>Confidence</span><strong>${escapeHtml(result.confidence_score)}%</strong></div>
            </div>
          </div>
        </div>

        <hr>

        <div class="question-layout">
          <section>
            <h3>Skill Profile</h3>
            <div class="meter-grid">${renderSkillBars(result.skill_percentages)}</div>
          </section>

          <aside class="side-panel">
            <h3>Weak Skill Warnings</h3>
            <ul class="warning-list">${renderWarnings(result.weak_skill_warnings)}</ul>
          </aside>
        </div>

        <hr>

        <h3>Placement Result JSON</h3>
        <pre class="json-box">${safeJson}</pre>

        <div class="button-row">
          <button id="downloadJson" class="primary" type="button">Export JSON</button>
          <button id="downloadCsv" class="secondary" type="button">Export Moodle CSV</button>
          <button id="printReport" class="ghost" type="button">Print Report</button>
          <button id="restartTest" class="danger" type="button">New Test</button>
        </div>
      </div>
    `;
  }

  function csvEscape(value) {
    const text = String(value == null ? "" : value);
    return `"${text.replace(/"/g, '""')}"`;
  }

  function buildMoodleCsv(questionBank) {
    const header = [
      "questionname",
      "questiontext",
      "A",
      "B",
      "C",
      "D",
      "correctanswer",
      "cefr",
      "skill",
      "difficulty"
    ];
    const rows = questionBank.coreQuestions.map((question) => {
      const options = question.options.slice(0, 4);
      while (options.length < 4) {
        options.push("");
      }
      return [
        question.id,
        question.text,
        options[0],
        options[1],
        options[2],
        options[3],
        question.answer,
        question.cefr,
        question.skill,
        question.difficulty
      ].map(csvEscape).join(",");
    });
    return [header.join(","), ...rows].join("\r\n");
  }

  window.FLWPlacementReport = {
    renderReport,
    buildMoodleCsv,
    downloadJson(result) {
      downloadText(`flw-placement-${Date.now()}.json`, JSON.stringify(result, null, 2), "application/json");
    },
    downloadMoodleCsv(questionBank) {
      downloadText("flw-placement-moodle-question-bank.csv", buildMoodleCsv(questionBank), "text/csv");
    }
  };
})();
