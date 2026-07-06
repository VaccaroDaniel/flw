const repairBank = {
  "U1-RP-GREETING": "Look at the wave card. Listen: “Hello.” Repeat: “Hello.”",
  "U1-RP-IM-NAME": "Put I’m before the name. Say: “I’m Leo.”",
  "U1-RP-QA-ORDER": "Put the question first: “What’s your name?” Then answer: “I’m ___.”",
  "U1-RP-HELLO-GOODBYE": "Meet = hello/hi. Leave = goodbye/bye. Sort the scene cards.",
  "U1-RP-CHARACTER-NAME": "Match the name card to the character picture. Try again.",
  "U1-RP-NAME-CARD": "Add your name and I’m ___ to the card.",
  "U1-RP-FINAL-GREETING": "Use three cards: Hello → I’m ___ → Goodbye. Record again.",
  "U1-RP-WATCH-HELLO-GATE": "Watch again. Listen for Hello, I’m, and Goodbye.",
  "U1-RP-PROJECT-READY": "Check the project list: voice greeting + name card. Fix the missing part."
};
const PAGE_SIZE = 3;
const audioAliases = {
  'WORD-hello': 'AEW2-U001-WORD-HELLO',
  'WORD-hi': 'AEW2-U001-WORD-HI',
  'WORD-I’m': 'AEW2-U001-WORD-I-M',
  'WORD-name': 'AEW2-U001-WORD-NAME',
  'WORD-What’s your name?': 'AEW2-U001-WORD-WHAT-S-YOUR-NAME',
  'WORD-goodbye': 'AEW2-U001-WORD-GOODBYE',
  'WORD-bye': 'AEW2-U001-WORD-BYE',
  'WORD-Leo': 'AEW2-U001-WORD-LEO',
  'WORD-Mia': 'AEW2-U001-WORD-MIA',
  'WORD-Emma': 'AEW2-U001-WORD-EMMA',
  'WORD-Toto': 'AEW2-U001-WORD-TOTO'
};
let flwAudio = null;
let flwAudioButton = null;
function audioPlaceholder(id) {
  const audioId = audioAliases[id] || id;
  const src = `https://192.168.129.79/flwcontent/english/adventure_v2/unit001_native_test/assets/audio/${audioId}.mp3`;
  const trigger = window.event && window.event.currentTarget ? window.event.currentTarget : null;
  if (flwAudio && flwAudio.dataset && flwAudio.dataset.src === src && !flwAudio.paused) {
    flwAudio.pause();
    if (flwAudioButton) flwAudioButton.classList.remove('is-playing');
    return;
  }
  if (flwAudio) flwAudio.pause();
  if (flwAudioButton) flwAudioButton.classList.remove('is-playing');
  flwAudio = new Audio(src);
  flwAudio.dataset.src = src;
  flwAudioButton = trigger;
  if (flwAudioButton) flwAudioButton.classList.add('is-playing');
  flwAudio.addEventListener('ended', () => {
    if (flwAudioButton) flwAudioButton.classList.remove('is-playing');
  }, { once: true });
  flwAudio.addEventListener('error', () => {
    if (flwAudioButton) flwAudioButton.classList.remove('is-playing');
    alert(`Audio file is not ready: ${src}`);
  }, { once: true });
  flwAudio.play().catch(() => {
    if (flwAudioButton) flwAudioButton.classList.remove('is-playing');
    alert(`Your browser blocked audio playback for: ${audioId}`);
  });
}
function aiPlaceholder(label) {
  alert(label + '\n\nThis is a student-facing placeholder for future AI speaking/writing feedback.');
}
function getItems(block) {
  return Array.from(block.querySelectorAll('.practice-item'));
}
function getCurrentPage(block) {
  const page = parseInt(block.dataset.page || '0', 10);
  return Number.isFinite(page) ? page : 0;
}
function setCurrentPage(block, page) {
  const items = getItems(block);
  const pages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
  block.dataset.page = String(Math.max(0, Math.min(page, pages - 1)));
  renderPracticePage(block);
}
function renderPracticePage(block) {
  const items = getItems(block);
  const page = getCurrentPage(block);
  const pages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
  items.forEach((item, index) => {
    const visible = index >= page * PAGE_SIZE && index < (page + 1) * PAGE_SIZE;
    item.classList.toggle('practice-hidden', !visible);
  });
  const indicator = block.querySelector('.practice-page-indicator');
  if (indicator) indicator.textContent = `Practice page ${page + 1} / ${pages}`;
  const prev = block.querySelector('[data-action="prev-page"]');
  const next = block.querySelector('[data-action="next-page"]');
  if (prev) prev.disabled = page === 0;
  if (next) next.disabled = page >= pages - 1;
}
function initPracticeBlock(block) {
  if (block.dataset.pagedReady === '1') return;
  block.dataset.pagedReady = '1';
  block.dataset.page = '0';
  const note = document.createElement('p');
  note.className = 'practice-mini-note';
  note.textContent = 'This Practice shows 3 questions at a time. Check the page, then use Next Page.';
  const head = block.querySelector('.section-head');
  if (head) head.insertAdjacentElement('afterend', note);
  const pager = document.createElement('div');
  pager.className = 'practice-pager';
  pager.innerHTML = `
    <button type="button" class="secondary" data-action="prev-page">Previous</button>
    <button type="button" data-action="check-page">Check Page</button>
    <span class="practice-page-indicator">Practice page 1 / 1</span>
    <button type="button" class="secondary" data-action="reset-page">Reset Page</button>
    <button type="button" data-action="next-page">Next Page</button>`;
  block.appendChild(pager);
  block.addEventListener('click', event => {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;
    const action = actionButton.dataset.action;
    if (action === 'prev-page') setCurrentPage(block, getCurrentPage(block) - 1);
    if (action === 'next-page') setCurrentPage(block, getCurrentPage(block) + 1);
    if (action === 'check-page') checkCurrentPage(block);
    if (action === 'reset-page') resetCurrentPage(block);
  });
  block.addEventListener('click', event => {
    const retry = event.target.closest('.retry-one');
    if (retry) {
      const item = retry.closest('.practice-item');
      if (item) resetItem(item);
      return;
    }
    const show = event.target.closest('.show-answer');
    if (show) {
      const item = show.closest('.practice-item');
      if (item) showCorrectAnswer(item);
    }
  });
  block.addEventListener('change', event => {
    if (event.target.matches('input[type="radio"]')) {
      const item = event.target.closest('.practice-item');
      if (item && item.dataset.done !== '1') {
        const feedback = item.querySelector('.feedback');
        if (feedback) { feedback.textContent = ''; feedback.className = 'feedback'; }
      }
    }
  });
  renderPracticePage(block);
}
function visibleItems(block) {
  return getItems(block).filter(item => !item.classList.contains('practice-hidden'));
}
function selectedInput(item) {
  return item.querySelector('input[type="radio"]:checked');
}
function answerText(item, value) {
  const input = item.querySelector(`input[type="radio"][value="${CSS.escape(value)}"]`);
  if (!input) return value;
  const label = input.closest('label');
  return label ? label.innerText.trim() : value;
}
function checkItem(item) {
  const correct = item.dataset.correct;
  const selected = selectedInput(item);
  const feedback = item.querySelector('.feedback');
  if (!feedback) return;
  feedback.className = 'feedback';
  if (!selected) {
    feedback.innerHTML = 'Choose one answer.';
    feedback.classList.add('no');
    item.dataset.done = '0';
    return;
  }
  if (selected.value === correct) {
    feedback.innerHTML = '✓ Good!';
    feedback.classList.add('ok');
    item.dataset.done = '1';
  } else {
    const repair = repairBank[item.dataset.repair] || 'Try again.';
    feedback.innerHTML = `Try again. ${repair}<div class="repair-tools"><button type="button" class="retry-one">Try again</button><button type="button" class="show-answer">Correct Answer</button></div>`;
    feedback.classList.add('no');
    item.dataset.done = '0';
  }
}
function checkCurrentPage(block) {
  visibleItems(block).forEach(checkItem);
  updateProgress();
}
function resetItem(item) {
  item.querySelectorAll('input[type="radio"]').forEach(input => { input.checked = false; });
  const feedback = item.querySelector('.feedback');
  if (feedback) { feedback.textContent = ''; feedback.className = 'feedback'; }
  item.dataset.done = '0';
  updateProgress();
}
function resetCurrentPage(block) {
  visibleItems(block).forEach(resetItem);
}
function showCorrectAnswer(item) {
  const correct = item.dataset.correct;
  const feedback = item.querySelector('.feedback');
  if (!feedback) return;
  feedback.className = 'feedback no';
  feedback.innerHTML = `Correct Answer: ${answerText(item, correct)}<div class="repair-tools"><button type="button" class="retry-one">Try again</button></div>`;
}
function updateProgress() {
  const total = document.querySelectorAll('.practice-item').length;
  const done = Array.from(document.querySelectorAll('.practice-item')).filter(i => i.dataset.done === '1').length;
  const progress = document.getElementById('progress');
  if (progress) progress.textContent = 'Practice: ' + done + ' / ' + total;
}
function openLightbox(src, alt) {
  const box = document.getElementById('lightbox');
  const img = document.getElementById('lightbox-img');
  img.src = src; img.alt = alt || '';
  box.style.display = 'flex';
}
function closeLightbox() {
  document.getElementById('lightbox').style.display = 'none';
}
function openHashDetails() {
  if (!location.hash) return;
  const el = document.querySelector(location.hash);
  if (el && el.tagName && el.tagName.toLowerCase() === 'details') el.open = true;
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeLightbox(); });
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.practice-block').forEach(initPracticeBlock);
  openHashDetails();
  updateProgress();
});
window.addEventListener('hashchange', openHashDetails);

