<?php
// =============================================================
// UMKC VSA - Shared dark-mode head partial
// Include inside <head> on every dashboard page.
// Provides: early-load theme script, dark-mode CSS variables,
// menu-link dark styling, hamburger visibility fix, and the
// Dark Mode toggle switch styling.
// =============================================================
?>
<script>(function(){try{if(localStorage.getItem('vsa-theme')==='dark'){document.documentElement.classList.add('dark-mode');}}catch(e){}})();</script>
<script>(function(){try{window.addEventListener('storage',function(e){if(e.key==='vsa-theme'){var d=document.documentElement.classList;if(e.newValue==='dark'){d.add('dark-mode');}else{d.remove('dark-mode');}}});}catch(e){}})();</script>
<style>
  html.dark-mode {
    --navy: #16314d;
    --red: #e2554f;
    --light: #0f1a26;
    --text: #e6edf3;
    --muted: #9fb0c0;
    --white: #16222f;
  }
  html.dark-mode body { background: var(--light); color: var(--text); }
  html.dark-mode .menu-link { color: #e6edf3; }
  html.dark-mode .menu-link:hover { background: rgba(255,255,255,0.06); }
  /* Hamburger / X icon visible in dark mode */
  html.dark-mode .menu-toggle span { background: #e6edf3; }
  /* Dark Mode toggle switch (inline in the left menu) */
  .theme-row { display: flex; align-items: center; justify-content: space-between; cursor: default; }
  .theme-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex: none; }
  .theme-switch input { opacity: 0; width: 0; height: 0; }
  .theme-slider { position: absolute; cursor: pointer; inset: 0; background: rgba(255,255,255,0.25); border-radius: 22px; transition: background .2s ease; }
  .theme-slider:before { content: ""; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: transform .2s ease; }
  .theme-switch input:checked + .theme-slider { background: var(--red); }
  .theme-switch input:checked + .theme-slider:before { transform: translateX(20px); }
  .topbar { position:relative; }
  .topbar::after {
    content:''; position:absolute; left:0; right:0; bottom:-2px; height:2px; z-index:5;
    background:linear-gradient(90deg, #1f6feb 0%, #2f9bff 25%, #7fdcff 50%, #2f9bff 75%, #1f6feb 100%);
    background-size:200% 100%;
    box-shadow:0 0 6px rgba(47,155,255,.7), 0 0 12px rgba(127,220,255,.4);
    animation:topbarNeon 6s linear infinite;
  }
  @keyframes topbarNeon { 0% { background-position:0% 50%; } 100% { background-position:200% 50%; } }
  @media (prefers-reduced-motion: reduce) { .topbar::after { animation:none; } }
</style>
