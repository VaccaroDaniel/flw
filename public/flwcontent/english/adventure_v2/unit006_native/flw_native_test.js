/* Source script: flw_unit.js */

const state={};
function norm(s){return (s||'').trim().replace(/[’‘]/g,"'").replace(/[“”]/g,'"').toLowerCase();}
function currentItems(block){const sec=block.dataset.section; const page=state[sec]||0; return [...block.querySelectorAll('.qitem')].slice(page*3,page*3+3);}
function renderBlock(block){const sec=block.dataset.section; if(state[sec]===undefined) state[sec]=0; const items=[...block.querySelectorAll('.qitem')]; const max=Math.max(0,Math.ceil(items.length/3)-1); if(state[sec]>max) state[sec]=max; items.forEach((it,i)=>{it.style.display=(Math.floor(i/3)===state[sec])?'block':'none'}); block.querySelector('.practice-page').textContent=`Page ${state[sec]+1} / ${max+1}`; block.querySelector('.prev').disabled=state[sec]===0; block.querySelector('.nextp').disabled=state[sec]===max;}
function selectedAnswer(item){if(item.dataset.type==='gap_fill'){const inp=item.querySelector('input'); return inp?inp.value:'';} const checked=item.querySelector('input[type=radio]:checked'); return checked?checked.value:'';}
function checkItem(item){const ans=item.dataset.answer; const got=selectedAnswer(item); const fb=item.querySelector('.feedback'); const repair=item.querySelector('.repair-btns'); if(norm(got)===norm(ans)){fb.textContent=item.dataset.good||'Correct.'; fb.className='feedback good'; repair.classList.remove('show');}else{fb.textContent=item.dataset.bad||'Try again.'; fb.className='feedback bad'; repair.classList.add('show');}}
function resetItem(item){item.querySelectorAll('input[type=radio]').forEach(i=>i.checked=false); const t=item.querySelector('input[type=text]'); if(t) t.value=''; const fb=item.querySelector('.feedback'); fb.textContent=''; fb.className='feedback'; item.querySelector('.repair-btns').classList.remove('show');}
function initPractice(){document.querySelectorAll('.practice-block').forEach(block=>{renderBlock(block); block.querySelector('.check').addEventListener('click',()=>currentItems(block).forEach(checkItem)); block.querySelector('.reset').addEventListener('click',()=>currentItems(block).forEach(resetItem)); block.querySelector('.prev').addEventListener('click',()=>{state[block.dataset.section]--; renderBlock(block);}); block.querySelector('.nextp').addEventListener('click',()=>{state[block.dataset.section]++; renderBlock(block);});}); document.querySelectorAll('.try-again').forEach(btn=>btn.addEventListener('click',e=>resetItem(e.target.closest('.qitem')))); document.querySelectorAll('.show-answer').forEach(btn=>btn.addEventListener('click',e=>{const it=e.target.closest('.qitem'); const fb=it.querySelector('.feedback'); fb.textContent='Correct answer: '+it.dataset.answer; fb.className='feedback good';}));}
function initModal(){const modal=document.getElementById('modal'); const img=document.getElementById('modal-img'); document.querySelectorAll('.zoom-btn').forEach(btn=>btn.addEventListener('click',()=>{img.src=btn.dataset.img; img.alt=btn.dataset.alt||''; modal.classList.add('show');})); modal.querySelector('button').addEventListener('click',()=>modal.classList.remove('show')); modal.addEventListener('click',e=>{if(e.target===modal) modal.classList.remove('show');});}
document.addEventListener('DOMContentLoaded',()=>{initPractice(); initModal();});


let flwActiveModelAudio=null;
function flwPlayModelAudio(src){ if(flwActiveModelAudio){flwActiveModelAudio.pause();flwActiveModelAudio.currentTime=0;} flwActiveModelAudio=new Audio(src); flwActiveModelAudio.play().catch(()=>alert('Please click again to play audio.')); }
document.querySelectorAll('.model-audio-btn').forEach((btn)=>btn.addEventListener('click',()=>flwPlayModelAudio(btn.dataset.src)));
const FLW_AUDIO_QUEUE=["https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/01_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/02_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/03_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/04_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/05_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/06_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/07_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/08_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/09_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/10_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/11_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/12_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/13_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/14_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/15_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/16_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/17_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/18_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/19_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/20_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/21_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/22_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/23_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/24_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/25_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/26_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/27_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/28_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/29_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/30_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/31_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/32_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/33_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/34_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/35_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/36_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/37_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/38_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/39_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/40_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/41_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/42_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/43_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/44_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/45_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/46_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/47_model-line_model.mp3","https://192.168.129.79/flwcontent/english/adventure_v2/unit006_native/assets/audio/generated/48_model-line_model.mp3"];
document.querySelectorAll('.audio,.model-audio').forEach((el,i)=>{ if(!el.dataset.src && FLW_AUDIO_QUEUE[i]) el.dataset.src=FLW_AUDIO_QUEUE[i]; el.setAttribute('role','button'); el.tabIndex=0; el.style.cursor='pointer'; el.addEventListener('click',()=>{ if(el.dataset.src) flwPlayModelAudio(el.dataset.src); }); });

