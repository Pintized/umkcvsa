<?php
declare(strict_types=1);

// ============================================================
// UMKC VSA - Officer Tasks Board
// Draggable task cards with snap-to-grid, CRUD, assignees.
// Every officer may create/edit/delete/move any task.
// ============================================================

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../partials/audit.php';
require_login();
require_officer();

$user  = current_user();
$panel = isset($_GET['panel']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tasks | UMKC VSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
(function(){try{var t=localStorage.getItem('vsa-theme');if(t==='dark'){document.documentElement.classList.add('dark-mode');}}catch(e){}})();
</script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --navy:#16314d; --red:#c8202f; --light:#eef3f8; --text:#1f2933; --muted:#5b6b7b; --line:#e3e9f0; }
  body { font-family:'Source Sans 3',system-ui,sans-serif; color:var(--text); background:var(--light); min-height:100vh; }
  html.dark-mode body { background:#0f1b2a; color:#cfe0f0; }
  .wrap { max-width:1500px; margin:0 auto; padding:24px; }
  .wrap.tasks-wrap { max-width:1601px; }
  body.panel-mode { background:transparent; }
  body.panel-mode .wrap { max-width:none; margin:0; padding:18px; }
  body.panel-mode .topbar { display:none; }
  .topbar a { color:var(--red); text-decoration:none; font-size:.92rem; }
  .head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:14px 0 16px; flex-wrap:wrap; }
  .head h1 { font-size:1.6rem; }
  .head p { color:var(--muted); font-size:.9rem; margin-top:2px; }
  html.dark-mode .head p { color:#8da6c0; }
  html.dark-mode .tasks-wrap .head h1 { color:#eef4fb; }
  .btn { background:var(--red); color:#fff; border:none; border-radius:8px; padding:9px 16px; font-size:.9rem; cursor:pointer; font-weight:600; }
  .btn.secondary { background:#5b6b7b; }
  .btn.ghost { background:transparent; color:var(--muted); border:1px solid var(--line); }
  html.dark-mode .btn.ghost { color:#8da6c0; border-color:#28394a; }

  /* ---- Board with grid background (snap target) ---- */
  .board {
    position:relative; width:100%; aspect-ratio:16 / 9; max-width:1235px; overflow:hidden;
    border:1px solid var(--line); border-radius:14px;
    background-color:#f6f9fc;
    cursor:grab; touch-action:none; user-select:none; -webkit-user-select:none; -ms-user-select:none;
  }
  .board.panning { cursor:grabbing; }
  .board-canvas {
    position:absolute; top:0; left:0; width:8000px; height:8000px;
    transform-origin:0 0; will-change:transform;
    background-image:
      linear-gradient(rgba(22,49,77,0.12) 1px, transparent 1px),
      linear-gradient(90deg, rgba(22,49,77,0.12) 1px, transparent 1px);
    background-size:40px 40px;
  }
  html.dark-mode .board-canvas {
    background-image:
      linear-gradient(rgba(120,160,200,0.16) 1px, transparent 1px),
      linear-gradient(90deg, rgba(120,160,200,0.16) 1px, transparent 1px);
  }
  .zoom-controls {
    position:absolute; left:12px; bottom:12px; z-index:40;
    display:flex; align-items:center; gap:4px;
    background:rgba(255,255,255,.92); border:1px solid var(--line); border-radius:10px;
    padding:4px 8px; box-shadow:0 2px 10px rgba(16,42,67,.12); font-size:.8rem;
  }
  .zoom-controls button {
    width:26px; height:26px; border:none; background:transparent; cursor:pointer;
    font-size:1rem; line-height:1; border-radius:6px; color:inherit;
  }
  .zoom-controls button:hover { background:rgba(16,42,67,.08); }
  .zoom-controls #zoomLevel { min-width:42px; text-align:center; color:var(--muted); }
  /* search box */
  .board-search { position:absolute; right:12px; top:12px; z-index:40; width:210px; }
  .board-search input { width:100%; box-sizing:border-box; padding:7px 11px; border:1px solid var(--line); border-radius:10px; background:rgba(255,255,255,.95); font-size:.82rem; box-shadow:0 2px 10px rgba(16,42,67,.12); }
  .board-search input:focus { outline:none; border-color:#1f6feb; }
  .search-results { margin-top:4px; background:#fff; border:1px solid var(--line); border-radius:10px; box-shadow:0 6px 20px rgba(16,42,67,.16); overflow:hidden; display:none; }
  .search-results.open { display:block; }
  .search-results .sr-item { padding:7px 11px; font-size:.8rem; cursor:pointer; border-bottom:1px solid var(--line); }
  .search-results .sr-item:last-child { border-bottom:none; }
  .search-results .sr-item:hover, .search-results .sr-item.active { background:rgba(31,111,235,.10); }
  .search-results .sr-empty { padding:7px 11px; font-size:.78rem; color:var(--muted); }
  html.dark-mode .board-search input { background:#13243a; color:#cfe0f0; border-color:#2a3c50; }
  html.dark-mode .search-results { background:#1b2c40; border-color:#2a3c50; color:#cfe0f0; }
  html.dark-mode .search-results .sr-item { border-bottom-color:#2a3c50; }
  /* card highlight when found */
  .card.search-hit { box-shadow:0 0 0 3px #1f6feb, 0 12px 30px rgba(31,111,235,.35) !important; }
  /* mini-map */
  .minimap { position:absolute; right:12px; bottom:12px; z-index:40; width:256px; height:144px; background:rgba(255,255,255,.95); border:1px solid var(--line); border-radius:10px; box-shadow:0 2px 10px rgba(16,42,67,.12); overflow:hidden; cursor:pointer; }
  .minimap svg { display:block; }
  .minimap .mm-node { fill:#7d97b0; }
  .minimap .mm-node.high{fill:#e0524d;} .minimap .mm-node.medium{fill:#e0a23a;} .minimap .mm-node.low{fill:#3aa17e;}
  .minimap .mm-edge { stroke:#c2cedb; stroke-width:1; }
  .minimap .mm-view { fill:rgba(31,111,235,.12); stroke:#1f6feb; stroke-width:1.5; }
  html.dark-mode .minimap { background:#13243a; border-color:#2a3c50; }
  html.dark-mode .minimap .mm-edge { stroke:#3a4d63; }
  html.dark-mode .zoom-controls { background:rgba(27,44,64,.95); border-color:#2a3c50; }
  html.dark-mode .zoom-controls button:hover { background:rgba(255,255,255,.08); }
  html.dark-mode .board { background-color:#13243a; border-color:#28394a; }
  .board-empty { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:.95rem; pointer-events:none; }

  /* ---- Task card ---- */
  .card {
    position:absolute; z-index:2; width:240px; background:#fff; border:1px solid var(--line);
    border-radius:12px; box-shadow:0 4px 14px rgba(16,42,67,.10); padding:12px 14px;
    cursor:grab; user-select:none; transition:box-shadow .15s, transform .05s;
  }
  .card.dragging { cursor:grabbing; box-shadow:0 12px 30px rgba(16,42,67,.28); z-index:50; opacity:.96; }
  /* priority accent (left border) */
  .card { border-left-width:1px; }
  .card[data-priority="high"]   { border-left:4px solid #e0524d; }
  .card[data-priority="medium"] { border-left:4px solid #e0a23a; }
  .card[data-priority="low"]    { border-left:4px solid #3aa17e; }
  .priority-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:5px; vertical-align:middle; }
  .priority-dot.high{background:#e0524d;} .priority-dot.medium{background:#e0a23a;} .priority-dot.low{background:#3aa17e;}
  html.dark-mode .card { background:#1b2c40; border-color:#2a3c50; color:#dce8f5; box-shadow:0 4px 14px rgba(0,0,0,.35); }
  html.dark-mode .card[data-priority="high"]   { border-left-color:#e0524d; }
  html.dark-mode .card[data-priority="medium"] { border-left-color:#e0a23a; }
  html.dark-mode .card[data-priority="low"]    { border-left-color:#3aa17e; }
  .card .ttl { font-weight:700; font-size:1rem; margin-bottom:4px; padding-right:18px; }
  .card .meta { font-size:.78rem; color:var(--muted); margin-bottom:6px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  html.dark-mode .card .meta { color:#8da6c0; }
  .card .desc { font-size:.84rem; color:#3a4a5a; margin-bottom:8px; white-space:pre-wrap; word-break:break-word; }
  html.dark-mode .card .desc { color:#b9cadc; }
  .status-badge { display:inline-block; padding:1px 8px; border-radius:999px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
  /* roll-up progress ring on parent nodes */
  .rollup { display:inline-flex; align-items:center; gap:3px; margin-left:2px; }
  .rollup svg { display:block; }
  .rollup .ring-bg { fill:none; stroke:#e1e8ef; stroke-width:3; }
  .rollup .ring-fg { fill:none; stroke:#1f6feb; stroke-width:3; stroke-linecap:round; transition:stroke-dashoffset .3s ease; }
  .rollup.complete .ring-fg { stroke:#3aa17e; }
  .rollup-label { font-size:.66rem; font-weight:700; color:var(--muted); }
  .rollup.complete .rollup-label { color:#3aa17e; }
  html.dark-mode .rollup .ring-bg { stroke:#2a3c50; }
  html.dark-mode .rollup .ring-fg { stroke:#6ea8ff; }
  .status-open { background:#e6f0ff; color:#1f5fbf; }
  .status-in-progress { background:#fff3d6; color:#9a6b00; }
  .status-done { background:#dff5e3; color:#1b7a34; }
  .due { display:inline-flex; align-items:center; gap:3px; }

  /* assignees collapsible */
  .assignees { border-top:1px solid var(--line); margin-top:6px; padding-top:6px; }
  html.dark-mode .assignees { border-color:#2a3c50; }
  .assignees summary { cursor:pointer; font-size:.78rem; color:var(--muted); list-style:none; display:flex; align-items:center; gap:5px; }
  .assignees summary::-webkit-details-marker { display:none; }
  .assignees summary .chev { transition:transform .15s; }
  .assignees[open] summary .chev { transform:rotate(90deg); }
  .assignee-list { margin-top:6px; display:flex; flex-direction:column; gap:4px; }
  .assignee-row { display:flex; align-items:center; justify-content:space-between; font-size:.8rem; gap:6px; }
  .assignee-row .x { cursor:pointer; color:var(--red); font-weight:700; padding:0 4px; }
  .assignee-add { display:flex; gap:6px; margin-top:6px; }
  .assignee-add select { flex:1; font-size:.78rem; padding:3px; border:1px solid var(--line); border-radius:6px; background:#fff; }
  html.dark-mode .assignee-add select { background:#13243a; color:#dce8f5; border-color:#2a3c50; }

  .card .actions { display:flex; gap:6px; margin-top:8px; }
  .card .actions button { font-size:.74rem; padding:4px 10px; border-radius:6px; border:1px solid var(--line); background:#fff; cursor:pointer; color:var(--text); }
  html.dark-mode .card .actions button { background:#13243a; color:#cfe0f0; border-color:#2a3c50; }
  .card .actions button.del { color:var(--red); border-color:#f3c4c8; }

  /* ---- Modal ---- */
  .overlay { position:fixed; inset:0; background:rgba(10,20,35,.55); display:none; align-items:center; justify-content:center; z-index:200; }
  .overlay.show { display:flex; }
  .modal { background:#fff; width:min(440px,92vw); border-radius:14px; padding:22px; }
  html.dark-mode .modal { background:#1b2c40; color:#dce8f5; }
  .modal h2 { font-size:1.2rem; margin-bottom:14px; color:var(--text); }
  html.dark-mode .modal h2 { color:#eef4fb; }
  .modal label { display:block; font-size:.82rem; font-weight:600; margin:10px 0 4px; }
  .modal input, .modal textarea, .modal select { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:8px; font-size:.9rem; font-family:inherit; background:#fff; color:var(--text); }
  html.dark-mode .modal input, html.dark-mode .modal textarea, html.dark-mode .modal select { background:#13243a; color:#dce8f5; border-color:#2a3c50; }
  .modal textarea { min-height:70px; resize:vertical; }
  .modal .row { display:flex; gap:10px; }
  .modal .row > div { flex:1; }
  .modal-assignees { margin-top:14px; }
  .modal-assignees > label { margin-bottom:7px; }
  .modal-assignee-box {
    display:flex; flex-direction:column; gap:2px;
    max-height:210px; overflow-y:auto; padding:6px;
    border:1px solid var(--line); border-radius:10px;
    background:#fff; scrollbar-width:thin;
  }
  .modal label.modal-assignee-option {
    position:relative; display:grid; grid-template-columns:18px minmax(0,1fr);
    align-items:center; column-gap:12px; width:100%;
    margin:0; padding:9px 10px; border-radius:7px;
    cursor:pointer; color:var(--text); font-size:.86rem; font-weight:600;
    transition:background .14s ease;
  }
  .modal label.modal-assignee-option:hover { background:#f3f6f9; }
  .modal label.modal-assignee-option:has(input:checked) { background:#eef5fc; }
  .modal label.modal-assignee-option input[type="checkbox"] {
    position:absolute; width:1px; height:1px; margin:0; padding:0;
    opacity:0; pointer-events:none; overflow:hidden;
    clip:rect(0 0 0 0); clip-path:inset(50%); white-space:nowrap;
  }
  .modal-assignee-check {
    position:relative; display:block; grid-column:1; width:18px; height:18px;
    border:1.5px solid #aebdca; border-radius:5px; background:#fff;
    box-shadow:0 1px 2px rgba(16,42,67,.06), inset 0 0 0 2px rgba(255,255,255,.55);
    transition:background .14s ease, border-color .14s ease, box-shadow .14s ease, transform .14s ease;
  }
  .modal-assignee-check::after {
    content:''; position:absolute; left:5px; top:2px; width:5px; height:9px;
    border:solid #fff; border-width:0 2px 2px 0;
    transform:rotate(45deg) scale(.7); opacity:0;
    transition:opacity .12s ease, transform .12s ease;
  }
  .modal label.modal-assignee-option:hover .modal-assignee-check { border-color:#7f93a6; }
  .modal label.modal-assignee-option input:checked + .modal-assignee-check {
    background:var(--navy); border-color:var(--navy);
    box-shadow:0 0 0 3px rgba(22,49,77,.12);
  }
  .modal label.modal-assignee-option input:checked + .modal-assignee-check::after {
    opacity:1; transform:rotate(45deg) scale(1);
  }
  .modal label.modal-assignee-option input:focus-visible + .modal-assignee-check {
    outline:3px solid rgba(31,111,235,.22); outline-offset:2px;
  }
  .modal label.modal-assignee-option:active .modal-assignee-check { transform:scale(.94); }
  .modal-assignee-name {
    grid-column:2; display:block; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    color:inherit; font-size:.86rem; font-weight:600;
  }
  .modal-assignee-empty { color:var(--muted); font-size:.8rem; padding:10px; text-align:center; }
  html.dark-mode .modal-assignee-box { background:#13243a; border-color:#2a3c50; }
  html.dark-mode .modal label.modal-assignee-option { color:#dce8f5; }
  html.dark-mode .modal label.modal-assignee-option:hover { background:#1c3045; }
  html.dark-mode .modal label.modal-assignee-option:has(input:checked) { background:#20384f; }
  html.dark-mode .modal-assignee-check { background:#0f1f31; border-color:#50657a; box-shadow:none; }
  html.dark-mode .modal-assignee-option:hover .modal-assignee-check { border-color:#7890a8; }
  html.dark-mode .modal-assignee-option input:checked + .modal-assignee-check { background:#4f8fd8; border-color:#4f8fd8; box-shadow:0 0 0 3px rgba(79,143,216,.16); }
  .modal .modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
  .err { color:var(--red); font-size:.82rem; margin-top:8px; min-height:1em; }

  /* ---- Layout: board + right-side task list ---- */
  .task-layout { display:flex; gap:18px; align-items:flex-start; margin-top:4px; }
  .task-layout .board { flex:1 1 auto; min-width:0; }
  .task-list-panel { flex:0 0 300px; width:300px; border:1px solid var(--line); border-radius:14px; background:#fff; padding:14px; max-height:695px; overflow-y:auto; }
  html.dark-mode .task-list-panel { background:#1b2c40; border-color:#2a3c50; color:#dce8f5; }
  .task-list-panel h3 { font-size:.95rem; margin:0 0 8px; }
  .task-list-panel .tl-group-title { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:14px 0 6px; font-weight:700; }
  .task-list-panel .tl-group-title:first-of-type { margin-top:4px; }
  .tl-item { display:flex; align-items:flex-start; gap:8px; padding:8px 0; border-bottom:1px solid var(--line); }
  html.dark-mode .tl-item { border-color:#2a3c50; }
  .tl-item:last-child { border-bottom:none; }
  .tl-dot { width:9px; height:9px; border-radius:50%; margin-top:5px; flex:0 0 auto; }
  .tl-dot.open { background:#2563eb; }
  .tl-dot.closed { background:#9aa7b4; }
  .tl-body { flex:1; min-width:0; }
  .tl-title { font-size:.86rem; font-weight:600; line-height:1.25; }
  .tl-meta { font-size:.72rem; color:var(--muted); margin-top:2px; }
  .tl-empty { color:var(--muted); font-size:.82rem; padding:6px 0; }
  @media (max-width:900px){ .task-layout { flex-direction:column; } .task-list-panel { flex-basis:auto; width:100%; max-height:none; } }
  /* ---- Redesigned card: header / menu / collapsible ---- */
  .card .card-head { display:flex; align-items:flex-start; gap:6px; position:relative; }
  .card .card-head-main { display:flex; align-items:center; gap:8px; flex:1; min-width:0; flex-wrap:wrap; }
  .card .card-head-main .ttl { font-weight:700; font-size:1rem; margin:0; white-space:normal; overflow-wrap:anywhere; }
  .card .card-menu-btn { flex:0 0 auto; border:none; background:transparent; cursor:pointer; font-size:1.1rem; line-height:1; padding:2px 6px; border-radius:6px; color:var(--muted); }
  .card .card-menu-btn:hover { background:rgba(16,42,67,.08); color:inherit; }
  .card .card-menu { display:none; position:absolute; top:26px; right:0; z-index:60; background:#fff; border:1px solid var(--line); border-radius:8px; box-shadow:0 6px 20px rgba(16,42,67,.18); overflow:hidden; min-width:110px; }
  .card .card-menu.open { display:block; }
  .card .card-menu button { display:block; width:100%; text-align:left; border:none; background:transparent; padding:8px 14px; cursor:pointer; font-size:.85rem; color:inherit; }
  .card .card-menu button:hover { background:rgba(16,42,67,.07); }
  .card .card-menu button.del { color:#c0392b; }
  .card .card-menu button.del:hover { background:rgba(192,57,43,.10); }
  .card .card-date { font-size:.78rem; color:var(--muted); margin:6px 0 4px; }
  .card .card-date .due { display:inline-flex; align-items:center; gap:4px; }
  .card .card-date .muted-due { font-style:italic; opacity:.8; }
  .card .card-toggle { display:inline-flex; align-items:center; gap:6px; border:none; background:transparent; cursor:pointer; padding:2px 0; font-size:.78rem; color:var(--brand,#1f6feb); font-weight:600; }
  .card .card-toggle .tg-chev { display:inline-block; transition:transform .15s; }
  .card.expanded .card-toggle .tg-chev { transform:rotate(90deg); }
  .card .card-body { display:none; margin-top:8px; padding-top:8px; border-top:1px solid var(--line); }
  .card.expanded .card-body { display:block; }
  .card .card-body .desc { font-size:.85rem; margin-bottom:8px; white-space:pre-wrap; overflow-wrap:anywhere; }
  .card .card-body .desc.muted-due { color:var(--muted); font-style:italic; }
  /* dark mode */
  html.dark-mode .card .card-menu { background:#1b2c40; border-color:#2a3c50; }
  html.dark-mode .card .card-menu button:hover { background:rgba(255,255,255,.06); }
  html.dark-mode .card .card-menu-btn:hover { background:rgba(255,255,255,.08); }
  html.dark-mode .card .card-date { color:#8da6c0; }
  html.dark-mode .card .card-body { border-top-color:#2a3c50; }
  html.dark-mode .card .card-toggle { color:#6ea8ff; }
  /* ---- Edges (task links) ---- */
  .edge-layer { position:absolute; top:0; left:0; pointer-events:none; overflow:visible; z-index:1; }
  .edge-layer .edge-line { stroke:#9bb4cc; stroke-width:2.5; fill:none; pointer-events:stroke; cursor:pointer; transition:stroke .12s; }
  .edge-layer .edge-line:hover { stroke:#1f6feb; stroke-width:3.5; }
  .edge-layer .edge-line.selected { stroke:#c0392b; stroke-width:3.5; }
  .edge-layer .edge-hit { stroke:transparent; stroke-width:16; fill:none; pointer-events:stroke; cursor:pointer; }
  html.dark-mode .edge-layer .edge-line { stroke:#5b7a99; }
  html.dark-mode .edge-layer .edge-line:hover { stroke:#6ea8ff; }
  /* midpoint clickable arrow */
  .edge-layer .edge-arrow { fill:#7d97b0; cursor:pointer; pointer-events:auto; transition:fill .12s, transform .15s ease; }
  .edge-layer .edge-arrow:hover { fill:#1f6feb; }
  .edge-layer .edge-arrow.active { fill:#c0392b; }
  html.dark-mode .edge-layer .edge-arrow { fill:#6e8aa6; }
  html.dark-mode .edge-layer .edge-arrow:hover { fill:#6ea8ff; }
  /* floating delete popup for an edge */
  .edge-menu { position:fixed; transform:translate(-50%,-50%); z-index:200; }
  .edge-menu .edge-menu-del { background:#c0392b; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-size:.8rem; font-weight:600; cursor:pointer; box-shadow:0 4px 14px rgba(16,42,67,.28); white-space:nowrap; }
  .edge-menu .edge-menu-del:hover { background:#a93226; }
  /* connector handle on cards */
  .card .connector {
    position:absolute; right:-9px; top:50%; transform:translateY(-50%);
    width:16px; height:16px; border-radius:50%; background:#1f6feb; border:2px solid #fff;
    cursor:crosshair; z-index:5; box-shadow:0 1px 4px rgba(16,42,67,.3); opacity:0; transition:opacity .12s;
  }
  .card:hover .connector { opacity:1; }
  .card.linking-source .connector { opacity:1; background:#c0392b; }
  .card.link-target-hover { outline:2px dashed #1f6feb; outline-offset:2px; }
  html.dark-mode .card .connector { border-color:#1b2c40; }
  .edge-temp { stroke:#1f6feb; stroke-width:2.5; stroke-dasharray:5 4; fill:none; pointer-events:none; }
  /* ---- Task details preview + popup ---- */
  .card .card-body { display:block; }
  .card.expanded .card-body { display:block; }
  .card .card-body .desc { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; max-height:2.6em; }
  .detpop-overlay { position:fixed; inset:0; background:rgba(10,20,35,.55); display:none; align-items:center; justify-content:center; z-index:260; }
  .detpop-overlay.show { display:flex; }
  .detpop { background:#fff; color:var(--text); width:min(560px,92vw); max-height:82vh; border-radius:14px; padding:22px; display:flex; flex-direction:column; box-shadow:0 18px 48px rgba(10,20,35,.28); }
  html.dark-mode .detpop { background:#1b2c40; color:#dce8f5; }
  .detpop-head { display:flex; align-items:flex-start; gap:12px; margin-bottom:6px; }
  .detpop-head h2 { font-size:1.2rem; margin:0; flex:1; word-break:break-word; }
  .detpop-close { border:none; background:transparent; font-size:1.5rem; line-height:1; cursor:pointer; color:var(--muted); padding:0 4px; }
  .detpop-meta { display:flex; gap:8px; flex-wrap:wrap; align-items:center; font-size:.8rem; color:var(--muted); margin-bottom:12px; }
  .detpop-desc { overflow-y:auto; white-space:pre-wrap; word-break:break-word; line-height:1.55; font-size:.95rem; padding-right:4px; }
  .detpop-desc.empty { color:var(--muted); font-style:italic; }
  /* ---- Neon accent line under the topbar ---- */
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
  /* ===== Premium Kanban workspace ===== */
  .view-toggle {
    display:inline-flex; align-items:center; gap:4px; margin-left:12px; padding:4px;
    border:1px solid var(--line); border-radius:12px; background:rgba(255,255,255,.72);
    box-shadow:0 3px 12px rgba(16,42,67,.06); vertical-align:middle;
  }
  .view-toggle button {
    border:none; border-radius:8px; background:transparent; color:var(--muted);
    padding:7px 15px; font:inherit; font-size:.84rem; font-weight:700; cursor:pointer;
    transition:background .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease;
  }
  .view-toggle button:hover { color:var(--navy); background:rgba(22,49,77,.06); }
  .view-toggle button.active { color:#fff; background:linear-gradient(135deg,var(--red),#a91424); box-shadow:0 4px 12px rgba(200,32,47,.25); }
  html.dark-mode .view-toggle { background:rgba(19,36,58,.82); border-color:#2a3c50; }
  html.dark-mode .view-toggle button:hover { color:#fff; background:rgba(255,255,255,.07); }

  .kanban { display:none; width:100%; position:relative; }
  body.kanban-mode #board, body.kanban-mode .task-list-panel { display:none; }
  body.kanban-mode .task-layout { display:block; width:100%; }
  body.kanban-mode .kanban { display:block; }

  .kanban::before {
    content:''; position:absolute; inset:0; pointer-events:none; border-radius:22px;
    background:
      radial-gradient(circle at 7% 0%,rgba(31,111,235,.09),transparent 30%),
      radial-gradient(circle at 95% 5%,rgba(200,32,47,.07),transparent 28%);
  }

  .kanban-intake {
    position:relative; z-index:1; width:100%; margin:0 0 18px; overflow:hidden;
    border:1px solid #d8e2ed; border-radius:16px; background:rgba(255,255,255,.78);
    box-shadow:0 8px 26px rgba(16,42,67,.07); backdrop-filter:blur(12px);
  }
  .kanban-intake-head {
    display:flex; align-items:center; justify-content:space-between; min-height:58px;
    padding:12px 16px; cursor:pointer; user-select:none;
  }
  .kanban-intake-title { display:flex; align-items:center; gap:11px; min-width:0; }
  .kanban-intake-icon {
    display:grid; place-items:center; width:34px; height:34px; border-radius:10px;
    color:#315d88; background:#e9f2fb; font-size:1rem;
  }
  .kanban-intake-copy strong { display:block; color:var(--navy); font-size:.92rem; }
  .kanban-intake-copy small { display:block; margin-top:1px; color:var(--muted); font-size:.74rem; font-weight:500; }
  .kanban-intake-actions { display:flex; align-items:center; gap:10px; }
  .kanban-count-pill {
    min-width:28px; height:25px; padding:0 8px; display:inline-grid; place-items:center;
    border-radius:999px; color:#315d88; background:#edf4fa; font-size:.72rem; font-weight:800;
  }
  .kanban-intake-chevron { color:var(--muted); font-size:.84rem; transition:transform .18s ease; }
  .kanban-intake.collapsed .kanban-intake-chevron { transform:rotate(-90deg); }
  .kanban-intake-body {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:12px;
    min-height:16px; padding:0 14px 14px;
  }
  .kanban-intake.collapsed .kanban-intake-body { display:none; }

  .kanban-columns {
    position:relative; z-index:1; display:grid; grid-template-columns:repeat(3,minmax(280px,1fr));
    gap:16px; align-items:start; width:100%;
  }
  .kanban-col {
    min-width:0; overflow:hidden; border:1px solid #dce5ee; border-radius:18px;
    background:rgba(248,250,252,.9); box-shadow:0 10px 32px rgba(16,42,67,.08);
    display:flex; flex-direction:column; height:clamp(510px,68vh,760px);
  }
  .kanban-col::before { content:''; display:block; height:4px; background:var(--col-accent); }
  .kanban-col[data-status="main"] { --col-accent:#7c5ce0; --col-soft:#f0ebff; --col-ink:#5f43bd; }
  .kanban-col[data-status="open"] { --col-accent:#3b82f6; --col-soft:#eaf2ff; --col-ink:#245fbd; }
  .kanban-col[data-status="in-progress"] { --col-accent:#e2a425; --col-soft:#fff4d8; --col-ink:#8a6200; }
  .kanban-col[data-status="done"] { --col-accent:#2fa477; --col-soft:#e3f6ee; --col-ink:#1e7657; }
  .kanban-main-col {
    position:relative; z-index:1; width:100%; height:auto; min-height:0; margin:0 0 18px;
  }
  .kanban-main-col .kanban-col-body {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr));
    gap:12px; overflow:visible; min-height:112px;
  }
  .kanban-main-col .kanban-col-body:empty::after { grid-column:1 / -1; }
  .kanban-col-head {
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    padding:16px 16px 13px; border-bottom:1px solid #e6edf4;
    background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(255,255,255,.68));
  }
  .kanban-col-heading { display:flex; align-items:center; gap:10px; min-width:0; }
  .kanban-col-icon {
    width:34px; height:34px; border-radius:11px; display:grid; place-items:center;
    color:var(--col-ink); background:var(--col-soft); font-size:.95rem; font-weight:900;
  }
  .kanban-col-copy strong { display:block; color:var(--navy); font-size:.96rem; line-height:1.1; }
  .kanban-col-copy small { display:block; color:var(--muted); margin-top:3px; font-size:.7rem; font-weight:500; }
  .kanban-col-count {
    min-width:30px; height:27px; padding:0 9px; display:inline-grid; place-items:center;
    border-radius:999px; color:var(--col-ink); background:var(--col-soft); font-size:.74rem; font-weight:800;
  }
  .kanban-col-body {
    flex:1; min-height:90px; overflow-y:auto; padding:13px;
    display:flex; flex-direction:column; gap:11px; scrollbar-width:thin;
    scrollbar-color:#c4d0dc transparent;
  }
  .kanban-col-body:empty::after, .kanban-intake-body:empty::after {
    content:'Drop a task here'; min-height:84px; border:1.5px dashed #cbd6e1; border-radius:12px;
    display:grid; place-items:center; color:#91a0af; font-size:.78rem; font-weight:600;
    background:rgba(255,255,255,.36);
  }
  .kanban-col.drop-hover, .kanban-intake.drop-hover { border-color:var(--red); box-shadow:0 0 0 3px rgba(200,32,47,.10),0 14px 35px rgba(16,42,67,.11); }
  .kanban-col.drop-hover .kanban-col-body, .kanban-intake.drop-hover .kanban-intake-body { background:rgba(200,32,47,.025); }

  .kcard {
    --priority:#e0a23a; position:relative; overflow:hidden; flex:0 0 auto;
    border:1px solid #dce5ed; border-radius:14px; background:#fff; padding:13px 13px 12px;
    cursor:grab; box-shadow:0 4px 14px rgba(16,42,67,.065);
    transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,opacity .18s ease;
  }
  .kcard::before { content:''; position:absolute; inset:0 auto 0 0; width:4px; background:var(--priority); }
  .kcard[data-priority="high"] { --priority:#e0524d; }
  .kcard[data-priority="medium"] { --priority:#f2b512; border-color:#eadba0; background:linear-gradient(145deg,#fff 0%,#fffdf4 100%); }
  .kcard[data-priority="low"] { --priority:#3aa17e; }
  .kcard:hover { transform:translateY(-2px); border-color:#c8d5e2; box-shadow:0 11px 25px rgba(16,42,67,.12); }
  .kcard:active { cursor:grabbing; }
  .kcard.dragging { opacity:.45; transform:rotate(1deg) scale(.985); }
  .kcard.kroot { border-color:#bcd6f1; background:linear-gradient(145deg,#fff 0%,#f5faff 100%); }
  .kcard.kroot::after { display:none; }
  .kcard-title-row { display:flex; align-items:flex-start; flex:1; min-width:0; gap:7px; }
  .kcard-parent-badge { flex:0 0 auto; margin-top:1px; padding:3px 7px; border-radius:999px; color:#2563a8; background:#e2f0ff; font-size:.54rem; font-weight:900; letter-spacing:.07em; line-height:1.1; }

  .kcard-head { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; padding-left:3px; }
  .kcard-title { flex:1; min-width:0; padding-right:4px; color:#22384d; font-size:.91rem; font-weight:750; line-height:1.28; overflow-wrap:anywhere; }
  .kcard-actions { display:flex; align-items:center; gap:5px; flex:0 0 auto; }
  .kcard-edit {
    position:relative; flex:0 0 auto; width:30px; height:30px; padding:0;
    display:grid; place-items:center; border:1px solid transparent; border-radius:9px;
    background:transparent; color:#7b8da0; cursor:pointer;
    transition:background .15s ease,color .15s ease,border-color .15s ease,transform .15s ease,box-shadow .15s ease;
  }
  .kcard-edit svg { width:15px; height:15px; display:block; pointer-events:none; }
  .kcard-edit:hover {
    color:#1f5f99; background:#edf5fc; border-color:#cfe0ef;
    transform:translateY(-1px); box-shadow:0 4px 10px rgba(31,95,153,.10);
  }
  .kcard-edit:focus-visible { outline:2px solid #4d9be6; outline-offset:2px; }
  .kcard-edit::after {
    content:'Edit task'; position:absolute; right:0; top:calc(100% + 7px); z-index:20;
    padding:5px 8px; border-radius:6px; background:#16314d; color:#fff;
    font-size:.66rem; font-weight:700; white-space:nowrap; opacity:0; pointer-events:none;
    transform:translateY(-3px); transition:opacity .14s ease,transform .14s ease;
    box-shadow:0 5px 14px rgba(16,42,67,.2);
  }
  .kcard-edit:hover::after, .kcard-edit:focus-visible::after { opacity:1; transform:translateY(0); }
  .kcard-toggle {
    flex:0 0 auto; display:grid; place-items:center; width:26px; height:26px; border:none; border-radius:8px;
    color:#728398; background:#f3f6f9; cursor:pointer; font-size:.76rem; line-height:1;
    transition:transform .16s ease,background .16s ease,color .16s ease;
  }
  .kcard-toggle:hover { color:#315d88; background:#e9f1f8; }
  .kcard-toggle.open { transform:rotate(90deg); }
  .kcard-meta { display:flex; align-items:center; flex-wrap:wrap; gap:6px; margin-top:10px; padding-left:3px; }
  .kcard-chip {
    display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:3px 7px; border-radius:7px;
    color:#66788a; background:#f2f5f8; font-size:.66rem; font-weight:700;
  }
  .kcard-chip.priority { color:#7a5700; background:#fff0ad; border:1px solid #f2d66d; text-transform:capitalize; }
  .kcard[data-priority="high"] .kcard-chip.priority { color:#a53a35; background:#fdebea; }
  .kcard[data-priority="low"] .kcard-chip.priority { color:#23755a; background:#e7f7f1; }
  .kcard-chip.overdue { color:#b42318; background:#fee9e7; }
  .kcard-parent {
    display:flex; align-items:center; gap:6px; margin:9px 0 0 3px; padding:7px 8px;
    border-radius:8px; color:#5f7488; background:#f3f7fa; font-size:.68rem; font-weight:600;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .kcard-parent span { overflow:hidden; text-overflow:ellipsis; }
  .kcard-desc {
    display:none; margin:10px 0 0 3px; padding:10px 1px 1px; border-top:1px solid #e7edf3;
    color:#617386; font-size:.76rem; line-height:1.45; white-space:pre-wrap; overflow-wrap:anywhere;
  }
  .kcard-desc.open { display:block; animation:kcardReveal .18s ease-out; }
  @keyframes kcardReveal { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:none; } }
  .kcard-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:11px; padding:10px 0 0 3px; border-top:1px solid #edf1f5; }
  .kcard-assignees { display:flex; align-items:center; min-width:0; }
  .kavatar {
    width:25px; height:25px; margin-left:-5px; border:2px solid #fff; border-radius:50%;
    display:grid; place-items:center; color:#fff; background:#4f7398; font-size:.58rem; font-weight:900; letter-spacing:.02em;
  }
  .kavatar:first-child { margin-left:0; }
  .kavatar.more { color:#53697d; background:#e8eef4; }
  .kcard-unassigned { color:#8a99a8; font-size:.66rem; font-weight:600; }
  .kcard-id { color:#a0adba; font-size:.62rem; font-weight:700; }

  html.dark-mode .kanban-intake, html.dark-mode .kanban-col { background:rgba(20,34,49,.92); border-color:#2a3c50; box-shadow:0 10px 30px rgba(0,0,0,.22); }
  html.dark-mode .kanban-intake-copy strong, html.dark-mode .kanban-col-copy strong { color:#edf5fd; }
  html.dark-mode .kanban-col-head { background:linear-gradient(180deg,rgba(28,46,65,.96),rgba(23,39,56,.9)); border-color:#2a3c50; }
  html.dark-mode .kanban-intake-icon { color:#8bc5ff; background:#203c57; }
  html.dark-mode .kanban-count-pill { color:#9cc9f3; background:#20384f; }
  html.dark-mode .kanban-col-body:empty::after, html.dark-mode .kanban-intake-body:empty::after { border-color:#3b5065; color:#71869a; background:rgba(255,255,255,.018); }
  html.dark-mode .kcard { background:#1b2c40; border-color:#2d4257; box-shadow:0 5px 16px rgba(0,0,0,.24); }
  html.dark-mode .kcard:hover { border-color:#45627e; box-shadow:0 12px 28px rgba(0,0,0,.32); }
  html.dark-mode .kcard.kroot { background:linear-gradient(145deg,#1c3045,#17283a); border-color:#365f84; }
  html.dark-mode .kcard-title { color:#e6f0fa; }
  html.dark-mode .kcard-edit { color:#8fa6bb; background:transparent; }
  html.dark-mode .kcard-edit:hover { color:#dcecff; background:#243b50; border-color:#35536d; box-shadow:0 4px 12px rgba(0,0,0,.22); }
  html.dark-mode .kcard-edit::after { background:#08131f; color:#eef6ff; }
  html.dark-mode .kcard-toggle { color:#92a9bd; background:#23384d; }
  html.dark-mode .kcard-toggle:hover { color:#cfe8ff; background:#2a435c; }
  html.dark-mode .kcard-chip { color:#9eb1c3; background:#25394d; }
  html.dark-mode .kcard-parent { color:#9db2c5; background:#21364a; }
  html.dark-mode .kcard-desc, html.dark-mode .kcard-footer { border-color:#2c4257; color:#abc0d2; }
  html.dark-mode .kavatar { border-color:#1b2c40; }

  @media (max-width:1100px) { .kanban-columns { grid-template-columns:repeat(3,minmax(260px,1fr)); overflow-x:auto; padding-bottom:8px; } .kanban-col { min-width:260px; } }
  @media (max-width:760px) { .kanban-columns { display:flex; overflow-x:auto; scroll-snap-type:x mandatory; } .kanban-columns .kanban-col { flex:0 0 min(86vw,330px); scroll-snap-align:start; } .kanban-intake-body, .kanban-main-col .kanban-col-body { grid-template-columns:1fr; } }
  @media (prefers-reduced-motion:reduce) { .kcard,.kcard-toggle,.kanban-intake-chevron { transition:none; } .kcard-desc.open { animation:none; } }

</style>
</head>
<body<?php echo $panel ? ' class="panel-mode"' : ''; ?>>
  <?php $officerActive = 'tasks'; include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-chrome.php'; ?>
  <div class="wrap tasks-wrap">
    <div class="head">
      <div>
        <h1>Tasks</h1>
        <p>Drag cards to organize. They snap to the grid and your layout is saved. Tasks with a due date appear on the calendar.</p>
      </div>
      <button class="btn" id="newTaskBtn">+ New Task</button>
      <span class="view-toggle" id="viewToggle">
        <button type="button" data-view="grid" class="active">Grid</button>
        <button type="button" data-view="kanban">Kanban</button>
      </span>
    </div>
    <div class="task-layout">
    <aside class="task-list-panel" id="taskListPanel">
      <h3>All Tasks</h3>
      <div id="taskListBody"><div class="tl-empty">Loading…</div></div>
    </aside>
    <div class="board" id="board">
      <div class="board-canvas" id="boardCanvas"><svg class="edge-layer" id="edgeLayer" width="8000" height="8000"></svg></div>
      <div class="board-empty" id="boardEmpty">No tasks yet. Click “New Task” to add one.</div>
      <div class="zoom-controls">
        <button type="button" id="zoomInBtn" title="Zoom in">+</button>
        <button type="button" id="zoomOutBtn" title="Zoom out">−</button>
        <button type="button" id="zoomFitBtn" title="Fit to screen">⛶</button>
        <span id="zoomLevel">100%</span>
      </div>
      <div class="board-search">
        <input type="text" id="boardSearch" placeholder="Search tasks…" autocomplete="off">
        <div class="search-results" id="searchResults"></div>
      </div>
      <div class="minimap" id="minimap">
        <svg id="minimapSvg" width="256" height="144" viewBox="0 0 256 144"></svg>
      </div>
    </div>
    <div class="kanban" id="kanban">
      <div class="kanban-intake" id="kanbanIntake">
        <div class="kanban-intake-head" id="kanbanIntakeHead">
          <div class="kanban-intake-title">
            <span class="kanban-intake-icon">◇</span>
            <span class="kanban-intake-copy"><strong>Intake / Unsorted</strong><small>Tasks waiting to be placed into a workflow</small></span>
          </div>
          <div class="kanban-intake-actions"><span class="kanban-count-pill" id="intakeCount">0</span><span class="kanban-intake-chevron" id="intakeChevron">▾</span></div>
        </div>
        <div class="kanban-intake-body" id="kanbanIntakeBody" data-status="unsorted"></div>
      </div>
      <section class="kanban-col kanban-main-col" data-status="main">
        <div class="kanban-col-head"><div class="kanban-col-heading"><span class="kanban-col-icon">◆</span><span class="kanban-col-copy"><strong>Main</strong><small>Main Tasks</small></span></div><span class="kanban-col-count" data-count="main">0</span></div>
        <div class="kanban-col-body" data-status="main"></div>
      </section>
      <div class="kanban-columns">
        <section class="kanban-col" data-status="open">
          <div class="kanban-col-head"><div class="kanban-col-heading"><span class="kanban-col-icon">○</span><span class="kanban-col-copy"><strong>Open</strong><small>Ready to be worked on</small></span></div><span class="kanban-col-count" data-count="open">0</span></div>
          <div class="kanban-col-body" data-status="open"></div>
        </section>
        <section class="kanban-col" data-status="in-progress">
          <div class="kanban-col-head"><div class="kanban-col-heading"><span class="kanban-col-icon">◐</span><span class="kanban-col-copy"><strong>In Progress</strong><small>Actively being completed</small></span></div><span class="kanban-col-count" data-count="in-progress">0</span></div>
          <div class="kanban-col-body" data-status="in-progress"></div>
        </section>
        <section class="kanban-col" data-status="done">
          <div class="kanban-col-head"><div class="kanban-col-heading"><span class="kanban-col-icon">✓</span><span class="kanban-col-copy"><strong>Done</strong><small>Completed and closed</small></span></div><span class="kanban-col-count" data-count="done">0</span></div>
          <div class="kanban-col-body" data-status="done"></div>
        </section>
      </div>
    </div>
  </div>

  <!-- Task details popup (read-only) -->
  <div class="detpop-overlay" id="detpopOverlay">
    <div class="detpop" role="dialog" aria-modal="true" aria-labelledby="detpopTitle">
      <div class="detpop-head">
        <h2 id="detpopTitle"></h2>
        <button type="button" class="detpop-close" id="detpopClose" aria-label="Close">&times;</button>
      </div>
      <div class="detpop-meta" id="detpopMeta"></div>
      <div class="detpop-desc" id="detpopDesc"></div>
    </div>
  </div>
  <!-- Create/Edit modal -->
  <div class="overlay" id="overlay">
    <div class="modal">
      <h2 id="modalTitle">New Task</h2>
      <input type="hidden" id="taskId">
      <label>Title</label>
      <input type="text" id="fTitle" maxlength="255" placeholder="Task title">
      <label>Description</label>
      <textarea id="fDesc" placeholder="Optional details"></textarea>
      <div class="row">
        <div>
          <label>Due date</label>
          <input type="date" id="fDue">
        </div>
        <div>
          <label>Status</label>
          <select id="fStatus">
            <option value="open">Open</option>
            <option value="in-progress">In progress</option>
            <option value="done">Done</option>
          </select>
        </div>
        <div>
          <label>Priority</label>
          <select id="fPriority">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </div>
      </div>
      <div class="modal-assignees">
        <label>Assigned officers</label>
        <div class="modal-assignee-box" id="fAssignees"><div class="modal-assignee-empty">No officers available.</div></div>
      </div>
      <div class="err" id="formErr"></div>
      <div class="modal-actions">
        <button class="btn ghost" id="cancelBtn" type="button">Cancel</button>
        <button class="btn" id="saveBtn" type="button">Save</button>
      </div>
    </div>
  </div>

<script>
const API = '/app/user/officer/tasks-api.php';
const GRID = 40;
let TASKS = [];
let OFFICERS = [];
  let ALL_TASKS = [];   // every task (for the right-side list)
  let EDGES = [];        // task links {id, from_id, to_id}
  // ---- Canvas camera (pan/zoom) ----
  let CAM = { x: 0, y: 0, scale: 1 };
  const MIN_SCALE = 0.3, MAX_SCALE = 2.5;
  try { const saved = JSON.parse(localStorage.getItem('vsa-task-cam')||'null'); if (saved && typeof saved.scale==='number') CAM = saved; } catch(e){}
  function applyCam(){
    const c = document.getElementById('boardCanvas');
    if (!c) return;
    c.style.transform = 'translate('+CAM.x+'px,'+CAM.y+'px) scale('+CAM.scale+')';
    const z = document.getElementById('zoomLevel'); if (z) z.textContent = Math.round(CAM.scale*100)+'%';
    if (typeof renderMinimap==='function') renderMinimap();
  }
  function saveCam(){ try { localStorage.setItem('vsa-task-cam', JSON.stringify(CAM)); } catch(e){} }
  function clampScale(s){ return Math.max(MIN_SCALE, Math.min(MAX_SCALE, s)); }
  function zoomAt(factor, cx, cy){
    const board = document.getElementById('board');
    const r = board.getBoundingClientRect();
    const px = (cx==null? r.width/2 : cx - r.left);
    const py = (cy==null? r.height/2 : cy - r.top);
    const ns = clampScale(CAM.scale * factor);
    const k = ns / CAM.scale;
    CAM.x = px - (px - CAM.x) * k;
    CAM.y = py - (py - CAM.y) * k;
    CAM.scale = ns;
    applyCam(); saveCam();
  }
  function fitToView(){
    const board = document.getElementById('board');
    if (!TASKS.length){ CAM = { x: 0, y: 0, scale: 1 }; applyCam(); saveCam(); return; }
    let minx=Infinity,miny=Infinity,maxx=-Infinity,maxy=-Infinity;
    TASKS.forEach(t=>{ const x=t.pos_x||40, y=t.pos_y||40; minx=Math.min(minx,x); miny=Math.min(miny,y); maxx=Math.max(maxx,x+260); maxy=Math.max(maxy,y+200); });
    const r = board.getBoundingClientRect();
    const pad = 60;
    const sw = (r.width - pad*2) / Math.max(1,(maxx-minx));
    const sh = (r.height - pad*2) / Math.max(1,(maxy-miny));
    const ns = clampScale(Math.min(sw, sh, 1.2));
    CAM.scale = ns;
    CAM.x = pad - minx*ns + (r.width - pad*2 - (maxx-minx)*ns)/2;
    CAM.y = pad - miny*ns + (r.height - pad*2 - (maxy-miny)*ns)/2;
    applyCam(); saveCam();
  }
// ---- fly the camera to center a task node ----
function flyToTask(taskId, targetScale){
  const t = TASKS.find(x=>x.id===taskId);
  if (!t) return;
  const board = document.getElementById('board');
  const cw = 240, ch = 96; // approx card size
  const nx = (t.pos_x||40) + cw/2, ny = (t.pos_y||40) + ch/2;
  const s = clampScale(targetScale || Math.max(CAM.scale, 1));
  CAM.scale = s;
  CAM.x = board.clientWidth/2 - nx*s;
  CAM.y = board.clientHeight/2 - ny*s;
  applyCam(); saveCam(); renderMinimap();
  // flash highlight the card
  const card = document.querySelector('.card[data-id="'+taskId+'"]');
  if (card){ card.classList.add('search-hit'); setTimeout(function(){ card.classList.remove('search-hit'); }, 1600); }
}

// ---- mini-map ----
function renderMinimap(){
  const svg = document.getElementById('minimapSvg');
  if (!svg) return;
  while (svg.firstChild) svg.removeChild(svg.firstChild);
  const NS = 'http://www.w3.org/2000/svg';
  const board = document.getElementById('board');
  if (!TASKS.length){ return; }
  // bounds of all nodes in canvas space
  let minx=Infinity,miny=Infinity,maxx=-Infinity,maxy=-Infinity;
  TASKS.forEach(function(t){ const x=t.pos_x||40, y=t.pos_y||40; minx=Math.min(minx,x); miny=Math.min(miny,y); maxx=Math.max(maxx,x+240); maxy=Math.max(maxy,y+96); });
  const pad = 60; minx-=pad; miny-=pad; maxx+=pad; maxy+=pad;
  const bw = maxx-minx, bh = maxy-miny;
  const MW=256, MH=144;
  const k = Math.min(MW/bw, MH/bh);
  const offx = (MW - bw*k)/2, offy = (MH - bh*k)/2;
  function mmx(x){ return (x-minx)*k + offx; }
  function mmy(y){ return (y-miny)*k + offy; }
  svg.dataset.minx=minx; svg.dataset.miny=miny; svg.dataset.k=k; svg.dataset.offx=offx; svg.dataset.offy=offy;
  // edges
  EDGES.forEach(function(e){
    const a=TASKS.find(t=>t.id===e.from_id), b=TASKS.find(t=>t.id===e.to_id);
    if(!a||!b) return;
    const ln=document.createElementNS(NS,'line');
    ln.setAttribute('class','mm-edge');
    ln.setAttribute('x1', mmx((a.pos_x||40)+120)); ln.setAttribute('y1', mmy((a.pos_y||40)+48));
    ln.setAttribute('x2', mmx((b.pos_x||40)+120)); ln.setAttribute('y2', mmy((b.pos_y||40)+48));
    svg.appendChild(ln);
  });
  // nodes
  TASKS.forEach(function(t){
    const r=document.createElementNS(NS,'rect');
    r.setAttribute('class','mm-node '+(t.priority||'medium'));
    r.setAttribute('x', mmx(t.pos_x||40)); r.setAttribute('y', mmy(t.pos_y||40));
    r.setAttribute('width', Math.max(3,240*k)); r.setAttribute('height', Math.max(2,96*k));
    r.setAttribute('rx', 1.5);
    svg.appendChild(r);
  });
  // viewport rectangle: which canvas region is currently visible
  const vx = -CAM.x/CAM.scale, vy = -CAM.y/CAM.scale;
  const vw = board.clientWidth/CAM.scale, vh = board.clientHeight/CAM.scale;
  const vr=document.createElementNS(NS,'rect');
  vr.setAttribute('class','mm-view');
  vr.setAttribute('x', mmx(vx)); vr.setAttribute('y', mmy(vy));
  vr.setAttribute('width', Math.max(4,vw*k)); vr.setAttribute('height', Math.max(4,vh*k));
  vr.setAttribute('rx', 2);
  svg.appendChild(vr);
}
function minimapPanTo(evt){
  const svg = document.getElementById('minimapSvg');
  if (!svg || !svg.dataset.k) return;
  const r = svg.getBoundingClientRect();
  const px = evt.clientX - r.left, py = evt.clientY - r.top;
  const minx=parseFloat(svg.dataset.minx), miny=parseFloat(svg.dataset.miny), k=parseFloat(svg.dataset.k), offx=parseFloat(svg.dataset.offx), offy=parseFloat(svg.dataset.offy);
  // convert mini-map point back to canvas coords
  const cx = (px-offx)/k + minx, cy = (py-offy)/k + miny;
  const board = document.getElementById('board');
  CAM.x = board.clientWidth/2 - cx*CAM.scale;
  CAM.y = board.clientHeight/2 - cy*CAM.scale;
  applyCam(); saveCam(); renderMinimap();
}

// ---- search ----
function runSearch(q){
  const box = document.getElementById('searchResults');
  if (!box) return;
  q = (q||'').trim().toLowerCase();
  if (!q){ box.classList.remove('open'); box.innerHTML=''; return; }
  const matches = TASKS.filter(t=>(t.title||'').toLowerCase().indexOf(q)>-1).slice(0,8);
  if (!matches.length){ box.innerHTML='<div class="sr-empty">No matches</div>'; box.classList.add('open'); return; }
  box.innerHTML = matches.map(function(t){ return '<div class="sr-item" data-fly="'+t.id+'">'+esc(t.title)+'</div>'; }).join('');
  box.classList.add('open');
}

function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function api(payload){
  const r = await fetch(API, {method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
  const t = await r.text();
  try { return JSON.parse(t); } catch(e){ return {ok:false, error:'Bad response'}; }
}

function snap(v){ return Math.max(0, Math.round(v/GRID)*GRID); }

function statusClass(s){ return 'status-' + (s||'open').replace(/[^a-z-]/g,''); }
function statusLabel(s){ return (s||'open').replace('-', ' '); }

function officerOptions(task){
  const assignedIds = new Set((task.assignees||[]).map(a=>a.user_id));
  const avail = OFFICERS.filter(o => !assignedIds.has(o.user_id));
  if (!avail.length) return '';
  return '<div class="assignee-add"><select data-add="'+task.id+'"><option value="">+ Assign officer…</option>' +
    avail.map(o => '<option value="'+o.user_id+'">'+esc(o.name)+'</option>').join('') +
    '</select></div>';
}

// roll-up progress: children are tasks this node points to via an edge (from_id === task.id)
function taskProgress(taskId){
  const childIds = EDGES.filter(e=>e.from_id===taskId).map(e=>e.to_id);
  if (!childIds.length) return null;
  let done = 0;
  childIds.forEach(function(cid){
    const ct = TASKS.find(t=>t.id===cid);
    if (ct && ct.status==='done') done++;
  });
  return { done: done, total: childIds.length };
}
function progressRingHTML(p){
  if (!p) return '';
  const pct = p.total ? (p.done / p.total) : 0;
  const R = 9, C = 2 * Math.PI * R;
  const off = C * (1 - pct);
  const complete = (p.done === p.total) ? ' complete' : '';
  return '<span class="rollup' + complete + '" title="' + p.done + ' of ' + p.total + ' sub-tasks done">' +
    '<svg width="24" height="24" viewBox="0 0 24 24">' +
      '<circle class="ring-bg" cx="12" cy="12" r="' + R + '"></circle>' +
      '<circle class="ring-fg" cx="12" cy="12" r="' + R + '" stroke-dasharray="' + C.toFixed(2) + '" stroke-dashoffset="' + off.toFixed(2) + '" transform="rotate(-90 12 12)"></circle>' +
    '</svg>' +
    '<span class="rollup-label">' + p.done + '/' + p.total + '</span>' +
  '</span>';
}
function renderCard(task){
  const el = document.createElement('div');
  el.className = 'card';
  el.style.left = (task.pos_x||40) + 'px';
  el.style.top  = (task.pos_y||40) + 'px';
  el.dataset.id = task.id;
  el.dataset.priority = task.priority || 'medium';

  const due = task.due_date ? '<span class="due">\u{1F4C5} '+esc(task.due_date)+'</span>' : '<span class="due muted-due">No due date</span>';
  const aCount = (task.assignees||[]).length;
  const aRows = (task.assignees||[]).map(a =>
      '<div class="assignee-row"><span>'+esc(a.name)+'</span><span class="x" data-unassign="'+task.id+'" data-uid="'+a.user_id+'">\u2715</span></div>'
    ).join('') || '<div class="assignee-row" style="color:var(--muted)">No one assigned</div>';

  el.innerHTML =
    '<div class="card-head">' +
      '<div class="card-head-main">' +
        '<span class="ttl">'+esc(task.title)+'</span>' +
        '<span class="status-badge '+statusClass(task.status)+'">'+esc(statusLabel(task.status))+'</span>' +
        progressRingHTML(taskProgress(task.id)) +
      '</div>' +
      '<button class="card-menu-btn" data-menu="'+task.id+'" title="More" aria-label="More">\u22EE</button>' +
      '<div class="card-menu" data-menufor="'+task.id+'">' +
        '<button data-edit="'+task.id+'">Edit</button>' +
        '<button class="del" data-del="'+task.id+'">Delete</button>' +
      '</div>' +
    '</div>' +
    '<div class="card-date">'+due+'</div>' +
    '<button class="card-toggle" data-toggle="'+task.id+'"><span class="tg-chev">\u25B8</span> <span class="tg-label">Show details</span></button>' +
    '<div class="card-body">' +
      (task.description ? '<div class="desc">'+esc(task.description)+'</div>' : '<div class="desc muted-due">No description</div>') +
      '<details class="assignees"><summary><span class="chev">\u25B8</span> Assigned ('+aCount+')</summary>' +
        '<div class="assignee-list">'+aRows+'</div>' + officerOptions(task) +
      '</details>' +
    '</div>';
  const conn = document.createElement('div');
  conn.className = 'connector';
  conn.title = 'Drag to link to another task';
  conn.dataset.connFor = task.id;
  el.appendChild(conn);
  attachConnector(conn, el, task);

  attachDrag(el);
  return el;
}

  function renderTaskList(){
    var body = document.getElementById('taskListBody');
    if (!body) return;
    var active = ALL_TASKS.filter(function(t){ return (t.status||'open') !== 'done'; });
    var closed = ALL_TASKS.filter(function(t){ return (t.status||'open') === 'done'; });
    function row(t){
      var open = (t.status||'open') !== 'done';
      var who = (t.assignees && t.assignees.length)
        ? t.assignees.map(function(a){ return esc(a.name || a.email || ('#'+a.user_id)); }).join(', ')
        : 'Unassigned';
      var due = t.due_date ? (' \u00b7 due ' + esc(t.due_date)) : '';
      return '<div class="tl-item" data-fly="'+t.id+'" style="cursor:pointer">'
        + '<span class="tl-dot ' + (open?'open':'closed') + '"></span>'
        + '<div class="tl-body">'
        + '<div class="tl-title">' + esc(t.title || '(untitled)') + '</div>'
        + '<div class="tl-meta">' + statusLabel(t.status) + due + ' \u00b7 ' + who + '</div>'
        + '</div></div>';
    }
    var html = '';
    html += '<div class="tl-group-title">Active (' + active.length + ')</div>';
    html += active.length ? active.map(row).join('') : '<div class="tl-empty">No active tasks.</div>';
    html += '<div class="tl-group-title">Closed (' + closed.length + ')</div>';
    html += closed.length ? closed.map(row).join('') : '<div class="tl-empty">No closed tasks.</div>';
    body.innerHTML = html;
  }

function renderBoard(){
  const canvas = document.getElementById('boardCanvas');
  canvas.querySelectorAll('.card').forEach(c=>c.remove());
  document.getElementById('boardEmpty').style.display = TASKS.length ? 'none' : 'flex';
  TASKS.forEach(t => canvas.appendChild(renderCard(t)));
  renderEdges();
  applyCam();
}
// ---- Edge (link) rendering ----
function nodeCenter(taskId){
  const canvas = document.getElementById('boardCanvas');
  const el = canvas.querySelector('.card[data-id="'+taskId+'"]');
  if (el) {
    const x = parseInt(el.style.left,10)||0;
    const y = parseInt(el.style.top,10)||0;
    return { x: x + el.offsetWidth/2, y: y + el.offsetHeight/2 };
  }
  const t = TASKS.find(t=>t.id===taskId);
  if (t) return { x: (t.pos_x||40)+120, y: (t.pos_y||40)+45 };
  return null;
}
function edgePath(a, b){
  // smooth cubic between two points
  const dx = (b.x - a.x) * 0.5;
  return 'M '+a.x+' '+a.y+' C '+(a.x+dx)+' '+a.y+' '+(b.x-dx)+' '+b.y+' '+b.x+' '+b.y;
}
function renderEdges(){
  const svg = document.getElementById('edgeLayer');
  if (!svg) return;
  while (svg.firstChild) svg.removeChild(svg.firstChild);
  const NS = 'http://www.w3.org/2000/svg';
  EDGES.forEach(function(edge){
    const a = nodeCenter(edge.from_id), b = nodeCenter(edge.to_id);
    if (!a || !b) return;
    const d = edgePath(a, b);
    // invisible wide hit band for the line
    const hit = document.createElementNS(NS, 'path');
    hit.setAttribute('class','edge-hit');
    hit.setAttribute('d', d);
    hit.dataset.edgeId = edge.id;
    const line = document.createElementNS(NS, 'path');
    line.setAttribute('class','edge-line');
    line.setAttribute('d', d);
    line.dataset.edgeId = edge.id;
    svg.appendChild(hit);
    svg.appendChild(line);
    // midpoint arrow (clickable). points along the edge direction (a -> b) by default.
    const _dx = (b.x - a.x) * 0.5;
    const P0={x:a.x,y:a.y}, P1={x:a.x+_dx,y:a.y}, P2={x:b.x-_dx,y:b.y}, P3={x:b.x,y:b.y};
    const mx = (P0.x + 3*P1.x + 3*P2.x + P3.x) / 8;
    const my = (P0.y + 3*P1.y + 3*P2.y + P3.y) / 8;
    const _tx = -0.75*P0.x - 0.75*P1.x + 0.75*P2.x + 0.75*P3.x;
    const _ty = -0.75*P0.y - 0.75*P1.y + 0.75*P2.y + 0.75*P3.y;
    const ang = Math.atan2(_ty, _tx) * 180 / Math.PI;
    const arrow = document.createElementNS(NS, 'path');
    arrow.setAttribute('class','edge-arrow');
    // triangle centered at origin, pointing +x; size ~11px
    arrow.setAttribute('d','M -7 -7 L 8 0 L -7 7 L -3 0 Z');
    arrow.dataset.edgeId = edge.id;
    arrow.dataset.mx = mx; arrow.dataset.my = my;
    arrow.dataset.flowAngle = ang;
    arrow.setAttribute('transform','translate('+mx+','+my+') rotate('+ang+')');
    svg.appendChild(arrow);
  });
  // if a delete popup is open for an edge that still exists, keep arrow aimed at it
  if (window.__edgeMenu && window.__edgeMenu.edgeId){
    const still = EDGES.some(e=>e.id===window.__edgeMenu.edgeId);
    if (!still) closeEdgeMenu();
    else aimArrowAtMenu();
  }
}
// ---- Drag-to-connect (create edges) ----
let LINKING = null; // { fromId, tempEl }
function clientToCanvas(cx, cy){
  const board = document.getElementById('board');
  const r = board.getBoundingClientRect();
  return { x: (cx - r.left - CAM.x) / CAM.scale, y: (cy - r.top - CAM.y) / CAM.scale };
}
function attachConnector(conn, cardEl, task){
  conn.addEventListener('pointerdown', (e)=>{
    e.preventDefault(); e.stopPropagation();
    const svg = document.getElementById('edgeLayer');
    const NS = 'http://www.w3.org/2000/svg';
    const temp = document.createElementNS(NS, 'path');
    temp.setAttribute('class','edge-temp');
    svg.appendChild(temp);
    LINKING = { fromId: task.id, temp: temp };
    cardEl.classList.add('linking-source');
    try{ conn.setPointerCapture(e.pointerId); }catch(_){}
  });
  conn.addEventListener('pointermove', (e)=>{
    if (!LINKING) return;
    const a = nodeCenter(LINKING.fromId);
    const b = clientToCanvas(e.clientX, e.clientY);
    if (a) LINKING.temp.setAttribute('d', edgePath(a, b));
    // highlight hovered target card
    document.querySelectorAll('.card.link-target-hover').forEach(c=>c.classList.remove('link-target-hover'));
    const over = document.elementFromPoint(e.clientX, e.clientY);
    const tgt = over && over.closest ? over.closest('.card') : null;
    if (tgt && parseInt(tgt.dataset.id,10) !== LINKING.fromId) tgt.classList.add('link-target-hover');
  });
  conn.addEventListener('pointerup', async (e)=>{
    if (!LINKING) return;
    const link = LINKING; LINKING = null;
    if (link.temp && link.temp.parentNode) link.temp.parentNode.removeChild(link.temp);
    cardEl.classList.remove('linking-source');
    document.querySelectorAll('.card.link-target-hover').forEach(c=>c.classList.remove('link-target-hover'));
    const over = document.elementFromPoint(e.clientX, e.clientY);
    const tgt = over && over.closest ? over.closest('.card') : null;
    if (!tgt) return;
    const toId = parseInt(tgt.dataset.id, 10);
    if (!toId || toId === link.fromId) return;
    const res = await api({action:'add_edge', from_id: link.fromId, to_id: toId});
    if (res && res.ok){ EDGES = res.edges || EDGES; renderBoard(); }
  });
}
// ---- Edge click to delete ----
// ---- canvas <-> screen helper ----
function canvasToClient(x, y){
  const board = document.getElementById('board');
  const r = board.getBoundingClientRect();
  return { x: x*CAM.scale + CAM.x + r.left, y: y*CAM.scale + CAM.y + r.top };
}
// ---- edge delete popup anchored to the midpoint arrow ----
window.__edgeMenu = null;
function closeEdgeMenu(){
  const m = document.getElementById('edgeMenu');
  if (m) m.remove();
  window.__edgeMenu = null;
  // restore every arrow to its flow direction
  document.querySelectorAll('.edge-arrow').forEach(function(ar){
    const mx = parseFloat(ar.dataset.mx), my = parseFloat(ar.dataset.my), fa = parseFloat(ar.dataset.flowAngle);
    ar.setAttribute('transform','translate('+mx+','+my+') rotate('+fa+')');
    ar.classList.remove('active');
  });
}
function aimArrowAtMenu(){
  if (!window.__edgeMenu) return;
  const menu = document.getElementById('edgeMenu');
  if (!menu) return;
  const ar = document.querySelector('.edge-arrow[data-edge-id="'+window.__edgeMenu.edgeId+'"]');
  if (!ar) return;
  const mx = parseFloat(ar.dataset.mx), my = parseFloat(ar.dataset.my);
  // angle (canvas space) from arrow midpoint toward the menu's canvas position
  const mc = window.__edgeMenu.canvas;
  const ang = Math.atan2(mc.y - my, mc.x - mx) * 180 / Math.PI;
  ar.setAttribute('transform','translate('+mx+','+my+') rotate('+ang+')');
  ar.classList.add('active');
}
function openEdgeMenu(edgeId){
  closeEdgeMenu();
  const ar = document.querySelector('.edge-arrow[data-edge-id="'+edgeId+'"]');
  if (!ar) return;
  const mx = parseFloat(ar.dataset.mx), my = parseFloat(ar.dataset.my), fa = parseFloat(ar.dataset.flowAngle);
  // place the Delete button offset PERPENDICULAR to the edge so it doesn't cover the line
  const perp = (fa + 90) * Math.PI / 180;
  const off = 46; // canvas-space offset distance
  // choose the side (perp or -perp) that has more room on screen; default to below-right
  const cand1 = { x: mx + Math.cos(perp)*off, y: my + Math.sin(perp)*off };
  const cand2 = { x: mx - Math.cos(perp)*off, y: my - Math.sin(perp)*off };
  const s1 = canvasToClient(cand1.x, cand1.y), s2 = canvasToClient(cand2.x, cand2.y);
  const W = window.innerWidth, H = window.innerHeight;
  function room(p){ return Math.min(p.x, W-p.x) + Math.min(p.y, H-p.y); }
  const pickCanvas = room(s1) >= room(s2) ? cand1 : cand2;
  const screen = canvasToClient(pickCanvas.x, pickCanvas.y);
  const menu = document.createElement('div');
  menu.id = 'edgeMenu';
  menu.className = 'edge-menu';
  menu.style.left = screen.x + 'px';
  menu.style.top = screen.y + 'px';
  menu.innerHTML = '<button type="button" class="edge-menu-del">Delete link</button>';
  document.body.appendChild(menu);
  window.__edgeMenu = { edgeId: edgeId, canvas: pickCanvas };
  aimArrowAtMenu();
  menu.querySelector('.edge-menu-del').addEventListener('click', async function(ev){
    ev.stopPropagation();
    const res = await api({action:'remove_edge', id: edgeId});
    if (res && res.ok){ EDGES = res.edges || EDGES; }
    closeEdgeMenu();
    renderBoard();
  });
}
// click an edge arrow (or the line) to open its delete popup
document.getElementById('edgeLayer').addEventListener('click', (e)=>{
  const t = e.target.closest('[data-edge-id]');
  if (!t) return;
  const eid = parseInt(t.dataset.edgeId, 10);
  if (!eid) return;
  if (window.__edgeMenu && window.__edgeMenu.edgeId === eid){ closeEdgeMenu(); return; }
  openEdgeMenu(eid);
});
// dismiss the popup when clicking elsewhere
document.addEventListener('click', (e)=>{
  if (!window.__edgeMenu) return;
  if (e.target.closest('#edgeMenu')) return;
  if (e.target.closest('[data-edge-id]')) return;
  closeEdgeMenu();
});

// ---- Dragging with snap-to-grid ----
function attachDrag(el){
  let sx, sy, ox, oy, dragging=false;
  el.addEventListener('pointerdown', (e)=>{
    if (e.target.closest('.connector') || e.target.closest('.card-toggle') || e.target.closest('.card-menu-btn') || e.target.closest('.card-menu') || e.target.closest('.assignees') || e.target.closest('select')) return;
    dragging = true;
    el.classList.add('dragging');
    try{ el.setPointerCapture(e.pointerId); }catch(_){}
    sx = e.clientX; sy = e.clientY;
    ox = parseInt(el.style.left,10)||0; oy = parseInt(el.style.top,10)||0;
  });
  el.addEventListener('pointermove', (e)=>{
    if(!dragging) return;
    let nx = ox + (e.clientX - sx) / CAM.scale;
    let ny = oy + (e.clientY - sy) / CAM.scale;
    nx = Math.max(0, nx);
    ny = Math.max(0, ny);
    el.style.left = nx + 'px';
    el.style.top  = ny + 'px';
    renderEdges();
  });
  el.addEventListener('pointerup', async (e)=>{
    if(!dragging) return;
    dragging = false;
    el.classList.remove('dragging');
    // snap to grid (lock onto background)
    const nx = snap(parseInt(el.style.left,10)||0);
    const ny = snap(parseInt(el.style.top,10)||0);
    el.style.left = nx+'px'; el.style.top = ny+'px';
    const id = parseInt(el.dataset.id,10);
    const task = TASKS.find(t=>t.id===id);
    if (task){ task.pos_x = nx; task.pos_y = ny; }
    await api({action:'move', id, pos_x:nx, pos_y:ny});
  });
}

// ---- Modal ----
const overlay = document.getElementById('overlay');
function openModal(task){
  document.getElementById('formErr').textContent = '';
  document.getElementById('taskId').value = task ? task.id : '';
  document.getElementById('fTitle').value = task ? task.title : '';
  document.getElementById('fDesc').value = task ? (task.description||'') : '';
  document.getElementById('fDue').value = task && task.due_date ? task.due_date : '';
  document.getElementById('fStatus').value = task ? (task.status||'open') : 'open';
  document.getElementById('fPriority').value = task ? (task.priority||'medium') : 'medium';
  const assigned = new Set(task && Array.isArray(task.assignees) ? task.assignees.map(a=>Number(a.user_id)) : []);
  const assigneeBox = document.getElementById('fAssignees');
  if (assigneeBox) {
    assigneeBox.innerHTML = OFFICERS.length ? OFFICERS.map(function(o){
      const oid = Number(o.user_id);
      const officerName = o.name||o.email||('#'+oid);
      return '<label class="modal-assignee-option">'+
        '<input type="checkbox" value="'+oid+'" '+(assigned.has(oid)?'checked':'')+'>'+
        '<span class="modal-assignee-check" aria-hidden="true"></span>'+
        '<span class="modal-assignee-name">'+esc(officerName)+'</span>'+
      '</label>';
    }).join('') : '<div class="modal-assignee-empty">No officers available.</div>';
  }
  document.getElementById('modalTitle').textContent = task ? 'Edit Task' : 'New Task';
  overlay.classList.add('show');
}
function closeModal(){ overlay.classList.remove('show'); }

var __detEsc = (typeof esc==='function') ? esc : function(s){ var d=document.createElement('div'); d.textContent = (s==null?'':String(s)); return d.innerHTML; };
function openDetails(t){
  var ov = document.getElementById('detpopOverlay'); if(!ov) return;
  document.getElementById('detpopTitle').textContent = t.title || 'Task';
  var meta = document.getElementById('detpopMeta');
  var parts = [];
  var sl = (typeof statusLabel==='function') ? statusLabel(t.status) : (t.status||'open');
  parts.push('<span class="status-badge ' + ((typeof statusClass==='function')?statusClass(t.status):'') + '">' + __detEsc(sl) + '</span>');
  if(t.due) parts.push('<span>\uD83D\uDCC5 ' + __detEsc(t.due) + '</span>');
  if(t.priority) parts.push('<span>Priority: ' + __detEsc(t.priority) + '</span>');
  meta.innerHTML = parts.join('');
  var d = document.getElementById('detpopDesc');
  if(t.description && String(t.description).trim()){ d.classList.remove('empty'); d.textContent = t.description; }
  else { d.classList.add('empty'); d.textContent = 'No description provided.'; }
  ov.classList.add('show');
}
function closeDetails(){ var ov=document.getElementById('detpopOverlay'); if(ov) ov.classList.remove('show'); }
(function(){
  var ov = document.getElementById('detpopOverlay');
  if(ov){
    var cb = document.getElementById('detpopClose'); if(cb) cb.onclick = closeDetails;
    ov.addEventListener('click', function(e){ if(e.target===ov) closeDetails(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && ov.classList.contains('show')) closeDetails(); });
  }
})();
document.getElementById('newTaskBtn').onclick = ()=> openModal(null);
document.getElementById('cancelBtn').onclick = closeModal;
overlay.addEventListener('click', e=>{ if(e.target===overlay) closeModal(); });

document.getElementById('saveBtn').onclick = async ()=>{
  const id = document.getElementById('taskId').value;
  const title = document.getElementById('fTitle').value.trim();
  if(!title){ document.getElementById('formErr').textContent = 'Title is required.'; return; }
  const payload = {
    action: id ? 'update' : 'create',
    title,
    description: document.getElementById('fDesc').value,
    due_date: document.getElementById('fDue').value,
    status: document.getElementById('fStatus').value,
    priority: document.getElementById('fPriority').value
  };
  if(id) payload.id = parseInt(id,10);
  else {
    // place new card at a free-ish grid spot
    payload.pos_x = 40 + (TASKS.length % 4) * 260;
    payload.pos_y = 40 + Math.floor(TASKS.length / 4) * 200;
  }
  const selectedAssignees = Array.from(document.querySelectorAll('#fAssignees input[type="checkbox"]:checked')).map(function(cb){ return parseInt(cb.value,10); });
  const previousTask = id ? (TASKS.find(function(t){ return t.id===parseInt(id,10); }) || ALL_TASKS.find(function(t){ return t.id===parseInt(id,10); })) : null;
  const previousAssignees = previousTask && Array.isArray(previousTask.assignees) ? previousTask.assignees.map(function(a){ return Number(a.user_id); }) : [];
  const res = await api(payload);
  if(!res.ok){ document.getElementById('formErr').textContent = res.error || 'Could not save.'; return; }
  const savedId = id ? parseInt(id,10) : ((res.task && res.task.id) ? Number(res.task.id) : null);
  if (savedId) {
    const toAdd = selectedAssignees.filter(function(uid){ return previousAssignees.indexOf(uid)===-1; });
    const toRemove = previousAssignees.filter(function(uid){ return selectedAssignees.indexOf(uid)===-1; });
    for (const uid of toAdd) await api({action:'assign', id:savedId, user_id:uid});
    for (const uid of toRemove) await api({action:'unassign', id:savedId, user_id:uid});
  }
  const fresh = await api({action:'list'});
  if (fresh && fresh.ok) { ALL_TASKS = fresh.allTasks || fresh.tasks || []; TASKS = fresh.tasks || []; OFFICERS = fresh.officers || OFFICERS; EDGES = fresh.edges || EDGES; }
  else { TASKS = res.tasks || TASKS; ALL_TASKS = res.allTasks || res.tasks || ALL_TASKS; }
  renderBoard(); renderTaskList(); renderKanban();
  closeModal();
};

// ---- Delegated actions on the board ----
document.getElementById('board').addEventListener('click', async (e)=>{
  const edit = e.target.closest('[data-edit]');
  const del  = e.target.closest('[data-del]');
  const un   = e.target.closest('[data-unassign]');
  const tg = e.target.closest('[data-toggle]');
  const mb = e.target.closest('[data-menu]');
  if(tg){
    const id = parseInt(tg.dataset.toggle, 10);
    const t = ALL_TASKS.find(function(x){ return x.id === id; }) || TASKS.find(function(x){ return x.id === id; });
    if(t) openDetails(t);
    return;
  }
  if(mb){
    const id = mb.dataset.menu;
    const menu = document.querySelector('.card-menu[data-menufor="'+id+'"]');
    const isOpen = menu && menu.classList.contains('open');
    document.querySelectorAll('.card-menu.open').forEach(x=>x.classList.remove('open'));
    if(menu && !isOpen) menu.classList.add('open');
    return;
  }
  if(edit){ const t = TASKS.find(x=>x.id===parseInt(edit.dataset.edit,10)); if(t) openModal(t); }
  else if(del){
    const id = parseInt(del.dataset.del,10);
    if(confirm('Delete this task? This cannot be undone.')){
      const res = await api({action:'delete', id});
      if(res.ok){ ALL_TASKS = res.allTasks || res.tasks || []; TASKS = res.tasks || []; renderBoard(); renderTaskList(); }
    }
  } else if(un){
    const id = parseInt(un.dataset.unassign,10);
    const uid = parseInt(un.dataset.uid,10);
    const res = await api({action:'unassign', id, user_id:uid});
    if(res.ok){ ALL_TASKS = res.allTasks || res.tasks || []; TASKS = res.tasks || []; renderBoard(); renderTaskList(); }
  }
});
// Close any open card menu when clicking outside of it
document.addEventListener('click', (e)=>{
  if(e.target.closest('.card-menu') || e.target.closest('[data-menu]')) return;
  document.querySelectorAll('.card-menu.open').forEach(x=>x.classList.remove('open'));
});

document.getElementById('board').addEventListener('change', async (e)=>{
  const add = e.target.closest('[data-add]');
  if(add && add.value){
    const id = parseInt(add.dataset.add,10);
    const uid = parseInt(add.value,10);
    const res = await api({action:'assign', id, user_id:uid});
    if(res.ok){ ALL_TASKS = res.allTasks || res.tasks || []; TASKS = res.tasks || []; renderBoard(); renderTaskList(); }
  }
});

// ---- Init ----
(async function(){
  const res = await api({action:'list'});
  if(res.ok){ ALL_TASKS = res.allTasks || res.tasks || []; TASKS = res.tasks || []; OFFICERS = res.officers||[]; EDGES = res.edges||[]; renderBoard(); renderTaskList(); }

/* ---- Live auto-refresh: keep all officers' boards in sync ---- */
(function(){
  var POLL_MS = 5000;
  var dragging = false;
  document.addEventListener('pointerdown', function(ev){
    if (ev.target && ev.target.closest && ev.target.closest('.card')) dragging = true;
  }, true);
  document.addEventListener('pointerup', function(){ dragging = false; }, true);
  document.addEventListener('mousedown', function(ev){
    if (ev.target && ev.target.closest && ev.target.closest('.card')) dragging = true;
  }, true);
  document.addEventListener('mouseup', function(){ dragging = false; }, true);

  function modalOpen(){
    var ov = document.getElementById('overlay');
    return ov && ov.classList.contains('show');
  }

  async function refresh(){
    if (modalOpen() || dragging || document.hidden) return;
    try {
      var res = await api({action:'list'});
      if (!res || !res.ok) return;
      var newTasks = res.tasks || [];
      var newAll = res.allTasks || res.tasks || [];
      var newEdges = res.edges || EDGES;
      var newOfficers = res.officers || OFFICERS;
      var changed = JSON.stringify(newTasks) !== JSON.stringify(TASKS)
                 || JSON.stringify(newAll) !== JSON.stringify(ALL_TASKS)
                 || JSON.stringify(newOfficers) !== JSON.stringify(OFFICERS)
                    || JSON.stringify(newEdges) !== JSON.stringify(EDGES);
      if (!changed) return;
      if (modalOpen() || dragging) return;
      TASKS = newTasks;
      ALL_TASKS = newAll;
      OFFICERS = newOfficers;
      EDGES = newEdges;
      renderBoard(); renderTaskList(); renderKanban();
    } catch (e) {}
  }

  // ---- Canvas pan & zoom wiring ----
  (function setupCanvas(){
    const board = document.getElementById('board');
    if (!board) return;
    // Wheel to zoom toward cursor
    board.addEventListener('wheel', (e)=>{
      e.preventDefault();
      const factor = e.deltaY < 0 ? 1.12 : 1/1.12;
      zoomAt(factor, e.clientX, e.clientY);
    }, { passive:false });
    // Empty-space drag to pan (ignore drags that start on a card)
    let panning=false, psx=0, psy=0, pcx=0, pcy=0;
    board.addEventListener('pointerdown', (e)=>{
      if (e.target.closest('.card') || e.target.closest('.board-search') || e.target.closest('.zoom-controls') || e.target.closest('.edge-layer') || (e.target.dataset && e.target.dataset.edgeId)) return;
      e.preventDefault();
      panning=true; psx=e.clientX; psy=e.clientY; pcx=CAM.x; pcy=CAM.y;
      board.classList.add('panning');
      try{ board.setPointerCapture(e.pointerId); }catch(_){}
    });
    board.addEventListener('pointermove', (e)=>{
      if(!panning) return;
      CAM.x = pcx + (e.clientX - psx);
      CAM.y = pcy + (e.clientY - psy);
      applyCam();
    });
    function endPan(){ if(!panning) return; panning=false; board.classList.remove('panning'); saveCam(); }
    board.addEventListener('pointerup', endPan);
    board.addEventListener('pointercancel', endPan);
    const zi=document.getElementById('zoomInBtn'); if(zi) zi.addEventListener('click', ()=>zoomAt(1.2));
    const zo=document.getElementById('zoomOutBtn'); if(zo) zo.addEventListener('click', ()=>zoomAt(1/1.2));
    const zf=document.getElementById('zoomFitBtn'); if(zf) zf.addEventListener('click', fitToView);
    applyCam();
    // --- search box wiring ---
    const searchInput = document.getElementById('boardSearch');
    if (searchInput){
      searchInput.addEventListener('input', function(){ runSearch(this.value); });
      searchInput.addEventListener('keydown', function(e){
        if (e.key==='Enter'){
          const first = document.querySelector('#searchResults .sr-item');
          if (first){ flyToTask(parseInt(first.dataset.fly,10)); this.blur(); document.getElementById('searchResults').classList.remove('open'); }
        } else if (e.key==='Escape'){ this.value=''; document.getElementById('searchResults').classList.remove('open'); }
      });
    }
    const sr = document.getElementById('searchResults');
    if (sr){ sr.addEventListener('click', function(e){
      const it = e.target.closest('.sr-item'); if (!it) return;
      flyToTask(parseInt(it.dataset.fly,10));
      sr.classList.remove('open');
      if (searchInput) searchInput.value='';
    }); }
    document.addEventListener('click', function(e){
      if (!e.target.closest('.board-search')){ const b=document.getElementById('searchResults'); if(b) b.classList.remove('open'); }
    });
    // --- mini-map click to pan ---
    const mm = document.getElementById('minimap');
    if (mm){ mm.addEventListener('click', minimapPanTo); }
    // --- All Tasks list: click a row to fly to that node ---
    const tlBody = document.getElementById('taskListBody');
    if (tlBody){ tlBody.addEventListener('click', function(e){
      const row = e.target.closest('[data-fly]'); if (!row) return;
      flyToTask(parseInt(row.dataset.fly,10));
    }); }
    renderMinimap();
  })();
  setInterval(refresh, POLL_MS);
  document.addEventListener('visibilitychange', function(){ if (!document.hidden) refresh(); });
})();
})();
  (function(){
    var t = document.getElementById('darkModeToggle');
    if (!t) return;
    t.checked = document.documentElement.classList.contains('dark-mode');
    t.addEventListener('change', function(){
      if (t.checked){
        document.documentElement.classList.add('dark-mode');
        try { localStorage.setItem('vsa-theme','dark'); } catch(e){}
      } else {
        document.documentElement.classList.remove('dark-mode');
        try { localStorage.setItem('vsa-theme','light'); } catch(e){}
      }
    });
  })();

/* ===== Kanban add-on logic ===== */
function kanbanParentOf(taskId){
  var e = (typeof EDGES!=="undefined"?EDGES:[]).find(function(e){ return e.to_id===taskId; });
  if (!e) return null;
  var fromList = (typeof ALL_TASKS!=="undefined" && ALL_TASKS.length) ? ALL_TASKS : TASKS;
  return fromList.find(function(t){ return t.id===e.from_id; }) || TASKS.find(function(t){ return t.id===e.from_id; }) || null;
}
function kanbanIsRoot(taskId){
  var ed = (typeof EDGES!=="undefined"?EDGES:[]);
  var hasChild = ed.some(function(e){ return e.from_id===taskId; });
  var isChild  = ed.some(function(e){ return e.to_id===taskId; });
  return hasChild && !isChild;
}
function kanbanInitials(name){
  var parts = String(name||'').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0].charAt(0) + (parts.length>1 ? parts[parts.length-1].charAt(0) : '')).toUpperCase();
}
function kanbanCardHTML(t){
  var root = kanbanIsRoot(t.id);
  var parent = kanbanParentOf(t.id);
  var priority = String(t.priority||'medium').toLowerCase();
  var dueClass = '';
  var dueLabel = 'No due date';
  if (t.due_date){
    dueLabel = esc(t.due_date);
    var today = new Date(); today.setHours(0,0,0,0);
    var dueDate = new Date(String(t.due_date)+'T00:00:00');
    if (!isNaN(dueDate.getTime()) && dueDate < today && t.status !== 'done') dueClass = ' overdue';
  }
  var parentHtml = parent ? ('<div class="kcard-parent" title="Parent: '+esc(parent.title||('#'+parent.id))+'">↳ <span>'+esc(parent.title||('#'+parent.id))+'</span></div>') : '';
  var rootBadge = root ? '<span class="kcard-parent-badge">PARENT</span>' : '';
  var descHtml = '<div class="kcard-desc">'+(t.description ? esc(String(t.description)) : 'No description provided.')+'</div>';
  var people = Array.isArray(t.assignees) ? t.assignees : [];
  var avatars = people.slice(0,3).map(function(a){ var n=a.name||a.email||''; return '<span class="kavatar" title="'+esc(n)+'">'+esc(kanbanInitials(n))+'</span>'; }).join('');
  if (people.length>3) avatars += '<span class="kavatar more">+'+(people.length-3)+'</span>';
  var peopleHtml = people.length ? '<div class="kcard-assignees">'+avatars+'</div>' : '<span class="kcard-unassigned">Unassigned</span>';
  return '<article class="kcard'+(root?' kroot':'')+'" draggable="true" data-id="'+t.id+'" data-priority="'+esc(priority)+'">'+
    '<div class="kcard-head"><div class="kcard-title-row">'+rootBadge+'<div class="kcard-title">'+esc(t.title||('#'+t.id))+'</div></div>'+
    '<div class="kcard-actions">'+
      '<button type="button" class="kcard-edit" data-kedit="'+t.id+'" draggable="false" title="Edit task" aria-label="Edit task"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4.2L19.6 8.6a2 2 0 0 0 0-2.8l-1.4-1.4a2 2 0 0 0-2.8 0L4 15.8V20Zm3.4-2H6v-1.4L14.8 7.8l1.4 1.4L7.4 18Zm8.8-11.6 1.4-1.4 1.4 1.4-1.4 1.4-1.4-1.4Z" fill="currentColor"/></svg></button>'+
      '<button type="button" class="kcard-toggle" data-toggle="'+t.id+'" draggable="false" title="Show description" aria-label="Show description">▸</button>'+
    '</div></div>'+
    '<div class="kcard-meta"><span class="kcard-chip'+dueClass+'">▣ '+dueLabel+'</span><span class="kcard-chip priority">● '+esc(priority)+'</span></div>'+
    parentHtml+descHtml+
    '<div class="kcard-footer">'+peopleHtml+'<span class="kcard-id">#'+t.id+'</span></div></article>';
}
function renderKanban(){
  var kb = document.getElementById("kanban"); if (!kb) return;
  if (!document.body.classList.contains("kanban-mode")) return;
  var bodies = kb.querySelectorAll(".kanban-col-body, #kanbanIntakeBody");
  bodies.forEach(function(b){ b.innerHTML = ""; });
  var counts = { "main":0, "open":0, "in-progress":0, "done":0, "unsorted":0 };
  TASKS.forEach(function(t){
    var st = t.status;
    var target;
    if (kanbanIsRoot(t.id)) { target = kb.querySelector('.kanban-col-body[data-status="main"]'); counts.main++; }
    else if (st==="open"||st==="in-progress"||st==="done"){ target = kb.querySelector('.kanban-col-body[data-status="'+st+'"]'); counts[st]++; }
    else { target = document.getElementById("kanbanIntakeBody"); counts["unsorted"]++; st="unsorted"; }
    if (target) target.insertAdjacentHTML("beforeend", kanbanCardHTML(t));
  });
  var ic = document.getElementById("intakeCount"); if (ic) ic.textContent = counts["unsorted"];
  kb.querySelectorAll(".kanban-col-count").forEach(function(c){ c.textContent = counts[c.getAttribute("data-count")]||0; });
}
document.addEventListener("click", function(e){
  var editBtn = e.target.closest && e.target.closest("[data-kedit]");
  if (!editBtn) return;
  e.preventDefault(); e.stopPropagation();
  var id = parseInt(editBtn.getAttribute("data-kedit"), 10);
  var task = TASKS.find(function(t){ return t.id === id; }) || ALL_TASKS.find(function(t){ return t.id === id; });
  if (task) openModal(task);
});
document.addEventListener("click", function(e){
  var btn = e.target.closest && e.target.closest(".kcard-toggle");
  if(!btn) return;
  e.preventDefault(); e.stopPropagation();
  var card = btn.closest(".kcard");
  if(!card) return;
  var desc = card.querySelector(".kcard-desc");
  if(!desc) return;
  var open = desc.classList.toggle("open");
  btn.classList.toggle("open", open);
  btn.setAttribute("title", open ? "Hide description" : "Show description");
});
var kanbanDragId = null;
document.addEventListener("dragstart", function(e){
  if (e.target.closest && e.target.closest(".kcard-actions")) { e.preventDefault(); return; }
  var card = e.target.closest && e.target.closest(".kcard"); if (!card) return;
  kanbanDragId = parseInt(card.getAttribute("data-id"),10); card.classList.add("dragging");
  if (e.dataTransfer){ e.dataTransfer.effectAllowed = "move"; try{ e.dataTransfer.setData("text/plain", String(kanbanDragId)); }catch(_){} }
});
document.addEventListener("dragend", function(e){
  var card = e.target.closest && e.target.closest(".kcard"); if (card) card.classList.remove("dragging");
  document.querySelectorAll(".drop-hover").forEach(function(el){ el.classList.remove("drop-hover"); });
});
function kanbanDropZone(el){ return el.closest ? el.closest(".kanban-col, .kanban-intake") : null; }
document.addEventListener("dragover", function(e){
  var zone = kanbanDropZone(e.target); if (!zone || !document.body.classList.contains("kanban-mode")) return;
  e.preventDefault(); if (e.dataTransfer) e.dataTransfer.dropEffect = "move";
  document.querySelectorAll(".drop-hover").forEach(function(el){ if(el!==zone) el.classList.remove("drop-hover"); });
  zone.classList.add("drop-hover");
});
document.addEventListener("dragleave", function(e){
  var zone = kanbanDropZone(e.target); if (zone && !zone.contains(e.relatedTarget)) zone.classList.remove("drop-hover");
});
document.addEventListener("drop", async function(e){
  var zone = kanbanDropZone(e.target); if (!zone || kanbanDragId==null) return;
  e.preventDefault(); zone.classList.remove("drop-hover");
  var bodyEl = zone.querySelector("[data-status]"); var newStatus = bodyEl ? bodyEl.getAttribute("data-status") : null;
  if (newStatus==null || newStatus==="main") { kanbanDragId = null; return; }
  var t = TASKS.find(function(x){ return x.id===kanbanDragId; }); var movingId = kanbanDragId; kanbanDragId = null;
  if (!t) return;
  var prev = t.status; t.status = (newStatus==="unsorted") ? null : newStatus;
  renderKanban();
  var payload = { action:"update", id: movingId, title: t.title, description: (t.description||""), due_date: (t.due_date||""), status: t.status, priority: (t.priority||"") };
  try { var r = await api(payload); if (!r || !r.ok){ t.status = prev; renderKanban(); } }
  catch(err){ t.status = prev; renderKanban(); }
});
(function(){
  var vt = document.getElementById("viewToggle"); if (!vt) return;
  function setView(view){
    document.body.classList.toggle("kanban-mode", view==="kanban");
    vt.querySelectorAll("button").forEach(function(b){ b.classList.toggle("active", b.getAttribute("data-view")===view); });
    try { localStorage.setItem("vsa-tasks-view", view); } catch(_){}
    if (view==="kanban"){ renderKanban(); if (typeof refresh==="function") refresh(); }
  }
  vt.addEventListener("click", function(e){ var b=e.target.closest("button"); if(!b) return; setView(b.getAttribute("data-view")); });
  var ih = document.getElementById("kanbanIntakeHead");
  if (ih) ih.addEventListener("click", function(){ var box=document.getElementById("kanbanIntake"); if(box) box.classList.toggle("collapsed"); var ch=document.getElementById("intakeChevron"); if(ch) ch.textContent = box.classList.contains("collapsed")?"\u25B8":"\u25BE"; });
  var saved=null; try { saved = localStorage.getItem("vsa-tasks-view"); } catch(_){}
  var initialView = (saved==="kanban") ? "kanban" : "grid";
  setView(initialView);
  if (initialView==="kanban"){
    var tries = 0;
    var kbTimer = setInterval(function(){
      tries++;
      if (typeof TASKS!=="undefined" && TASKS.length){ renderKanban(); clearInterval(kbTimer); }
      else if (tries>=20){ clearInterval(kbTimer); }
    }, 150);
  }
})();
</script>
</body>
</html>
