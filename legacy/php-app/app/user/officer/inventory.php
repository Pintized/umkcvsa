<?php declare(strict_types=1);
// ============================================================
// UMKC VSA - Officer Inventory Tracking page
// Full-page workspace view. Data via inventory-api.php.
// ============================================================
require_once __DIR__ . '/../../auth.php';
require_login();
require_officer();

$user  = current_user();
$panel = isset($_GET['panel']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory | UMKC VSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
(function(){try{var t=localStorage.getItem('vsa-theme');if(t==='dark'){document.documentElement.classList.add('dark-mode');}}catch(e){}})();
</script>
<style>
  :root {
    --vsa-navy:#16314d;
    --vsa-navy-2:#0f243b;
    --vsa-red:#c8202f;
    --vsa-red-2:#a81724;
    --vsa-gold:#f6c451;
    --vsa-green:#24865b;
    --vsa-amber:#b66b00;
    --vsa-danger:#b72634;
    --vsa-bg:#f4f7fb;
    --vsa-panel:#ffffff;
    --vsa-panel-2:#f9fbfe;
    --vsa-text:#1d2935;
    --vsa-muted:#627386;
    --vsa-line:#dce5ef;
    --vsa-shadow:0 18px 40px rgba(22,49,77,.10);
    --vsa-shadow-soft:0 10px 24px rgba(22,49,77,.07);
  }

  html.dark-mode {
    --vsa-bg:#0b1521;
    --vsa-panel:#111f2e;
    --vsa-panel-2:#0d1a28;
    --vsa-text:#e8eef6;
    --vsa-muted:#9cafc4;
    --vsa-line:#25384b;
    --vsa-shadow:0 18px 42px rgba(0,0,0,.38);
    --vsa-shadow-soft:0 10px 24px rgba(0,0,0,.24);
  }
  html.dark-mode, html.dark-mode body { background:#0b1521 !important; }
  html.dark-mode .ws-aside, html.dark-mode .rail-card, html.dark-mode .table-panel { background:var(--vsa-panel); }

  * { box-sizing:border-box; margin:0; padding:0; }
  html, body { min-height:100%; }
  body {
    background:
      radial-gradient(circle at top left, rgba(200,32,47,.10), transparent 26rem),
      radial-gradient(circle at top right, rgba(22,49,77,.12), transparent 28rem),
      var(--vsa-bg);
    color:var(--vsa-text);
    font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }

  button, input, select, textarea { font:inherit; }
  button { -webkit-tap-highlight-color:transparent; }

  .page-shell {
    width:100%;
    min-height:calc(100vh - 0px);
    padding:18px;
  }

  body.panel-mode .page-shell { padding:14px; }

  .inventory-app {
    min-height:calc(100vh - 36px);
    display:grid;
    grid-template-columns:280px minmax(0, 1fr);
    border:1px solid var(--vsa-line);
    border-radius:26px;
    overflow:hidden;
    background:var(--vsa-panel);
    box-shadow:var(--vsa-shadow);
  }

  body.panel-mode .inventory-app {
    min-height:calc(100vh - 28px);
    border-radius:20px;
  }

  .side-rail {
    background:
      linear-gradient(180deg, rgba(22,49,77,.98), rgba(15,36,59,.98)),
      var(--vsa-navy);
    color:#fff;
    padding:22px;
    display:flex;
    flex-direction:column;
    gap:18px;
    position:relative;
    overflow:hidden;
  }

  .side-rail::before,
  .side-rail::after {
    content:"";
    position:absolute;
    border-radius:999px;
    pointer-events:none;
  }

  .side-rail::before {
    width:220px;
    height:220px;
    background:rgba(200,32,47,.25);
    top:-90px;
    right:-100px;
    filter:blur(2px);
  }

  .side-rail::after {
    width:150px;
    height:150px;
    background:rgba(246,196,81,.16);
    bottom:18px;
    left:-70px;
  }

  .brand-block,
  .rail-card,
  .rail-footer { position:relative; z-index:1; }

  .brand-kicker {
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:rgba(255,255,255,.76);
    font-size:.72rem;
    font-weight:800;
    letter-spacing:.12em;
    text-transform:uppercase;
    margin-bottom:10px;
  }

  .brand-kicker::before {
    content:"";
    width:9px;
    height:9px;
    border-radius:50%;
    background:var(--vsa-red);
    box-shadow:0 0 0 5px rgba(200,32,47,.18);
  }

  .brand-block h1 {
    font-size:1.85rem;
    line-height:1.02;
    letter-spacing:-.04em;
    margin-bottom:8px;
  }

  .brand-block p {
    color:rgba(255,255,255,.70);
    line-height:1.45;
    font-size:.92rem;
  }

  .rail-card {
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.08);
    border-radius:20px;
    padding:16px;
    backdrop-filter:blur(12px);
  }

  .rail-card h2 {
    font-size:.77rem;
    letter-spacing:.11em;
    text-transform:uppercase;
    color:rgba(255,255,255,.62);
    margin-bottom:12px;
  }

  .health-grid {
    display:grid;
    gap:10px;
  }

  .health-item {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:11px 12px;
    border-radius:14px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
  }

  .health-item span:first-child {
    color:rgba(255,255,255,.70);
    font-size:.82rem;
  }

  .health-item strong {
    font-size:1.15rem;
    font-variant-numeric:tabular-nums;
  }

  .cat-list {
    display:grid;
    gap:8px;
  }

  .cat-btn {
    width:100%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    border:1px solid rgba(255,255,255,.09);
    background:rgba(255,255,255,.06);
    color:rgba(255,255,255,.80);
    border-radius:14px;
    padding:10px 11px;
    cursor:pointer;
    transition:background .18s ease, border-color .18s ease, transform .18s ease;
  }

  .cat-btn:hover,
  .cat-btn.active {
    background:rgba(255,255,255,.14);
    border-color:rgba(255,255,255,.20);
    transform:translateX(2px);
  }

  .cat-count {
    min-width:26px;
    height:22px;
    padding:0 8px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    background:rgba(255,255,255,.12);
    font-size:.75rem;
    font-weight:800;
  }

  .rail-footer {
    margin-top:auto;
    color:rgba(255,255,255,.58);
    font-size:.78rem;
    line-height:1.45;
    border-top:1px solid rgba(255,255,255,.12);
    padding-top:16px;
  }

  .main-workspace {
    min-width:0;
    display:grid;
    grid-template-rows:auto auto minmax(0, 1fr);
    background:var(--vsa-panel-2);
  }

  .ws-header {
    min-height:76px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    padding:18px 22px;
    background:rgba(255,255,255,.78);
    border-bottom:1px solid var(--vsa-line);
    backdrop-filter:blur(18px);
    position:sticky;
    top:0;
    z-index:10;
  }

  html.dark-mode .ws-header { background:rgba(17,31,46,.78); }

  .top-title { min-width:0; }
  .top-title h2 {
    font-size:1.18rem;
    letter-spacing:-.02em;
    margin-bottom:2px;
  }
  .top-title p {
    color:var(--vsa-muted);
    font-size:.86rem;
  }

  .top-actions {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    justify-content:flex-end;
  }

  .btn {
    border:none;
    border-radius:13px;
    padding:10px 15px;
    font-size:.86rem;
    cursor:pointer;
    font-weight:800;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition:transform .16s ease, box-shadow .16s ease, background .16s ease, border-color .16s ease;
  }

  .btn:hover { transform:translateY(-1px); }
  .btn.primary {
    background:linear-gradient(135deg, var(--vsa-red), var(--vsa-red-2));
    color:#fff;
    box-shadow:0 12px 24px rgba(200,32,47,.22);
  }
  .btn.secondary { background:#5b6b7b; color:#fff; }
  .btn.ghost {
    background:transparent;
    color:var(--vsa-muted);
    border:1px solid var(--vsa-line);
  }
  .btn.ghost:hover { color:var(--vsa-text); background:rgba(98,115,134,.08); }

  .command-bar {
    padding:16px 22px;
    display:grid;
    grid-template-columns:minmax(220px, 1fr) 190px 160px auto;
    gap:12px;
    border-bottom:1px solid var(--vsa-line);
    background:var(--vsa-panel);
  }

  .searchbox,
  .selectbox {
    min-height:44px;
    display:flex;
    align-items:center;
    gap:10px;
    border:1px solid var(--vsa-line);
    border-radius:15px;
    background:var(--vsa-panel-2);
    padding:0 13px;
  }

  .searchbox svg { flex:0 0 auto; color:var(--vsa-muted); }

  .searchbox input,
  .selectbox select {
    width:100%;
    border:0;
    outline:0;
    background:transparent;
    color:var(--vsa-text);
    min-width:0;
    font-size:.9rem;
  }

  .selectbox select { cursor:pointer; }

  .mini-stat {
    min-height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    border-radius:15px;
    border:1px solid var(--vsa-line);
    background:var(--vsa-panel-2);
    color:var(--vsa-muted);
    font-size:.84rem;
    font-weight:750;
  }

  .mini-stat strong { color:var(--vsa-text); font-variant-numeric:tabular-nums; }

  .table-zone {
    min-height:0;
    padding:0 22px 22px;
    overflow:hidden;
    display:grid;
  }

  .table-panel {
    min-height:0;
    display:grid;
    grid-template-rows:auto minmax(0, 1fr);
    margin-top:16px;
    border:1px solid var(--vsa-line);
    border-radius:20px;
    background:var(--vsa-panel);
    box-shadow:var(--vsa-shadow-soft);
    overflow:hidden;
  }

  .table-toolbar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    padding:12px 14px;
    border-bottom:1px solid var(--vsa-line);
    background:linear-gradient(180deg, var(--vsa-panel), var(--vsa-panel-2));
  }

  .table-toolbar p {
    color:var(--vsa-muted);
    font-size:.83rem;
  }

  .view-tabs {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .tab-btn {
    border:1px solid var(--vsa-line);
    background:transparent;
    color:var(--vsa-muted);
    border-radius:999px;
    padding:7px 11px;
    cursor:pointer;
    font-size:.78rem;
    font-weight:800;
  }

  .tab-btn.active {
    color:#fff;
    border-color:var(--vsa-navy);
    background:var(--vsa-navy);
  }

  html.dark-mode .tab-btn.active {
    border-color:#395a78;
    background:#223d59;
  }

  .inv-table-wrap {
    min-height:0;
    overflow:auto;
  }

  table.inv {
    width:100%;
    min-width:940px;
    border-collapse:separate;
    border-spacing:0;
    font-size:.9rem;
  }

  table.inv thead th {
    position:sticky;
    top:0;
    z-index:2;
    text-align:left;
    font-size:.68rem;
    letter-spacing:.09em;
    text-transform:uppercase;
    color:var(--vsa-muted);
    font-weight:900;
    padding:13px 16px;
    border-bottom:1px solid var(--vsa-line);
    background:var(--vsa-panel);
    white-space:nowrap;
  }

  table.inv tbody td {
    padding:14px 16px;
    border-bottom:1px solid var(--vsa-line);
    color:var(--vsa-text);
    vertical-align:middle;
  }

  table.inv tbody tr:last-child td { border-bottom:none; }
  table.inv tbody tr { transition:background .15s ease; }
  table.inv tbody tr:hover { background:rgba(22,49,77,.035); }
  html.dark-mode table.inv tbody tr:hover { background:rgba(255,255,255,.035); }

  .item-cell {
    display:grid;
    gap:3px;
    min-width:190px;
  }

  .item-name {
    font-weight:850;
    letter-spacing:-.01em;
  }

  .item-note {
    max-width:320px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:var(--vsa-muted);
    font-size:.78rem;
  }

  .category-pill,
  .location-pill {
    display:inline-flex;
    align-items:center;
    width:max-content;
    max-width:180px;
    min-height:26px;
    border:1px solid var(--vsa-line);
    border-radius:999px;
    padding:3px 9px;
    background:var(--vsa-panel-2);
    color:var(--vsa-muted);
    font-size:.78rem;
    font-weight:750;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .qty-cell {
    width:max-content;
    display:grid;
    grid-template-columns:30px 48px 30px;
    align-items:center;
    border:1px solid var(--vsa-line);
    border-radius:13px;
    overflow:hidden;
    background:var(--vsa-panel-2);
  }

  .qty-val {
    min-width:48px;
    text-align:center;
    font-weight:900;
    font-variant-numeric:tabular-nums;
    color:var(--vsa-text);
  }

  .qbtn {
    width:30px;
    height:30px;
    border:0;
    border-radius:0;
    background:transparent;
    color:var(--vsa-muted);
    cursor:pointer;
    line-height:1;
    font-size:1rem;
    font-weight:900;
  }

  .qbtn:hover { background:rgba(22,49,77,.08); color:var(--vsa-text); }
  html.dark-mode .qbtn:hover { background:rgba(255,255,255,.08); }

  .unit-text { color:var(--vsa-muted); font-weight:750; }

  .tag {
    display:inline-flex;
    align-items:center;
    gap:7px;
    min-height:28px;
    padding:4px 10px;
    border-radius:999px;
    font-size:.74rem;
    font-weight:900;
    letter-spacing:.01em;
    white-space:nowrap;
  }

  .tag::before {
    content:"";
    width:7px;
    height:7px;
    border-radius:50%;
  }

  .tag.ok { color:var(--vsa-green); background:rgba(36,134,91,.11); }
  .tag.ok::before { background:var(--vsa-green); }
  .tag.low { color:var(--vsa-amber); background:rgba(182,107,0,.13); }
  .tag.low::before { background:var(--vsa-amber); }
  .tag.out { color:var(--vsa-danger); background:rgba(183,38,52,.13); }
  .tag.out::before { background:var(--vsa-danger); }

  tr.row-out td { background:rgba(183,38,52,.035); }
  tr.row-low td { background:rgba(182,107,0,.035); }

  .row-actions {
    display:flex;
    gap:8px;
    justify-content:flex-end;
  }

  .link-btn {
    border:1px solid var(--vsa-line);
    background:var(--vsa-panel-2);
    cursor:pointer;
    font-size:.78rem;
    color:var(--vsa-muted);
    padding:7px 10px;
    border-radius:10px;
    font-weight:850;
  }

  .link-btn:hover { color:var(--vsa-text); background:rgba(22,49,77,.06); }
  .link-btn.danger:hover { color:#fff; border-color:var(--vsa-danger); background:var(--vsa-danger); }

  .empty {
    padding:44px 16px;
    text-align:center;
    color:var(--vsa-muted);
    font-size:.9rem;
  }

  .drawer-back {
    display:none;
    position:fixed;
    inset:0;
    z-index:50;
    background:rgba(3,9,16,.45);
    backdrop-filter:blur(5px);
  }

  .drawer-back.open { display:block; }

  .drawer {
    position:absolute;
    top:16px;
    right:16px;
    bottom:16px;
    width:min(520px, calc(100vw - 32px));
    display:grid;
    grid-template-rows:auto minmax(0, 1fr) auto;
    background:var(--vsa-panel);
    color:var(--vsa-text);
    border:1px solid var(--vsa-line);
    border-radius:24px;
    box-shadow:0 26px 80px rgba(0,0,0,.28);
    overflow:hidden;
    animation:drawerIn .22s ease both;
  }

  @keyframes drawerIn {
    from { transform:translateX(20px); opacity:0; }
    to { transform:translateX(0); opacity:1; }
  }

  .drawer-head {
    padding:20px 22px;
    border-bottom:1px solid var(--vsa-line);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    background:linear-gradient(180deg, var(--vsa-panel), var(--vsa-panel-2));
  }

  .drawer-head h2 { font-size:1.18rem; letter-spacing:-.02em; }
  .drawer-head p { margin-top:3px; color:var(--vsa-muted); font-size:.84rem; }

  .icon-btn {
    width:36px;
    height:36px;
    border-radius:12px;
    border:1px solid var(--vsa-line);
    background:transparent;
    color:var(--vsa-muted);
    cursor:pointer;
    font-size:1.2rem;
    line-height:1;
  }
  .icon-btn:hover { color:var(--vsa-text); background:rgba(98,115,134,.08); }

  .drawer-body {
    min-height:0;
    overflow:auto;
    padding:20px 22px;
  }

  .field { margin-bottom:14px; }
  .field label {
    display:block;
    font-size:.75rem;
    color:var(--vsa-muted);
    margin-bottom:6px;
    font-weight:850;
    letter-spacing:.02em;
  }
  .field label .req { color:var(--vsa-red); }

  .field input,
  .field select,
  .field textarea {
    width:100%;
    padding:11px 12px;
    border:1px solid var(--vsa-line);
    border-radius:13px;
    background:var(--vsa-panel-2);
    color:var(--vsa-text);
    outline:0;
    font-size:.9rem;
  }

  .field input:focus,
  .field select:focus,
  .field textarea:focus {
    border-color:rgba(200,32,47,.55);
    box-shadow:0 0 0 4px rgba(200,32,47,.10);
  }

  .field textarea { min-height:96px; resize:vertical; }
  .field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .field-row.three { grid-template-columns:1fr 1fr 1fr; }

  .drawer-actions {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:16px 22px;
    border-top:1px solid var(--vsa-line);
    background:var(--vsa-panel);
  }

  .modal-err {
    color:var(--vsa-danger);
    font-size:.82rem;
    margin-top:4px;
    min-height:1em;
  }

  .modal-back {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(3,9,16,.45);
    z-index:60;
    align-items:center;
    justify-content:center;
    padding:20px;
    backdrop-filter:blur(5px);
  }

  .modal-back.open { display:flex; }

  .modal {
    background:var(--vsa-panel);
    color:var(--vsa-text);
    width:100%;
    max-width:430px;
    border-radius:22px;
    border:1px solid var(--vsa-line);
    padding:22px;
    box-shadow:0 24px 70px rgba(0,0,0,.24);
  }

  .modal h2 { font-size:1.12rem; margin-bottom:8px; }
  .modal p { color:var(--vsa-muted); font-size:.92rem; line-height:1.5; }
  .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }

  @media (max-width:1080px) {
    .inventory-app { grid-template-columns:1fr; }
    .side-rail {
      min-height:auto;
      display:grid;
      grid-template-columns:1fr 1fr;
      align-items:start;
    }
    .brand-block { grid-column:1 / -1; }
    .rail-footer { display:none; }
    .command-bar { grid-template-columns:1fr 180px 140px auto; }
  }

  @media (max-width:760px) {
    .page-shell { padding:10px; }
    .inventory-app { min-height:calc(100vh - 20px); border-radius:18px; }
    .side-rail { display:block; padding:18px; }
    .rail-card { margin-top:14px; }
    .cat-list { grid-template-columns:1fr 1fr; }
    .ws-header { align-items:flex-start; flex-direction:column; padding:16px; }
    .top-actions { width:100%; justify-content:stretch; }
    .top-actions .btn { flex:1; }
    .command-bar { grid-template-columns:1fr; padding:14px 16px; }
    .table-zone { padding:0 16px 16px; }
    .table-toolbar { align-items:flex-start; flex-direction:column; }
    .field-row,
    .field-row.three { grid-template-columns:1fr; }
    .drawer { top:8px; right:8px; bottom:8px; width:calc(100vw - 16px); border-radius:18px; }
  }
</style>
</head>
<body<?php echo $panel ? ' class="panel-mode"' : ''; ?>>
  <?php $officerActive = 'inventory'; include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-chrome.php'; ?>

  <main class="page-shell">
    <section class="inventory-app" aria-label="Inventory workspace">
      <aside class="side-rail" aria-label="Inventory overview">
        <div class="brand-block">
          <div class="brand-kicker">UMKC VSA Officer</div>
          <h1>Inventory Workspace</h1>
        </div>

        <div class="rail-card">
          <h2>Stock health</h2>
          <div class="health-grid">
            <div class="health-item"><span>Total items</span><strong id="statTotal">0</strong></div>
            <div class="health-item"><span>Low stock</span><strong id="statLow">0</strong></div>
            <div class="health-item"><span>Out of stock</span><strong id="statOut">0</strong></div>
          </div>
        </div>

        <div class="rail-card">
          <h2>Categories</h2>
          <div class="cat-list" id="categoryRail"></div>
        </div>

        <div class="rail-footer">
          Tip: use the left category buttons for quick filtering, then update counts directly inside the table.
        </div>
      </aside>

      <section class="main-workspace">
        <header class="ws-header">
          <div class="top-title">
            <h2>Inventory Management</h2>
          </div>
          <div class="top-actions">
            <button class="btn ghost" id="refreshBtn" type="button">Refresh</button>
            <button class="btn primary" id="addBtn" type="button">+ Add Item</button>
          </div>
        </header>

        <div class="command-bar">
          <label class="searchbox" aria-label="Search inventory items">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m21 21-4.35-4.35m1.35-5.65a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="text" id="search" placeholder="Search by item, location, category, or notes...">
          </label>

          <label class="selectbox" aria-label="Category filter">
            <select id="catFilter"><option value="">All categories</option></select>
          </label>

          <div class="mini-stat"><strong id="visibleCount">0</strong> visible</div>
          <button class="btn ghost" id="clearFiltersBtn" type="button">Clear filters</button>
        </div>

        <div class="table-zone">
          <div class="table-panel">
            <div class="table-toolbar">
              <p id="tableSummary">Showing inventory items.</p>
              <div class="view-tabs" aria-label="Status filters">
                <button class="tab-btn active" type="button" data-status="all">All</button>
                <button class="tab-btn" type="button" data-status="low">Low</button>
                <button class="tab-btn" type="button" data-status="out">Out</button>
                <button class="tab-btn" type="button" data-status="ok">In stock</button>
              </div>
            </div>

            <div class="inv-table-wrap">
              <table class="inv">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody id="invBody"></tbody>
              </table>
              <div class="empty" id="emptyState" style="display:none">No inventory items yet. Click "Add Item" to create one.</div>
            </div>
          </div>
        </div>
      </section>
    </section>
  </main>

  <!-- Add/Edit drawer -->
  <div class="drawer-back" id="modalBack">
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="drawer-head">
        <div>
          <h2 id="modalTitle">Add Item</h2>
          <p>Update the item details, quantity, threshold, and storage notes.</p>
        </div>
        <button class="icon-btn" id="drawerCloseBtn" type="button" aria-label="Close">&times;</button>
      </div>

      <div class="drawer-body">
        <input type="hidden" id="f_id">
        <div class="field"><label>Name <span class="req">*</span></label><input type="text" id="f_name" autocomplete="off"></div>
        <div class="field-row">
          <div class="field"><label>Category <span class="req">*</span></label><select id="f_category"></select></div>
          <div class="field"><label>Location <span class="req">*</span></label><input type="text" id="f_location" autocomplete="off"></div>
        </div>
        <div class="field-row three">
          <div class="field"><label>Quantity</label><input type="number" id="f_quantity" value="0" min="0"></div>
          <div class="field"><label>Unit</label><input type="text" id="f_unit" placeholder="pcs, boxes..." autocomplete="off"></div>
          <div class="field"><label>Low-stock threshold</label><input type="number" id="f_threshold" value="0" min="0"></div>
        </div>
        <div class="field"><label>Notes</label><textarea id="f_notes" placeholder="Optional notes about condition, storage, event use, etc."></textarea></div>
        <div class="modal-err" id="modalErr"></div>
      </div>

      <div class="drawer-actions">
        <button class="btn ghost" id="cancelBtn" type="button">Cancel</button>
        <button class="btn primary" id="saveBtn" type="button">Save Item</button>
      </div>
    </aside>
  </div>

  <div class="modal-back" id="delModalBack">
    <div class="modal">
      <h2>Delete item</h2>
      <p id="delModalMsg"></p>
      <div class="modal-actions">
        <button class="btn ghost" id="delCancelBtn" type="button">Cancel</button>
        <button class="btn secondary" id="delConfirmBtn" type="button">Delete</button>
      </div>
    </div>
  </div>
<script>
var API = '/app/user/officer/inventory-api.php';
var CATEGORIES = ['Apparel','Supplies','Event','Food & Drink','Equipment','Other'];
var ITEMS = [];
var STATUS_FILTER = 'all';

function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

function api(payload){
  return fetch(API, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)})
    .then(function(r){ return r.json(); });
}

function statusFor(it){
  if (it.quantity <= 0) return {cls:'out', label:'Out of stock'};
  if (it.quantity <= it.low_stock_threshold) return {cls:'low', label:'Low'};
  return {cls:'ok', label:'In stock'};
}

function categoryCount(category){
  return ITEMS.filter(function(it){ return it.category === category; }).length;
}

function fillCategorySelects(){
  var opts = CATEGORIES.map(function(c){ return '<option value="'+esc(c)+'">'+esc(c)+'</option>'; }).join('');
  document.getElementById('f_category').innerHTML = opts;
  document.getElementById('catFilter').innerHTML = '<option value="">All categories</option>' + opts;
  renderCategoryRail();
}

function renderCategoryRail(){
  var active = document.getElementById('catFilter').value || '';
  var rail = document.getElementById('categoryRail');
  rail.innerHTML = '<button class="cat-btn '+(!active?'active':'')+'" type="button" data-cat=""><span>All categories</span><span class="cat-count">'+ITEMS.length+'</span></button>' +
    CATEGORIES.map(function(c){
      return '<button class="cat-btn '+(active===c?'active':'')+'" type="button" data-cat="'+esc(c)+'"><span>'+esc(c)+'</span><span class="cat-count">'+categoryCount(c)+'</span></button>';
    }).join('');
}

function updateStats(rows){
  var low = 0, out = 0, ok = 0;
  ITEMS.forEach(function(it){
    var st = statusFor(it).cls;
    if (st === 'out') out++;
    else if (st === 'low') low++;
    else ok++;
  });
  document.getElementById('statTotal').textContent = ITEMS.length;
  document.getElementById('statLow').textContent = low;
  document.getElementById('statOut').textContent = out;
  document.getElementById('visibleCount').textContent = rows.length;
  document.getElementById('tableSummary').textContent = 'Showing ' + rows.length + ' of ' + ITEMS.length + ' inventory item' + (ITEMS.length === 1 ? '.' : 's.');
}

function filteredRows(){
  var q = (document.getElementById('search').value||'').toLowerCase();
  var cat = document.getElementById('catFilter').value||'';
  return ITEMS.filter(function(it){
    var st = statusFor(it).cls;
    if (cat && it.category !== cat) return false;
    if (STATUS_FILTER !== 'all' && st !== STATUS_FILTER) return false;
    if (q){
      var hay = (it.name+' '+it.category+' '+it.location+' '+(it.notes||'')+' '+(it.unit||'')).toLowerCase();
      if (hay.indexOf(q) < 0) return false;
    }
    return true;
  });
}

function renderTable(){
  var rows = filteredRows();
  var body = document.getElementById('invBody');
  var empty = document.getElementById('emptyState');
  updateStats(rows);
  renderCategoryRail();

  if (!rows.length){
    body.innerHTML='';
    empty.style.display='block';
    empty.textContent = ITEMS.length ? 'No items match your current filters.' : 'No inventory items yet. Click "Add Item" to create one.';
    return;
  }

  empty.style.display='none';
  body.innerHTML = rows.map(function(it){
    var st = statusFor(it);
    var rowCls = st.cls==='out' ? 'row-out' : (st.cls==='low' ? 'row-low' : '');
    var notes = it.notes ? '<span class="item-note">'+esc(it.notes)+'</span>' : '<span class="item-note">No notes added</span>';
    return '<tr class="'+rowCls+'" data-id="'+it.id+'">'+
      '<td><div class="item-cell"><span class="item-name">'+esc(it.name)+'</span>'+notes+'</div></td>'+
      '<td><span class="category-pill">'+esc(it.category)+'</span></td>'+
      '<td><span class="qty-val">'+it.quantity+'</span></td>'+
      '<td><span class="unit-text">'+esc(it.unit||'—')+'</span></td>'+
      '<td><span class="location-pill">'+esc(it.location)+'</span></td>'+
      '<td><span class="tag '+st.cls+'">'+esc(st.label)+'</span></td>'+
      '<td><div class="row-actions"><button class="link-btn" data-edit="'+it.id+'">Edit</button><button class="link-btn danger" data-del="'+it.id+'">Delete</button></div></td>'+
      '</tr>';
  }).join('');
}

function refresh(){
  return api({action:'list'}).then(function(j){
    if(j&&j.ok){ ITEMS = j.items||[]; renderTable(); }
  });
}

function openModal(item){
  document.getElementById('modalErr').textContent='';
  document.getElementById('modalTitle').textContent = item ? 'Edit Item' : 'Add Item';
  document.getElementById('f_id').value = item ? item.id : '';
  document.getElementById('f_name').value = item ? item.name : '';
  document.getElementById('f_category').value = item ? item.category : (CATEGORIES[0]||'');
  document.getElementById('f_location').value = item ? item.location : '';
  document.getElementById('f_quantity').value = item ? item.quantity : 0;
  document.getElementById('f_unit').value = item ? (item.unit||'') : '';
  document.getElementById('f_threshold').value = item ? item.low_stock_threshold : 0;
  document.getElementById('f_notes').value = item ? (item.notes||'') : '';
  document.getElementById('modalBack').classList.add('open');
  setTimeout(function(){ document.getElementById('f_name').focus(); }, 60);
}
function closeModal(){ document.getElementById('modalBack').classList.remove('open'); }

var PENDING_DEL_ID = null;
function openDeleteModal(id, nm){
  PENDING_DEL_ID = id;
  document.getElementById('delModalMsg').textContent = 'Delete "' + nm + '"? This cannot be undone.';
  document.getElementById('delModalBack').classList.add('open');
}
function closeDeleteModal(){ PENDING_DEL_ID = null; document.getElementById('delModalBack').classList.remove('open'); }
function confirmDelete(){
  var did = PENDING_DEL_ID;
  if (did==null){ closeDeleteModal(); return; }
  api({action:'delete', id:did}).then(function(j){ if(j&&j.ok){ ITEMS=j.items||ITEMS; renderTable(); } closeDeleteModal(); });
}

function saveItem(){
  var id = document.getElementById('f_id').value;
  var payload = {
    action: id ? 'update' : 'create',
    name: document.getElementById('f_name').value.trim(),
    category: document.getElementById('f_category').value,
    location: document.getElementById('f_location').value.trim(),
    quantity: parseInt(document.getElementById('f_quantity').value,10)||0,
    unit: document.getElementById('f_unit').value.trim(),
    low_stock_threshold: parseInt(document.getElementById('f_threshold').value,10)||0,
    notes: document.getElementById('f_notes').value.trim()
  };
  if (id) payload.id = parseInt(id,10);
  if (!payload.name || !payload.category || !payload.location){
    document.getElementById('modalErr').textContent = 'Name, category and location are required.';
    return;
  }
  api(payload).then(function(j){
    if (j && j.ok){ ITEMS = j.items||ITEMS; renderTable(); closeModal(); }
    else { document.getElementById('modalErr').textContent = (j&&j.error) ? j.error : 'Could not save.'; }
  });
}

document.getElementById('addBtn').addEventListener('click', function(){ openModal(null); });
document.getElementById('refreshBtn').addEventListener('click', refresh);
document.getElementById('cancelBtn').addEventListener('click', closeModal);
document.getElementById('drawerCloseBtn').addEventListener('click', closeModal);
document.getElementById('saveBtn').addEventListener('click', saveItem);
document.getElementById('modalBack').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
document.getElementById('search').addEventListener('input', renderTable);
document.getElementById('catFilter').addEventListener('change', renderTable);
document.getElementById('clearFiltersBtn').addEventListener('click', function(){
  document.getElementById('search').value = '';
  document.getElementById('catFilter').value = '';
  STATUS_FILTER = 'all';
  document.querySelectorAll('.tab-btn').forEach(function(btn){ btn.classList.toggle('active', btn.getAttribute('data-status') === 'all'); });
  renderTable();
});

document.getElementById('categoryRail').addEventListener('click', function(e){
  var btn = e.target.closest && e.target.closest('.cat-btn');
  if (!btn) return;
  document.getElementById('catFilter').value = btn.getAttribute('data-cat') || '';
  renderTable();
});

document.querySelectorAll('.tab-btn').forEach(function(btn){
  btn.addEventListener('click', function(){
    STATUS_FILTER = btn.getAttribute('data-status') || 'all';
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.toggle('active', b === btn); });
    renderTable();
  });
});