function initNativeImageZoom() {
  const ensureNativeImageModal = () => {
    let modal = document.getElementById('flw-native-image-modal');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'flw-native-image-modal';
    modal.className = 'flw-native image-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '<button aria-label="Close large image" class="modal-close" type="button">Close</button><div class="modal-inner"><img alt="" id="flw-native-modal-img" src=""/><div id="flw-native-modal-cap"></div></div>';
    document.body.appendChild(modal);
    const close = event => {
      if (event && event.type === 'click') {
        const target = event.target;
        if (target && target !== modal && !target.classList.contains('modal-close')) return;
      }
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    };
    modal.querySelector('.modal-close').addEventListener('click', close);
    modal.addEventListener('click', close);
    return modal;
  };
  window.openImageModal = (src, alt) => {
    const modal = ensureNativeImageModal();
    const image = modal.querySelector('#flw-native-modal-img');
    const caption = modal.querySelector('#flw-native-modal-cap');
    if (!image) return;
    image.src = src || '';
    image.alt = alt || 'large lesson image';
    if (caption) caption.textContent = alt || '';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  };
  window.closeImageModal = event => {
    const modal = ensureNativeImageModal();
    if (event && event.type === 'click') {
      const target = event.target;
      if (target && target !== modal && !target.classList.contains('modal-close')) return;
    }
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  };
  window.openModal = (src, alt) => window.openImageModal(src, alt);
  window.closeModal = event => window.closeImageModal(event);
  document.querySelectorAll('.flw-native .zoom-btn').forEach(btn => {
    if (btn.dataset.nativeZoomReady === '1') return;
    btn.dataset.nativeZoomReady = '1';
    btn.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      const image = btn.closest('.img-box, figure, .image-card, .hero-card')?.querySelector('img');
      const src = image?.currentSrc || image?.src || '';
      const alt = image?.alt || btn.getAttribute('aria-label') || '';
      if (src) window.openImageModal(src, alt);
    });
  });
  document.querySelectorAll('.flw-native .zoom[data-img]').forEach(btn => {
    if (btn.dataset.nativeZoomReady === '1') return;
    btn.dataset.nativeZoomReady = '1';
    btn.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      const modal = ensureNativeImageModal();
      let image = modal.querySelector('#flw-native-modal-img');
      let caption = modal.querySelector('#flw-native-modal-cap');
      if (!modal || !image) return;
      image.src = btn.dataset.img || '';
      image.alt = btn.dataset.title || btn.getAttribute('aria-label') || '';
      if (caption) caption.textContent = btn.dataset.title || '';
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });
  document.querySelectorAll('.flw-native .image-modal, .flw-native .modal').forEach(modal => {
    if (modal.dataset.nativeCloseReady === '1') return;
    modal.dataset.nativeCloseReady = '1';
    const close = () => {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    };
    modal.querySelectorAll('.modal-close, .close-modal').forEach(btn => {
      btn.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        close();
      });
    });
    modal.addEventListener('click', event => {
      if (event.target === modal) close();
    });
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNativeImageZoom);
} else {
  initNativeImageZoom();
}

function initVideoPosters() {
  document.querySelectorAll('.flw-native .video-card video').forEach(video => {
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
    const posterSrc = video.getAttribute('poster');
    if (!posterSrc) return;
    const poster = document.createElement('button');
    poster.type = 'button';
    poster.className = 'flw-video-poster';
    poster.setAttribute('aria-label', video.getAttribute('aria-label') || 'Play video');
    const image = document.createElement('img');
    image.src = posterSrc;
    image.alt = '';
    poster.appendChild(image);
    shell.appendChild(poster);
    const hidePoster = () => card.classList.add('is-started', 'is-playing');
    poster.addEventListener('click', () => {
      hidePoster();
      video.play().catch(() => card.classList.remove('is-playing'));
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

function initNativePracticeBlocks() {
  if (typeof initPractice !== 'function') return;
  document.querySelectorAll('.flw-native .practice[id^="practice-"]').forEach(root => {
    const sectionId = root.id.replace(/^practice-/, '');
    if (!sectionId) return;
    if (root.children.length === 0 || !root.querySelector('.practice-header')) {
      try {
        initPractice(sectionId);
      } catch (error) {
        console.warn('FLW Auto Check render failed for', sectionId, error);
        delete root.dataset.nativePracticeRendered;
        return;
      }
    }
    if (root.querySelector('.practice-header')) root.dataset.nativePracticeRendered = '1';
  });
  if (typeof updateOverallProgress === 'function') {
    updateOverallProgress();
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNativePracticeBlocks);
} else {
  initNativePracticeBlocks();
}
setTimeout(initNativePracticeBlocks, 250);
setTimeout(initNativePracticeBlocks, 1000);
setTimeout(initNativePracticeBlocks, 2000);
window.addEventListener('load', initNativePracticeBlocks);
(() => {
  const hiddenLabelCmIds = [921,922,923,924,925,926,927,928,929,930,931,932,933,934];
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