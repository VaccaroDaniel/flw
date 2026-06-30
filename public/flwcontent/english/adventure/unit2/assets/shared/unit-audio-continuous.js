(function () {
  const LABELS = [
    /useful\s+sentence\s+patterns?/i,
    /useful\s+sentence\s+models?/i,
    /useful\s+expressions?/i,
    /useful\s+phrases?/i,
    /model\s+dialog(?:ue)?/i,
    /model\s+conversation/i
  ];
  const AUDIO_SELECTOR = [
    ".sound-icon[data-audio-src]",
    ".sound-icon[data-audio]",
    "[data-audio-src]",
    "[data-audio]"
  ].join(",");
  const blockAudio = new Audio();
  const pronunciationAudio = new Audio();
  const storyAudio = new Audio();
  const clickableWordAudio = new Audio();
  let queue = [];
  let queueButton = null;
  let queueIndex = 0;

  function textOf(node) {
    return (node?.textContent || "").replace(/\s+/g, " ").trim();
  }

  function matchesHeading(node) {
    return node && /^H[1-6]$/i.test(node.tagName) && LABELS.some((pattern) => pattern.test(textOf(node)));
  }

  function audioSrcFrom(node) {
    return node?.dataset?.audioSrc || node?.dataset?.audio || "";
  }

  function absoluteAudioSrc(src) {
    const link = document.createElement("a");
    link.href = src || "";
    return link.href;
  }

  function uniqueAudioItems(block) {
    const seen = new Set();
    return Array.from(block.querySelectorAll(AUDIO_SELECTOR))
      .map((node) => {
        const src = audioSrcFrom(node);
        if (!src || seen.has(src)) return null;
        seen.add(src);
        return {
          src,
          text: node.dataset.audioText || textOf(node).replace("🔊", "").trim()
        };
      })
      .filter(Boolean);
  }

  function nextContentBlock(heading) {
    let block = heading.nextElementSibling;
    while (block && !block.querySelector(AUDIO_SELECTOR)) {
      if (matchesHeading(block)) return null;
      block = block.nextElementSibling;
    }
    return block;
  }

  function resetButton() {
    if (queueButton) {
      queueButton.classList.remove("playing", "error");
      queueButton.setAttribute("aria-pressed", "false");
    }
    queueButton = null;
    queue = [];
    queueIndex = 0;
  }

  function playCurrent() {
    const item = queue[queueIndex];
    if (!item) {
      resetButton();
      return;
    }
    blockAudio.src = item.src;
    blockAudio.play().catch(() => {
      if (queueButton) {
        queueButton.classList.remove("playing");
        queueButton.classList.add("error");
      }
      resetButton();
    });
  }

  function toggleQueue(button, items) {
    if (queueButton === button && button.classList.contains("playing")) {
      blockAudio.pause();
      blockAudio.currentTime = 0;
      resetButton();
      return;
    }
    blockAudio.pause();
    blockAudio.currentTime = 0;
    resetButton();
    queueButton = button;
    queue = items;
    queueIndex = 0;
    button.classList.add("playing");
    button.setAttribute("aria-pressed", "true");
    playCurrent();
  }

  function addButton(heading, items) {
    if (!items.length || heading.querySelector(".flw-play-all-audio")) return;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "flw-play-all-audio";
    button.textContent = "Play";
    button.title = "Play this whole section";
    button.setAttribute("aria-label", `Play all: ${textOf(heading)}`);
    button.setAttribute("aria-pressed", "false");
    button.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      toggleQueue(button, items);
    });
    heading.appendChild(button);
  }

  function enhance() {
    document.querySelectorAll(".story-play-all-button[data-story-audio-src]").forEach((button) => {
      if (button.dataset.flwStoryReady === "true") return;
      button.dataset.flwStoryReady = "true";
      button.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const src = button.dataset.storyAudioSrc || "";
        const requestedSrc = src ? absoluteAudioSrc(src) : "";
        if (requestedSrc && storyAudio.src === requestedSrc && !storyAudio.paused) {
          storyAudio.pause();
          storyAudio.currentTime = 0;
          button.classList.remove("playing");
          return;
        }
        storyAudio.pause();
        storyAudio.currentTime = 0;
        document.querySelectorAll(".story-play-all-button.playing,.story-play-all-button.error")
          .forEach((node) => node.classList.remove("playing", "error"));
        if (!src) {
          button.classList.add("error");
          return;
        }
        button.classList.add("playing");
        storyAudio.src = requestedSrc;
        storyAudio.play().catch(() => {
          button.classList.remove("playing");
          button.classList.add("error");
        });
      });
    });
    document.querySelectorAll(".clickable-audio-word[data-audio-src],.clickable-audio-word[data-audio]").forEach((item) => {
      if (item.dataset.flwClickableAudioReady === "true") return;
      item.dataset.flwClickableAudioReady = "true";
      item.setAttribute("role", "button");
      item.setAttribute("tabindex", "0");
      const play = () => {
        const src = item.dataset.audioSrc || item.dataset.audio || "";
        const requestedSrc = src ? absoluteAudioSrc(src) : "";
        if (requestedSrc && clickableWordAudio.src === requestedSrc && !clickableWordAudio.paused) {
          clickableWordAudio.pause();
          clickableWordAudio.currentTime = 0;
          item.classList.remove("playing");
          return;
        }
        clickableWordAudio.pause();
        clickableWordAudio.currentTime = 0;
        document.querySelectorAll(".clickable-audio-word.playing,.clickable-audio-word.error")
          .forEach((node) => node.classList.remove("playing", "error"));
        if (!src) {
          item.classList.add("error");
          return;
        }
        item.classList.add("playing");
        clickableWordAudio.src = requestedSrc;
        clickableWordAudio.play().catch(() => {
          item.classList.remove("playing");
          item.classList.add("error");
        });
      };
      item.addEventListener("click", (event) => {
        if (event.target.closest(".speaker-button,.sound-icon,.pronunciation-sound-button")) return;
        event.preventDefault();
        play();
      });
      item.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        event.preventDefault();
        play();
      });
    });
    document.querySelectorAll(".pronunciation-listen-item[data-pron-audio-src]").forEach((item) => {
      if (item.querySelector(".pronunciation-sound-button")) return;
      const button = document.createElement("button");
      button.type = "button";
      button.className = "pronunciation-sound-button";
      button.innerHTML = "&#9654;";
      const text = item.dataset.pronAudioText || textOf(item);
      button.title = `Play: ${text}`;
      button.setAttribute("aria-label", `Play pronunciation: ${text}`);
      button.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const src = item.dataset.pronAudioSrc || "";
        const requestedSrc = src ? absoluteAudioSrc(src) : "";
        if (requestedSrc && pronunciationAudio.src === requestedSrc && !pronunciationAudio.paused) {
          pronunciationAudio.pause();
          pronunciationAudio.currentTime = 0;
          button.classList.remove("playing");
          return;
        }
        pronunciationAudio.pause();
        pronunciationAudio.currentTime = 0;
        document.querySelectorAll(".pronunciation-sound-button.playing,.pronunciation-sound-button.error")
          .forEach((node) => node.classList.remove("playing", "error"));
        button.classList.add("playing");
        pronunciationAudio.src = requestedSrc;
        pronunciationAudio.play().catch(() => {
          button.classList.remove("playing");
          button.classList.add("error");
        });
      });
      item.prepend(button);
    });
    document.querySelectorAll("h1,h2,h3,h4,h5,h6").forEach((heading) => {
      if (!matchesHeading(heading)) return;
      const block = nextContentBlock(heading);
      if (!block) return;
      const items = uniqueAudioItems(block);
      addButton(heading, items);
    });
  }

  blockAudio.addEventListener("ended", () => {
    queueIndex += 1;
    if (queueIndex < queue.length) {
      window.setTimeout(playCurrent, 350);
    } else {
      resetButton();
    }
  });
  pronunciationAudio.addEventListener("ended", () => {
    document.querySelectorAll(".pronunciation-sound-button.playing")
      .forEach((node) => node.classList.remove("playing"));
  });
  storyAudio.addEventListener("ended", () => {
    document.querySelectorAll(".story-play-all-button.playing")
      .forEach((node) => node.classList.remove("playing"));
  });
  clickableWordAudio.addEventListener("ended", () => {
    document.querySelectorAll(".clickable-audio-word.playing")
      .forEach((node) => node.classList.remove("playing"));
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => window.setTimeout(enhance, 80));
  } else {
    window.setTimeout(enhance, 80);
  }
  window.addEventListener("load", () => window.setTimeout(enhance, 250));
})();
