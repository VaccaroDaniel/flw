(() => {
  const unitAudioPlayer = new Audio();
  const songAudioPlayer = new Audio();
  const speakerIcon = `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4z"></path><path d="M16 8.5a4 4 0 0 1 0 7"></path><path d="M18.5 6a7 7 0 0 1 0 12"></path></svg>`;
  function absoluteAudioSrc(src) {
    const link = document.createElement("a");
    link.href = src || "";
    return link.href;
  }
  function clearPlayingButtons() {
    document.querySelectorAll(".speaker-button.playing").forEach(button => button.classList.remove("playing"));
  }
  function clearSongButtons() {
    document.querySelectorAll(".music-note-button.playing").forEach(button => button.classList.remove("playing"));
  }
  document.querySelectorAll("[data-audio-src]").forEach(item => {
    const button = document.createElement("button");
    const text = item.dataset.audioText || item.textContent.trim();
    const character = item.dataset.audioCharacter || "FLW";
    button.type = "button";
    button.className = "speaker-button";
    button.innerHTML = speakerIcon;
    button.setAttribute("aria-label", `Play ${character}: ${text}`);
    button.title = `Play ${character}`;
    button.addEventListener("click", event => {
      event.preventDefault();
      event.stopPropagation();
      const requestedSrc = absoluteAudioSrc(item.dataset.audioSrc);
      const isSameAudio = unitAudioPlayer.src === requestedSrc && !unitAudioPlayer.paused;
      if (isSameAudio) {
        unitAudioPlayer.pause();
        unitAudioPlayer.currentTime = 0;
        button.classList.remove("playing");
        return;
      }
      clearPlayingButtons();
      clearSongButtons();
      button.classList.add("playing");
      songAudioPlayer.pause();
      songAudioPlayer.currentTime = 0;
      unitAudioPlayer.pause();
      unitAudioPlayer.currentTime = 0;
      unitAudioPlayer.src = requestedSrc;
      unitAudioPlayer.play().catch(() => {
        button.classList.remove("playing");
      });
    });
    item.classList.add("has-audio");
    item.appendChild(button);
  });
  document.querySelectorAll("[data-song-src]").forEach(button => {
    button.addEventListener("click", event => {
      event.preventDefault();
      event.stopPropagation();
      const requestedSrc = absoluteAudioSrc(button.dataset.songSrc);
      const shouldRestart = songAudioPlayer.src !== requestedSrc || songAudioPlayer.paused;
      clearPlayingButtons();
      clearSongButtons();
      unitAudioPlayer.pause();
      unitAudioPlayer.currentTime = 0;
      if (!shouldRestart) {
        songAudioPlayer.pause();
        songAudioPlayer.currentTime = 0;
        return;
      }
      button.classList.add("playing");
      songAudioPlayer.pause();
      songAudioPlayer.currentTime = 0;
      songAudioPlayer.src = requestedSrc;
      songAudioPlayer.play().catch(() => {
        button.classList.remove("playing");
      });
    });
  });
  unitAudioPlayer.addEventListener("ended", clearPlayingButtons);
  songAudioPlayer.addEventListener("ended", clearSongButtons);
  unitAudioPlayer.addEventListener("pause", () => {
    if (unitAudioPlayer.ended) clearPlayingButtons();
  });
  songAudioPlayer.addEventListener("pause", () => {
    if (songAudioPlayer.ended) clearSongButtons();
  });
})();

document.querySelectorAll('.answer-btn').forEach(btn=>btn.addEventListener('click',()=>{const box=btn.closest('.activity');box.querySelectorAll('.answer-btn').forEach(b=>b.classList.remove('correct','wrong'));const ok=btn.dataset.correct==='true';btn.classList.add(ok?'correct':'wrong');box.querySelector('.feedback').textContent=ok?'Correct! Great job!':'Try again. Listen, look, and choose.';box.querySelector('.feedback').className='feedback '+(ok?'ok':'bad');}));
document.querySelectorAll('.check-fill').forEach(btn=>btn.addEventListener('click',()=>{const box=btn.closest('.activity');const normalizeAnswer=(text)=>text.trim().toLowerCase().replace(/['’]/g,'').replace(/[.!?]/g,'').replace(/\s+/g,' ');const val=normalizeAnswer(box.querySelector('input').value);const answers=JSON.parse(box.dataset.answers);const ok=answers.some(a=>val===normalizeAnswer(a));box.querySelector('.feedback').textContent=ok?'Correct!':'Try again. Check the words.';box.querySelector('.feedback').className='feedback '+(ok?'ok':'bad');}));
document.querySelectorAll('.check-match').forEach(btn=>btn.addEventListener('click',()=>{const box=btn.closest('.activity');let ok=true;box.querySelectorAll('select').forEach(s=>{if(s.value!==s.dataset.correct) ok=false;});box.querySelector('.feedback').textContent=ok?'Correct! All matches are right.':'Try again. Match the names and descriptions.';box.querySelector('.feedback').className='feedback '+(ok?'ok':'bad');}));


(function(){
  function stickyOffset(){
    const flw = document.querySelector('.flw-unit-topbar');
    const nav = document.querySelector('.nav');
    return (flw ? flw.getBoundingClientRect().height : 0) + (nav ? nav.getBoundingClientRect().height : 0) + 14;
  }
  function scrollToUnitHash(hash, updateHash){
    if(!hash) return;
    const target = document.querySelector(hash);
    if(!target) return;
    const y = target.getBoundingClientRect().top + window.scrollY - stickyOffset();
    window.scrollTo({ top: Math.max(0, y), behavior: 'auto' });
    target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
    if(updateHash) history.pushState(null, '', hash);
  }
  document.addEventListener('click', (event)=>{
    const link = event.target.closest('.nav a[href^="#"], .unit-map-card[href^="#"]');
    if(!link) return;
    event.preventDefault();
    scrollToUnitHash(link.getAttribute('href'), true);
  }, true);
  window.addEventListener('load', ()=>{
    if(location.hash) setTimeout(()=>scrollToUnitHash(location.hash, false), 80);
  });
  window.addEventListener('hashchange', ()=>scrollToUnitHash(location.hash, false));
})();
