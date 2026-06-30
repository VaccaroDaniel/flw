(function () {
  "use strict";

  const BLOCKED_IDS = new Set([
    "overview",
    "unit-map",
    "map",
    "story",
    "unit-story",
    "unit-story-reading",
    "pronunciation",
    "teacher-notes",
    "methodology",
    "certificate",
    "checkpoints"
  ]);

  function direct(root, selector) {
    try {
      return root.querySelector(selector);
    } catch (_) {
      return null;
    }
  }

  function textOf(node) {
    return (node && node.textContent ? node.textContent : "").replace(/\s+/g, " ").trim();
  }

  function isLessonId(id) {
    return /^(lesson-?\d+|l-?\d+|img\d+)$/i.test(id || "");
  }

  function isCandidate(element) {
    if (!element || element.dataset.flwFolded === "true") return false;
    if (element.closest("details.flw-folding-lesson")) return false;
    const id = element.id || "";
    if (BLOCKED_IDS.has(id)) return false;
    if (element.matches("details.lesson, details.section")) return true;
    if (element.classList.contains("lesson")) return true;
    return isLessonId(id) && /^(SECTION|ARTICLE|DIV)$/i.test(element.tagName);
  }

  function findTitle(element, index) {
    const heading = direct(element, ":scope > .lesson-head h1, :scope > .lesson-head h2, :scope > .lesson-head h3, :scope > header h1, :scope > header h2, :scope > header h3, :scope > h1, :scope > h2, :scope > h3")
      || element.querySelector("h1, h2, h3");
    const title = textOf(heading) || `Lesson ${index + 1}`;
    const subtitle = textOf(direct(element, ":scope > .lesson-head .kp, :scope > .lesson-head .goal, :scope > .can-do, :scope > .goal, :scope > .lesson-num"));
    return { heading, title, subtitle };
  }

  function buildSummary(title, subtitle, index) {
    const summary = document.createElement("summary");
    const badge = document.createElement("span");
    const titleSpan = document.createElement("span");
    badge.className = "flw-folding-index";
    badge.textContent = String(index + 1);
    titleSpan.className = "flw-folding-title";
    titleSpan.textContent = title;
    summary.append(badge, titleSpan);
    if (subtitle && subtitle !== title) {
      const sub = document.createElement("span");
      sub.className = "flw-folding-subtitle";
      sub.textContent = subtitle;
      summary.appendChild(sub);
    }
    return summary;
  }

  function wrapDetailsContent(details) {
    if (direct(details, ":scope > .flw-folding-content")) return;
    const summary = direct(details, ":scope > summary");
    if (!summary) return;
    const content = document.createElement("div");
    content.className = "flw-folding-content";
    Array.from(details.childNodes).forEach((node) => {
      if (node !== summary) content.appendChild(node);
    });
    details.appendChild(content);
  }

  function upgradeDetails(details, index) {
    details.classList.add("flw-folding-lesson");
    details.dataset.flwFolded = "true";
    if (!direct(details, ":scope > summary")) {
      const info = findTitle(details, index);
      details.insertBefore(buildSummary(info.title, info.subtitle, index), details.firstChild);
    }
    wrapDetailsContent(details);
    if (index === 0 && !location.hash) details.open = true;
  }

  function transformElement(element, index) {
    const info = findTitle(element, index);
    const details = document.createElement("details");
    Array.from(element.attributes).forEach((attr) => details.setAttribute(attr.name, attr.value));
    details.classList.add("flw-folding-lesson");
    details.dataset.flwFolded = "true";
    if (index === 0 && !location.hash) details.open = true;

    if (info.heading && info.heading.parentElement === element) {
      info.heading.classList.add("flw-folded-title");
    }

    const content = document.createElement("div");
    content.className = "flw-folding-content";
    while (element.firstChild) content.appendChild(element.firstChild);
    details.append(buildSummary(info.title, info.subtitle, index), content);
    element.replaceWith(details);
  }

  function openHashTarget() {
    document.querySelectorAll("details.flw-folding-lesson.flw-hash-open").forEach((item) => {
      item.classList.remove("flw-hash-open");
    });
    if (!location.hash || location.hash.length < 2) return;
    const id = decodeURIComponent(location.hash.slice(1));
    const target = document.getElementById(id);
    const panel = target && target.closest("details.flw-folding-lesson");
    if (!panel) return;
    panel.open = true;
    panel.classList.add("flw-hash-open");
    window.setTimeout(() => panel.scrollIntoView({ block: "start", behavior: "smooth" }), 30);
  }

  function init() {
    if (document.body && document.body.dataset.flwFolding === "off") return;
    const elements = Array.from(document.querySelectorAll("details.lesson, details.section, section.lesson, article.lesson, div.lesson, section[id^='lesson'], section[id^='l'], section[id^='img'], article[id^='lesson'], div[id^='lesson']"))
      .filter(isCandidate);
    let foldedIndex = 0;
    elements.forEach((element) => {
      if (!isCandidate(element)) return;
      const index = foldedIndex;
      foldedIndex += 1;
      if (element.tagName.toLowerCase() === "details") upgradeDetails(element, index);
      else transformElement(element, index);
    });
    openHashTarget();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
  window.addEventListener("hashchange", openHashTarget);
})();
