function openImageModal(src, alt){
  const modal = document.getElementById("image-modal");
  const img = document.getElementById("modal-img");
  const cap = document.getElementById("modal-cap");
  if(!modal || !img) return;
  img.src = src;
  img.alt = alt || "large lesson image";
  if(cap) cap.textContent = alt || "";
  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");
}
function closeImageModal(event){
  const modal = document.getElementById("image-modal");
  if(!modal) return;
  if(event && event.type === "click"){
    const target = event.target;
    if(target && target.id !== "image-modal" && !target.classList.contains("modal-close")) return;
  }
  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
}
function saveFeedback(){
  const note = document.getElementById("feedback-note");
  const saved = document.getElementById("feedback-saved");
  if(!note) return;
  localStorage.setItem("flw-v2-aew-unit004-feedback", note.value || "");
  if(saved) saved.textContent = "Feedback saved on this browser.";
}
window.addEventListener("keydown", e => {
  if(e.key === "Escape") closeImageModal();
});
window.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".lesson-select a[data-open]").forEach(a => {
    a.addEventListener("click", () => {
      const id = a.getAttribute("data-open");
      const target = document.getElementById(id);
      if(target && target.tagName.toLowerCase() === "details") target.open = true;
      setTimeout(() => target && target.scrollIntoView({block:"start"}), 20);
    });
  });
  const note = document.getElementById("feedback-note");
  if(note) note.value = localStorage.getItem("flw-v2-aew-unit004-feedback") || "";
});

