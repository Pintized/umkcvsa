<?php /* UMKC VSA - Officer pop-up panel. Include before </body> on dashboard pages. */ ?>
<style>
  .officer-panel-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); opacity:0; visibility:hidden; transition:opacity .2s ease; z-index:1000; }
  .officer-panel-overlay.open { opacity:1; visibility:visible; }
  .officer-panel { position:fixed; top:0; right:0; height:100%; width:min(680px, 94vw); background:#fff; box-shadow:-4px 0 24px rgba(0,0,0,0.2); transform:translateX(100%); transition:transform .25s ease; z-index:1001; display:flex; flex-direction:column; }
  html.dark-mode .officer-panel { background:#0f1a26; }
  .officer-panel.open { transform:translateX(0); }
  .officer-panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #e5e9f0; }
  html.dark-mode .officer-panel-head { border-bottom-color:#28394a; }
  .officer-panel-title { font-weight:700; font-size:16px; color:#1f2d3d; }
  html.dark-mode .officer-panel-title { color:#e6edf3; }
  .officer-panel-close { background:none; border:none; font-size:26px; line-height:1; cursor:pointer; color:#64748b; padding:0 4px; }
  html.dark-mode .officer-panel-close { color:#9fb0c0; }
  .officer-panel-frame { flex:1; border:none; width:100%; background:transparent; }
</style>
<div class="officer-panel-overlay" id="officerPanelOverlay"></div>
<aside class="officer-panel" id="officerPanel" aria-hidden="true">
  <div class="officer-panel-head">
    <span class="officer-panel-title" id="officerPanelTitle">Officer</span>
    <button type="button" class="officer-panel-close" id="officerPanelClose" aria-label="Close">&times;</button>
  </div>
  <iframe class="officer-panel-frame" id="officerPanelFrame" title="Officer panel" src="about:blank"></iframe>
</aside>
<script>
(function(){
  var overlay=document.getElementById("officerPanelOverlay");
  var panel=document.getElementById("officerPanel");
  var frame=document.getElementById("officerPanelFrame");
  var titleEl=document.getElementById("officerPanelTitle");
  var closeBtn=document.getElementById("officerPanelClose");
  function openPanel(url,label){
    frame.src=url+(url.indexOf("?")>-1?"&":"?")+"panel=1";
    titleEl.textContent=label||"Officer";
    overlay.classList.add("open"); panel.classList.add("open"); panel.setAttribute("aria-hidden","false");
  }
  function closePanel(){
    overlay.classList.remove("open"); panel.classList.remove("open"); panel.setAttribute("aria-hidden","true");
    setTimeout(function(){ frame.src="about:blank"; }, 250);
  }
  document.querySelectorAll("[data-officer-panel]").forEach(function(a){
    a.addEventListener("click",function(e){ e.preventDefault(); openPanel(a.getAttribute("href"), a.textContent.trim()); });
  });
  overlay.addEventListener("click",closePanel);
  closeBtn.addEventListener("click",closePanel);
  document.addEventListener("keydown",function(e){ if(e.key==="Escape") closePanel(); });
})();
</script>
