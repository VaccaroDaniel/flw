(() => {
  const unitAudioPlayer = new Audio();
  const speakerIcon = `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4z"></path><path d="M16 8.5a4 4 0 0 1 0 7"></path><path d="M18.5 6a7 7 0 0 1 0 12"></path></svg>`;
  function clearPlayingButtons() {
    document.querySelectorAll(".speaker-button.playing").forEach(button => button.classList.remove("playing"));
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
      clearPlayingButtons();
      button.classList.add("playing");
      unitAudioPlayer.pause();
      unitAudioPlayer.currentTime = 0;
      unitAudioPlayer.src = item.dataset.audioSrc;
      unitAudioPlayer.play().catch(() => {
        button.classList.remove("playing");
      });
    });
    item.classList.add("has-audio");
    item.appendChild(button);
  });
  unitAudioPlayer.addEventListener("ended", clearPlayingButtons);
  unitAudioPlayer.addEventListener("pause", () => {
    if (unitAudioPlayer.ended) clearPlayingButtons();
  });
})();

const FLW = {score:0, done:{}};
function normalize(s){return (s||'').toString().trim().toLowerCase().replace(/[.!?]/g,'').replace(/\s+/g,' ')}
function mark(id, ok, fb){ const el=document.getElementById(id); if(!el) return; el.innerHTML = ok ? `<span class="ok">✓ Correct! ${fb||''}</span>` : `<span class="no">Try again. ${fb||''}</span>`; }
function addScore(qid){ if(!FLW.done[qid]){FLW.done[qid]=true; FLW.score++; const s=document.getElementById('score'); if(s) s.textContent=FLW.score; }}
document.addEventListener('click', e=>{
  const btn=e.target.closest('.option');
  if(!btn) return;
  const q=btn.closest('.quiz'); const ok=btn.dataset.correct==='true'; const id=q.dataset.qid;
  q.querySelectorAll('.option').forEach(b=>b.classList.remove('correct','wrong'));
  btn.classList.add(ok?'correct':'wrong');
  mark('fb-'+id, ok, ok?'Good job!':'Look, listen, and choose again.');
  if(ok) addScore(id);
});
function checkFill(id){ const q=document.querySelector(`[data-qid="${id}"]`); const input=q.querySelector('input'); const ans=JSON.parse(q.dataset.answers); const ok=ans.map(normalize).includes(normalize(input.value)); mark('fb-'+id, ok, ok?'Nice sentence!':'Use one Unit 2 color word.'); if(ok) addScore(id); }
function checkOrder(id){ const q=document.querySelector(`[data-qid="${id}"]`); const input=q.querySelector('input'); const ans=q.dataset.order; const ok=normalize(input.value)===normalize(ans); mark('fb-'+id, ok, ok?'Great chant order!':'Try: red, blue, yellow, green, purple'); if(ok) addScore(id); }
function allowDrop(ev){ev.preventDefault()}
function drag(ev){ev.dataTransfer.setData('text/plain', ev.target.dataset.value)}
function drop(ev){ev.preventDefault(); const val=ev.dataTransfer.getData('text/plain'); const z=ev.currentTarget; const expected=z.dataset.accept; const ok=normalize(val)===normalize(expected); const tag=document.createElement('span'); tag.className='dragitem'; tag.textContent=val; z.appendChild(tag); z.classList.add(ok?'good':'bad'); const q=z.closest('.quiz'); const id=q.dataset.qid; if(ok){ addScore(id+'-'+expected); mark('fb-'+id, true, 'Matched: '+val+'!'); } else { mark('fb-'+id, false, 'That object belongs in another basket.'); }}


(function(){
  function stickyOffset(){
    const flw = document.querySelector('.flw-unit-topbar');
    const nav = document.querySelector('.topbar');
    return (flw ? flw.getBoundingClientRect().height : 0) + (nav ? nav.getBoundingClientRect().height : 0) + 14;
  }
  function scrollToUnitHash(hash, updateHash){
    if(!hash) return;
    const target = document.querySelector(hash);
    if(!target) return;
    const y = target.getBoundingClientRect().top + window.scrollY - stickyOffset();
    window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
    target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
    if(updateHash) history.pushState(null, '', hash);
  }
  document.querySelectorAll('a[href^="#lesson"],a[href="#unit-map"]').forEach((link)=>{
    link.addEventListener('click', (event)=>{
      event.preventDefault();
      scrollToUnitHash(link.getAttribute('href'), true);
    });
  });
  window.addEventListener('load', ()=>{
    if(location.hash) setTimeout(()=>scrollToUnitHash(location.hash, false), 80);
  });
  window.addEventListener('hashchange', ()=>scrollToUnitHash(location.hash, false));
})();
