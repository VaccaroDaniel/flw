(function () {
  "use strict";

  const STORAGE_KEY = "flw-placement-progress-v1";
  const PLACEMENT_ALGORITHM_VERSION = "1.0.0";
  const SKILL_WEIGHTS = {
    grammar: 0.1,
    vocabulary: 0.1,
    reading: 0.24,
    listening: 0.24,
    speaking: 0.16,
    writing: 0.16
  };
  const LEGACY_SKILL_WEIGHTS = {
    grammar: 0.3,
    vocabulary: 0.25,
    reading: 0.2,
    listening: 0.15,
    writing: 0.1
  };
  const START_LEVEL = "A2";
  const TARGET_ADAPTIVE_QUESTIONS = 30;
  const CHECKPOINT_UNITS = [12, 24, 36, 48, 60];
  const COURSE_UNIT_MAP = {
    "A1.1": 1,
    "A1.2": 7,
    "A2.1": 13,
    "A2.2": 19,
    "B1.1": 25,
    "B1.2": 31,
    "B2.1": 37,
    "B2.2": 43
  };
  const LEVEL_SCORE = {
    "Pre-A1": 15,
    A1: 30,
    A2: 45,
    B1: 62,
    B2: 78,
    C1: 90
  };
  const BAND_ORDER = ["A1.1", "A1.2", "A2.1", "A2.2", "B1.1", "B1.2", "B2.1", "B2.2"];

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function average(values) {
    if (!values.length) {
      return 0;
    }
    return values.reduce((sum, value) => sum + value, 0) / values.length;
  }

  function roundDecimal(value, decimals) {
    const factor = Math.pow(10, decimals);
    return Math.round(value * factor) / factor;
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

  function scoreToPlacementBand(score) {
    const value = clamp(Math.round(score), 0, 100);
    if (value <= 20) return "A1.1";
    if (value <= 35) return "A1.2";
    if (value <= 50) return "A2.1";
    if (value <= 60) return "A2.2";
    if (value <= 70) return "B1.1";
    if (value <= 80) return "B1.2";
    if (value <= 87) return "B2.1";
    return "B2.2";
  }

  function levelToPlacementBand(level) {
    const value = LEVEL_SCORE[level] == null ? 45 : LEVEL_SCORE[level];
    return scoreToPlacementBand(value);
  }

  function placementBandToScore(band) {
    const scores = {
      "A1.1": 18,
      "A1.2": 30,
      "A2.1": 45,
      "A2.2": 56,
      "B1.1": 66,
      "B1.2": 76,
      "B2.1": 84,
      "B2.2": 90
    };
    return scores[band] || 45;
  }

  function bandIndex(band) {
    return Math.max(0, BAND_ORDER.indexOf(band));
  }

  function cefrToUnit(level) {
    const unitMap = {
      "Pre-A1": 1,
      A1: 1,
      A2: 13,
      B1: 25,
      B2: 37,
      C1: 49
    };
    return unitMap[level] || COURSE_UNIT_MAP[level] || 1;
  }

  function cefrBandToUnit(band) {
    return COURSE_UNIT_MAP[band] || 1;
  }

  function nextCheckpointUnit(startUnit) {
    return CHECKPOINT_UNITS.find((unit) => unit > startUnit) || CHECKPOINT_UNITS[CHECKPOINT_UNITS.length - 1];
  }

  function normalizeLanguage(value) {
    const map = {
      en: "english",
      english: "english",
      ru: "russian",
      russian: "russian",
      zh: "chinese",
      chinese: "chinese",
      ja: "japanese",
      japanese: "japanese",
      de: "german",
      german: "german",
      fr: "french",
      french: "french",
      es: "spanish",
      spanish: "spanish"
    };
    return map[String(value || "").toLowerCase()] || "english";
  }

  function courseIdFromProfile(profile) {
    const language = normalizeLanguage(profile.courseLanguage || profile.learningLanguage);
    const world = String(profile.targetWorld || profile.targetWorldName || "selfstudy").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "");
    return `FLW_${language.toUpperCase()}_${(world || "selfstudy").toUpperCase()}`;
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
      kp: question.kp || question.kp_id || null,
      selected,
      answer: question.answer,
      correct: selected === question.answer
    };
  }

  function deriveKpId(answer, profile) {
    const language = normalizeLanguage(profile.courseLanguage || profile.learningLanguage);
    const skill = String(answer.skill || "general").toLowerCase();
    const band = answer.cefr && answer.cefr.indexOf(".") !== -1 ? answer.cefr : levelToPlacementBand(answer.cefr || "A2");
    return answer.kp || answer.kp_id || `placement.${language}.${band}.${skill}`;
  }

  function answerMastery(answer) {
    const difficulty = Number(answer.difficulty || 0.5);
    if (answer.correct) {
      return clamp(0.62 + difficulty * 0.33, 0, 1);
    }
    return clamp(0.12 + difficulty * 0.18, 0, 0.45);
  }

  function scoreEvidenceBySkill(answers, skill) {
    return answers.filter((answer) => answer.skill === skill);
  }

  function computeObjectiveSkillScore(answers) {
    if (!answers.length) {
      return 0;
    }
    const correctPercent = (answers.filter((answer) => answer.correct).length / answers.length) * 100;
    const correctDifficulty = answers.filter((answer) => answer.correct).map((answer) => Number(answer.difficulty || 0.5) * 12);
    const difficultyBonus = correctDifficulty.length ? average(correctDifficulty) : 0;
    return clamp(Math.round(correctPercent * 0.86 + difficultyBonus), 0, 90);
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
        speakingEvidence: null,
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
      if (!this.state.speakingEvidence) {
        this.state.speakingEvidence = null;
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

    setSpeakingEvidence(evidence) {
      this.state.speakingEvidence = evidence || null;
      this.saveProgress();
    }

    getAllObjectiveAnswers() {
      return this.state.adaptiveAnswers.concat(this.state.diagnosticAnswers);
    }

    computeSkillScores() {
      const allAnswers = this.getAllObjectiveAnswers();
      const skillScores = {};

      Object.keys(LEGACY_SKILL_WEIGHTS).forEach((skill) => {
        if (skill === "writing") {
          skillScores.writing = evaluateWriting(this.state.writingResponse);
          return;
        }
        skillScores[skill] = computeObjectiveSkillScore(scoreEvidenceBySkill(allAnswers, skill));
      });

      return skillScores;
    }

    computeSpeakingProfile(skillScores) {
      const evidence = this.state.speakingEvidence || {};
      const sttConfidence = Number(evidence.stt_confidence ?? evidence.sttConfidence ?? 0);
      const hasSttSignal = sttConfidence > 0;
      const sttReliability = !hasSttSignal ? "medium" : (sttConfidence >= 0.75 ? "high" : (sttConfidence >= 0.45 ? "medium" : "low"));
      const profileConfidence = average(Object.values(this.state.profileAnswers).map(Number).filter(Boolean)) || 3;
      const baseSpeaking = average([
        skillScores.grammar || 0,
        skillScores.vocabulary || 0,
        skillScores.listening || 0,
        profileConfidence * 14
      ]);
      const sentenceCompletion = Number(evidence.sentence_completion ?? evidence.sentenceCompletion ?? (this.state.speakingAcknowledged ? 0.64 : 0.48));
      const pronunciationAccuracy = Number(evidence.pronunciation_accuracy ?? evidence.pronunciationAccuracy ?? (hasSttSignal ? sttConfidence : 0.62));
      const fluency = Number(evidence.fluency ?? (this.state.speakingAcknowledged ? 0.6 : 0.45));
      const speakingScore = clamp(Math.round(baseSpeaking * 0.72 + sentenceCompletion * 28), 0, 90);

      return {
        score: speakingScore,
        pronunciation_accuracy: roundDecimal(clamp(pronunciationAccuracy, 0, 1), 2),
        fluency: roundDecimal(clamp(fluency, 0, 1), 2),
        sentence_completion: roundDecimal(clamp(sentenceCompletion, 0, 1), 2),
        stt_confidence: roundDecimal(clamp(sttConfidence, 0, 1), 2),
        stt_reliability: sttReliability,
        needs_voice_repair: speakingScore < 45 || sentenceCompletion < 0.5
      };
    }

    computeKpMastery(skillScores, speakingProfile) {
      const profile = this.state.learnerProfile || {};
      const buckets = {};
      this.getAllObjectiveAnswers().forEach((answer) => {
        const kpId = deriveKpId(answer, profile);
        if (!buckets[kpId]) {
          buckets[kpId] = [];
        }
        buckets[kpId].push(answerMastery(answer));
      });

      const language = normalizeLanguage(profile.courseLanguage || profile.learningLanguage);
      buckets[`placement.${language}.${scoreToPlacementBand(skillScores.writing || 0)}.writing`] = [(skillScores.writing || 0) / 100];
      buckets[`placement.${language}.${scoreToPlacementBand(speakingProfile.score || 0)}.speaking`] = [(speakingProfile.score || 0) / 100];

      return Object.keys(buckets).sort().reduce((mastery, kpId) => {
        mastery[kpId] = roundDecimal(clamp(average(buckets[kpId]), 0, 1), 2);
        return mastery;
      }, {});
    }

    computeConfidenceScore(skillScores, speakingProfile) {
      const profileValues = Object.values(this.state.profileAnswers).map(Number).filter(Boolean);
      const selfConfidence = profileValues.length ? average(profileValues) * 20 : 50;
      const objectiveAnswers = this.getAllObjectiveAnswers();
      const expectedAnswers = TARGET_ADAPTIVE_QUESTIONS + this.bank.diagnosticReading.length + this.bank.diagnosticListening.length;
      const objectiveReliability = objectiveAnswers.length
        ? clamp((objectiveAnswers.length / expectedAnswers) * 100, 0, 100)
        : 0;
      const coreSkills = ["reading", "listening", "speaking", "writing"].map((skill) => skillScores[skill] || 0);
      const spread = Math.max(...coreSkills) - Math.min(...coreSkills);
      const missingPenalty = clamp((expectedAnswers - objectiveAnswers.length) * 1.25, 0, 30);
      const conflictPenalty = spread > 35 ? clamp((spread - 35) * 0.45, 0, 18) : 0;
      const sttPenalty = speakingProfile.stt_reliability === "low" ? 8 : (speakingProfile.stt_reliability === "medium" ? 2 : 0);
      const base = selfConfidence * 0.35 + objectiveReliability * 0.45 + average(coreSkills) * 0.2;
      return clamp(Math.round(base - missingPenalty - conflictPenalty - sttPenalty), 0, 100);
    }

    chooseCourse(level) {
      const profile = this.state.learnerProfile || {};
      if (profile.targetWorldName) {
        const languageLabel = profile.learningLanguageLabel || profile.courseLanguage || "FLW";
        return `${languageLabel} ${profile.targetWorldName}`;
      }
      return `${profile.learningLanguageLabel || profile.courseLanguage || "FLW"} ${profile.targetWorld || "Self Study"}`;
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

    buildRepairUnits(kpMastery, startUnit) {
      const weak = Object.entries(kpMastery).filter((entry) => entry[1] < 0.7);
      const required = new Set();
      const optional = new Set();
      weak.forEach(([, mastery]) => {
        const repairUnit = Math.max(1, startUnit - (mastery < 0.45 ? 6 : 3));
        if (mastery < 0.45) {
          required.add(repairUnit);
        } else {
          optional.add(repairUnit);
        }
      });
      return {
        required_repair_units: Array.from(required).sort((a, b) => a - b),
        optional_review_units: Array.from(optional).filter((unit) => !required.has(unit)).sort((a, b) => a - b)
      };
    }

    buildSupportFlags(skillScores, speakingProfile, placementConfidence) {
      const supportFlags = {
        needs_pronunciation_support: speakingProfile.needs_voice_repair || speakingProfile.pronunciation_accuracy < 0.55,
        needs_writing_support: (skillScores.writing || 0) < 55,
        needs_grammar_repair: (skillScores.grammar || 0) < 55,
        needs_vocabulary_repair: (skillScores.vocabulary || 0) < 55,
        needs_listening_support: (skillScores.listening || 0) < 55,
        teacher_review_recommended: placementConfidence < 55 || speakingProfile.stt_reliability === "low"
      };
      return supportFlags;
    }

    buildStudyRecommendation(level, weakSkills, supportFlags) {
      const repairAreas = Object.entries(supportFlags)
        .filter(([key, value]) => value && key !== "teacher_review_recommended")
        .map(([key]) => key.replace(/^needs_/, "").replace(/_support$|_repair$/g, "").replace(/_/g, " "));
      if (!weakSkills.length && !repairAreas.length) {
        return `Begin at ${level} material and use weekly progress checks to confirm the learner can keep pace.`;
      }
      const focus = repairAreas.length ? repairAreas.slice(0, 2).join(" and ") : weakSkills.slice(0, 2).map((item) => item.skill).join(" and ");
      return `Begin at the recommended unit, then add ${focus} repair practice before the next checkpoint.`;
    }

    buildPlacementStatus(confidence, supportFlags, skillScores, speakingProfile) {
      const coreScores = ["reading", "listening", "speaking", "writing"].map((skill) => skillScores[skill] || 0);
      const conflict = Math.max(...coreScores) - Math.min(...coreScores);
      if (confidence < 55 || supportFlags.teacher_review_recommended || conflict > 45) {
        return "teacher_review_required";
      }
      if (confidence < 72 || speakingProfile.stt_reliability === "medium") {
        return "provisional";
      }
      return "confirmed";
    }

    finish() {
      const legacySkillScores = this.computeSkillScores();
      const speakingProfile = this.computeSpeakingProfile(legacySkillScores);
      const skillScores = Object.assign({}, legacySkillScores, {
        speaking: speakingProfile.score
      });
      const weightedScore = Object.entries(SKILL_WEIGHTS).reduce((sum, [skill, weight]) => {
        return sum + (skillScores[skill] || 0) * weight;
      }, 0);
      const adaptiveLevelScore = LEVEL_SCORE[this.getCurrentLevel()] || weightedScore;
      const overallScore = weightedScore * 0.86 + adaptiveLevelScore * 0.14;
      const overallCefr = scoreToPlacementBand(overallScore);
      const skillLevels = {
        listening: scoreToPlacementBand(skillScores.listening || 0),
        speaking: scoreToPlacementBand(skillScores.speaking || 0),
        reading: scoreToPlacementBand(skillScores.reading || 0),
        writing: scoreToPlacementBand(skillScores.writing || 0)
      };
      const skillProfile = Object.fromEntries(
        Object.entries(skillScores).map(([skill, score]) => [skill, scoreToPlacementBand(score)])
      );
      const kpMastery = this.computeKpMastery(skillScores, speakingProfile);
      const startUnit = cefrBandToUnit(overallCefr);
      const repairUnits = this.buildRepairUnits(kpMastery, startUnit);
      const placementConfidence = this.computeConfidenceScore(skillScores, speakingProfile);
      const supportFlags = this.buildSupportFlags(skillScores, speakingProfile, placementConfidence);
      const placementStatus = this.buildPlacementStatus(placementConfidence, supportFlags, skillScores, speakingProfile);
      const weakSkillWarnings = this.buildWeakSkillWarnings(skillScores);
      const recommendedCourse = this.chooseCourse(overallCefr);
      const rawScore = Math.round(weightedScore);
      const maxScore = 100;
      const learningPath = {
        start_mode: placementStatus === "teacher_review_required"
          ? "teacher_review_first"
          : (repairUnits.required_repair_units.length ? "main_path_with_repair" : (repairUnits.optional_review_units.length ? "review_path" : "main_path")),
        required_repair_units: repairUnits.required_repair_units,
        optional_review_units: repairUnits.optional_review_units,
        locked_until_checkpoint: placementStatus === "teacher_review_required"
      };
      const result = {
        placement_date: new Date().toISOString(),
        course: courseIdFromProfile(this.state.learnerProfile || {}),
        overall_cefr: overallCefr,
        recommended_start_unit: startUnit,
        next_checkpoint_unit: nextCheckpointUnit(startUnit),
        placement_confidence: roundDecimal(placementConfidence / 100, 2),
        placement_status: placementStatus,
        skill_levels: skillLevels,
        kp_mastery: kpMastery,
        support_flags: supportFlags,
        speaking_profile: {
          pronunciation_accuracy: speakingProfile.pronunciation_accuracy,
          fluency: speakingProfile.fluency,
          sentence_completion: speakingProfile.sentence_completion,
          stt_confidence: speakingProfile.stt_confidence,
          stt_reliability: speakingProfile.stt_reliability,
          needs_voice_repair: speakingProfile.needs_voice_repair
        },
        learning_path: learningPath,
        audit: {
          raw_score: rawScore,
          max_score: maxScore,
          answered_items: this.getAllObjectiveAnswers().length,
          placement_algorithm_version: PLACEMENT_ALGORITHM_VERSION
        },

        cefr_level: overallCefr,
        skill_profile: skillProfile,
        recommended_course: recommendedCourse,
        starting_unit: startUnit,
        confidence_score: placementConfidence,
        weighted_score: rawScore,
        skill_percentages: skillScores,
        weak_skill_warnings: weakSkillWarnings,
        strong_areas: Object.entries(skillScores).filter((entry) => entry[1] >= placementBandToScore(overallCefr)).map(([skill]) => skill),
        repair_areas: Object.entries(supportFlags).filter(([key, value]) => value && key !== "teacher_review_recommended").map(([key]) => key),
        study_recommendation: this.buildStudyRecommendation(overallCefr, weakSkillWarnings, supportFlags),
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
          learning_path_ready: true,
          learner_profile_update_ready: true
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
    scoreToPlacementBand,
    levelToPlacementBand,
    cefrToUnit,
    cefrBandToUnit,
    evaluateWriting,
    STORAGE_KEY,
    PLACEMENT_ALGORITHM_VERSION
  };
})();
