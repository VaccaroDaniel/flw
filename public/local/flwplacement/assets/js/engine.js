(function () {
  "use strict";

  const STORAGE_KEY = "flw-placement-progress-v1";
  const SKILL_WEIGHTS = {
    grammar: 0.3,
    vocabulary: 0.25,
    reading: 0.2,
    listening: 0.15,
    writing: 0.1
  };
  const START_LEVEL = "A2";
  const TARGET_ADAPTIVE_QUESTIONS = 30;

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function average(values) {
    if (!values.length) {
      return 0;
    }
    return values.reduce((sum, value) => sum + value, 0) / values.length;
  }

  function wordCount(text) {
    return String(text || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean).length;
  }

  function scoreToCefr(score, includePlus) {
    const value = Math.round(score);
    let level = "Pre-A1";
    let upper = 20;

    if (value <= 20) {
      level = "Pre-A1";
      upper = 20;
    } else if (value <= 35) {
      level = "A1";
      upper = 35;
    } else if (value <= 50) {
      level = "A2";
      upper = 50;
    } else if (value <= 65) {
      level = "B1";
      upper = 65;
    } else if (value <= 80) {
      level = "B2";
      upper = 80;
    } else {
      level = "C1";
      upper = 90;
    }

    if (includePlus && level !== "C1" && value >= upper - 4 && value > 20) {
      return `${level}+`;
    }
    return level;
  }

  function cefrToUnit(level) {
    const unitMap = {
      "Pre-A1": 1,
      A1: 5,
      A2: 13,
      B1: 25,
      B2: 37,
      C1: 49
    };
    return unitMap[level] || 1;
  }

  function evaluateWriting(text) {
    const words = wordCount(text);
    const sentences = String(text || "").split(/[.!?]+/).filter((part) => part.trim().length > 0).length;
    const connectors = (String(text || "").match(/\b(because|although|however|therefore|also|first|second|but|so|when|while)\b/gi) || []).length;
    const uniqueWords = new Set(String(text || "").toLowerCase().match(/[a-z']+/g) || []).size;

    let score = 12;
    if (words >= 20) score += 15;
    if (words >= 40) score += 18;
    if (words >= 60) score += 8;
    if (words > 90) score -= 8;
    score += clamp(sentences, 0, 5) * 5;
    score += clamp(connectors, 0, 4) * 5;
    score += clamp(uniqueWords / 2, 0, 18);

    return clamp(Math.round(score), 0, 90);
  }

  function answerRecord(question, selected) {
    return {
      questionId: question.id,
      cefr: question.cefr,
      skill: question.skill,
      difficulty: question.difficulty,
      selected,
      answer: question.answer,
      correct: selected === question.answer
    };
  }

  class FLWPlacementEngine {
    constructor(questionBank) {
      this.bank = questionBank;
      this.levels = questionBank.levels.slice();
      this.reset();
    }

    reset(learnerProfile) {
      this.state = {
        learnerProfile: learnerProfile || {},
        phase: "adaptive",
        currentLevelIndex: this.levels.indexOf(START_LEVEL),
        streakCorrect: 0,
        streakWrong: 0,
        adaptiveTarget: TARGET_ADAPTIVE_QUESTIONS,
        adaptiveAnswers: [],
        diagnosticAnswers: [],
        profileAnswers: {},
        writingResponse: "",
        speakingAcknowledged: false,
        usedQuestionIds: [],
        result: null,
        startedAt: new Date().toISOString()
      };
      this.saveProgress();
      return this.state;
    }

    load(savedState) {
      this.state = Object.assign({}, this.state || {}, savedState || {});
      if (!Array.isArray(this.state.usedQuestionIds)) {
        this.state.usedQuestionIds = [];
      }
      return this.state;
    }

    getCurrentLevel() {
      return this.levels[this.state.currentLevelIndex];
    }

    getAdaptiveProgress() {
      return this.state.adaptiveAnswers.length / this.state.adaptiveTarget;
    }

    isAdaptiveComplete() {
      return this.state.adaptiveAnswers.length >= this.state.adaptiveTarget;
    }

    getNextAdaptiveQuestion() {
      if (this.isAdaptiveComplete()) {
        this.state.phase = "diagnostic";
        this.saveProgress();
        return null;
      }

      const currentLevel = this.getCurrentLevel();
      const answeredSkills = this.state.adaptiveAnswers.reduce((counts, answer) => {
        counts[answer.skill] = (counts[answer.skill] || 0) + 1;
        return counts;
      }, {});

      const availableAtLevel = this.bank.coreQuestions.filter((question) => (
        question.cefr === currentLevel && !this.state.usedQuestionIds.includes(question.id)
      ));

      const pool = availableAtLevel.length
        ? availableAtLevel
        : this.bank.coreQuestions.filter((question) => !this.state.usedQuestionIds.includes(question.id));

      if (!pool.length) {
        this.state.phase = "diagnostic";
        this.saveProgress();
        return null;
      }

      return pool
        .slice()
        .sort((a, b) => {
          const skillGap = (answeredSkills[a.skill] || 0) - (answeredSkills[b.skill] || 0);
          if (skillGap !== 0) return skillGap;
          return Math.abs(a.difficulty - 0.5) - Math.abs(b.difficulty - 0.5);
        })[0];
    }

    answerAdaptive(question, selected) {
      const record = answerRecord(question, selected);
      this.state.adaptiveAnswers.push(record);
      this.state.usedQuestionIds.push(question.id);

      if (record.correct) {
        this.state.streakCorrect += 1;
        this.state.streakWrong = 0;
      } else {
        this.state.streakWrong += 1;
        this.state.streakCorrect = 0;
      }

      if (this.state.streakCorrect >= 3) {
        this.state.currentLevelIndex = clamp(this.state.currentLevelIndex + 1, 0, this.levels.length - 1);
        this.state.streakCorrect = 0;
        this.state.streakWrong = 0;
      }

      if (this.state.streakWrong >= 3) {
        this.state.currentLevelIndex = clamp(this.state.currentLevelIndex - 1, 0, this.levels.length - 1);
        this.state.streakCorrect = 0;
        this.state.streakWrong = 0;
      }

      if (this.isAdaptiveComplete()) {
        this.state.phase = "diagnostic";
      }

      this.saveProgress();
      return record;
    }

    answerDiagnostic(question, selected) {
      this.state.diagnosticAnswers.push(answerRecord(question, selected));
      this.saveProgress();
    }

    setWritingResponse(text) {
      this.state.writingResponse = text || "";
      this.saveProgress();
    }

    setProfileAnswer(questionId, value) {
      this.state.profileAnswers[questionId] = Number(value);
      this.saveProgress();
    }

    setSpeakingAcknowledged(value) {
      this.state.speakingAcknowledged = Boolean(value);
      this.saveProgress();
    }

    getAllObjectiveAnswers() {
      return this.state.adaptiveAnswers.concat(this.state.diagnosticAnswers);
    }

    computeSkillScores() {
      const allAnswers = this.getAllObjectiveAnswers();
      const skillScores = {};

      Object.keys(SKILL_WEIGHTS).forEach((skill) => {
        if (skill === "writing") {
          skillScores.writing = evaluateWriting(this.state.writingResponse);
          return;
        }

        const answers = allAnswers.filter((answer) => answer.skill === skill);
        const correctPercent = answers.length
          ? (answers.filter((answer) => answer.correct).length / answers.length) * 100
          : 0;
        const difficultyBonus = answers.length
          ? average(answers.filter((answer) => answer.correct).map((answer) => answer.difficulty * 12))
          : 0;
        skillScores[skill] = clamp(Math.round(correctPercent * 0.86 + difficultyBonus), 0, 90);
      });

      return skillScores;
    }

    computeConfidenceScore() {
      const profileValues = Object.values(this.state.profileAnswers).map(Number).filter(Boolean);
      const selfConfidence = profileValues.length ? average(profileValues) * 20 : 50;
      const objectiveAnswers = this.getAllObjectiveAnswers();
      const objectiveReliability = objectiveAnswers.length
        ? clamp((objectiveAnswers.length / (TARGET_ADAPTIVE_QUESTIONS + this.bank.diagnosticReading.length + this.bank.diagnosticListening.length)) * 100, 0, 100)
        : 0;
      return Math.round(selfConfidence * 0.75 + objectiveReliability * 0.25);
    }

    chooseCourse(level) {
      const profile = this.state.learnerProfile || {};
      if (profile.courseLanguage === "russian" || profile.targetWorld === "russian") {
        return "Russian World";
      }
      if (profile.courseLanguage === "chinese" || profile.targetWorld === "chinese") {
        return "Chinese World";
      }
      if (profile.targetWorld === "adventure" || (profile.ageBand === "junior" && ["Pre-A1", "A1", "A2", "B1"].includes(level))) {
        return "Adventure World";
      }
      return "Real World";
    }

    buildWeakSkillWarnings(skillScores) {
      return Object.entries(skillScores)
        .filter((entry) => entry[1] < 55)
        .sort((a, b) => a[1] - b[1])
        .map(([skill, score]) => ({
          skill,
          score,
          message: `${skill[0].toUpperCase()}${skill.slice(1)} needs focused review before the learner moves quickly through the next FLW unit.`
        }));
    }

    buildStudyRecommendation(level, weakSkills) {
      if (!weakSkills.length) {
        return `Begin at ${level} material and use weekly progress checks to confirm the learner can keep pace.`;
      }
      const focus = weakSkills.slice(0, 2).map((item) => item.skill).join(" and ");
      return `Begin at the recommended unit, then assign extra ${focus} practice before the next progress test.`;
    }

    finish() {
      const skillScores = this.computeSkillScores();
      const weightedScore = Object.entries(SKILL_WEIGHTS).reduce((sum, [skill, weight]) => {
        return sum + (skillScores[skill] || 0) * weight;
      }, 0);
      const cefrLevel = scoreToCefr(weightedScore, false);
      const skillProfile = Object.fromEntries(
        Object.entries(skillScores).map(([skill, score]) => [skill, scoreToCefr(score, true)])
      );
      const weakSkillWarnings = this.buildWeakSkillWarnings(skillScores);
      const recommendedCourse = this.chooseCourse(cefrLevel);
      const result = {
        cefr_level: cefrLevel,
        skill_profile: skillProfile,
        recommended_course: recommendedCourse,
        starting_unit: cefrToUnit(cefrLevel),
        confidence_score: this.computeConfidenceScore(),
        weighted_score: Math.round(weightedScore),
        skill_percentages: skillScores,
        weak_skill_warnings: weakSkillWarnings,
        study_recommendation: this.buildStudyRecommendation(cefrLevel, weakSkillWarnings),
        learner_profile: this.state.learnerProfile,
        adaptive_summary: {
          start_level: START_LEVEL,
          final_internal_level: this.getCurrentLevel(),
          questions_answered: this.state.adaptiveAnswers.length,
          correct: this.state.adaptiveAnswers.filter((answer) => answer.correct).length
        },
        generated_at: new Date().toISOString(),
        flw_integration: {
          placement_source: "standalone-offline-prototype",
          moodle_question_bank_ready: true,
          learning_path_ready: true
        }
      };

      this.state.result = result;
      this.state.phase = "result";
      this.saveProgress();
      return result;
    }

    saveProgress() {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
      } catch (error) {
        return false;
      }
      return true;
    }

    clearProgress() {
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch (error) {
        return false;
      }
      return true;
    }

    static loadSavedState() {
      try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
      } catch (error) {
        return null;
      }
    }
  }

  window.FLWPlacementEngine = FLWPlacementEngine;
  window.FLWPlacementScoring = {
    scoreToCefr,
    evaluateWriting,
    cefrToUnit,
    STORAGE_KEY
  };
})();