document.getElementById('delCancelBtn').addEventListener('click', closeDeleteModal);
document.getElementById('delConfirmBtn').addEventListener('click', confirmDelete);
document.getElementById('delModalBack').addEventListener('click', function(e){ if(e.target===this) closeDeleteModal(); });

document.getElementById('invBody').addEventListener('click', function(e){
  var t = e.target;
  var adj = t.getAttribute && t.getAttribute('data-adj');
  if (adj){
    var id=parseInt(t.getAttribute('data-id'),10);
    api({action:'adjust', id:id, delta:parseInt(adj,10)}).then(function(j){ if(j&&j.ok){ ITEMS=j.items||ITEMS; renderTable(); } });
    return;
  }
  var ed = t.getAttribute && t.getAttribute('data-edit');
  if (ed){ var it = ITEMS.filter(function(x){return x.id===parseInt(ed,10);})[0]; if(it) openModal(it); return; }
  var del = t.getAttribute && t.getAttribute('data-del');
  if (del){ var did=parseInt(del,10); var item=ITEMS.filter(function(x){return x.id===did;})[0]; var nm=item?item.name:('#'+did); openDeleteModal(did, nm); return; }
});

document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') { closeModal(); closeDeleteModal(); }
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); document.getElementById('search').focus(); }
});

fillCategorySelects();
refresh();
</script>
</body>
</html>