function initVideoPosters() {
  document.querySelectorAll('.flw-native .video-card video[poster]').forEach(video => {
    if (video.dataset.posterReady === '1') return;
    video.dataset.posterReady = '1';
    const card = video.closest('.video-card');
    if (!card) return;
    let shell = video.closest('.flw-video-shell');
    if (!shell) {
      shell = document.createElement('div');
      shell.className = 'flw-video-shell';
      video.parentNode.insertBefore(shell, video);
      shell.appendChild(video);
    }
    const poster = document.createElement('button');
    poster.type = 'button';
    poster.className = 'flw-video-poster';
    poster.setAttribute('aria-label', video.getAttribute('aria-label') || 'Play video');
    const image = document.createElement('img');
    image.src = video.getAttribute('poster');
    image.alt = '';
    poster.appendChild(image);
    shell.appendChild(poster);
    const hidePoster = () => {
      card.classList.add('is-started', 'is-playing');
    };
    poster.addEventListener('click', () => {
      hidePoster();
      video.play().catch(() => {
        card.classList.remove('is-playing');
      });
    });
    video.addEventListener('play', hidePoster);
    video.addEventListener('pause', () => card.classList.remove('is-playing'));
    video.addEventListener('ended', () => card.classList.remove('is-playing'));
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initVideoPosters);
} else {
  initVideoPosters();
}
(() => {
  const hiddenLabelCmIds = [453,454,455,456,457,458,459,460,461,462,463,464,465];
  const tidyCourseIndex = () => {
    hiddenLabelCmIds.forEach((cmid) => {
      const item = document.getElementById(`course-index-cm-${cmid}`);
      if (item) {
        item.remove();
      }
    });
    document.querySelectorAll('#course-index .courseindex-section').forEach((section) => {
      const content = section.querySelector('.courseindex-sectioncontent');
      if (!content) {
        return;
      }
      if (!content.querySelector('li.courseindex-item')) {
        content.classList.remove('show');
        content.setAttribute('hidden', 'hidden');
        const chevron = section.querySelector('.courseindex-chevron');
        if (chevron) {
          chevron.remove();
        }
      }
    });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tidyCourseIndex);
  } else {
    tidyCourseIndex();
  }
  const observer = new MutationObserver(tidyCourseIndex);
  observer.observe(document.documentElement, {childList: true, subtree: true});
})();