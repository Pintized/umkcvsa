<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_login();
require_officer();

$pdo = db();
$me  = current_user();
$uid = (int)($me['id'] ?? 0);
$msg = '';
$err = '';
$panel = isset($_GET['panel']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $date = trim((string)($_POST['event_date'] ?? ''));
        $st   = trim((string)($_POST['start_time'] ?? ''));
        $et   = trim((string)($_POST['end_time'] ?? ''));
        $loc  = trim((string)($_POST['location'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($name === '' || $date === '') {
            $err = 'Event name and date are required.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO app_events (name, event_date, start_time, end_time, location, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $date, ($st !== '' ? $st : null), ($et !== '' ? $et : null), ($loc !== '' ? $loc : null), ($desc !== '' ? $desc : null), $uid]);
            $msg = 'Event added.';
        }
    } elseif ($action === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $date = trim((string)($_POST['event_date'] ?? ''));
        $st   = trim((string)($_POST['start_time'] ?? ''));
        $et   = trim((string)($_POST['end_time'] ?? ''));
        $loc  = trim((string)($_POST['location'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($id <= 0 || $name === '' || $date === '') {
            $err = 'Event name and date are required.';
        } else {
            $stmt = $pdo->prepare('UPDATE app_events SET name=?, event_date=?, start_time=?, end_time=?, location=?, description=? WHERE id=?');
            $stmt->execute([$name, $date, ($st !== '' ? $st : null), ($et !== '' ? $et : null), ($loc !== '' ? $loc : null), ($desc !== '' ? $desc : null), $id]);
            $msg = 'Event updated.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM app_events WHERE id=?');
            $stmt->execute([$id]);
            $msg = 'Event deleted.';
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM app_events WHERE id=?');
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$events = $pdo->query('SELECT * FROM app_events ORDER BY event_date ASC, start_time ASC')->fetchAll(PDO::FETCH_ASSOC);
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_time(?string $t): string { return $t !== null && $t !== '' ? substr($t, 0, 5) : ''; }

$todayStr = date('Y-m-d');
$totalEv = count($events);
$upCount = 0;
$pastCount = 0;
$todayCount = 0;
$nextEvent = null;
foreach ($events as $__e) {
    $evDate = (string)($__e['event_date'] ?? '');
    if ($evDate === $todayStr) { $todayCount++; }
    if ($evDate >= $todayStr) {
        $upCount++;
        if ($nextEvent === null) { $nextEvent = $__e; }
    } else {
        $pastCount++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Events | UMKC VSA</title>
<?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-head.php'; ?>
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

  button, input, textarea { font:inherit; }
  button { -webkit-tap-highlight-color:transparent; }

  .page-shell { width:100%; min-height:100vh; padding:18px; }
  body.panel-mode .page-shell { padding:14px; }

  .events-app {
    min-height:calc(100vh - 36px);
    display:grid;
    grid-template-columns:280px minmax(0, 1fr);
    border:1px solid var(--vsa-line);
    border-radius:26px;
    overflow:hidden;
    background:var(--vsa-panel);
    box-shadow:var(--vsa-shadow);
  }
  body.panel-mode .events-app { min-height:calc(100vh - 28px); border-radius:20px; }

  .side-rail {
    background:linear-gradient(180deg, rgba(22,49,77,.98), rgba(15,36,59,.98)), var(--vsa-navy);
    color:#fff;
    padding:22px;
    display:flex;
    flex-direction:column;
    gap:18px;
    position:relative;
    overflow:hidden;
  }
  .side-rail::before, .side-rail::after { content:""; position:absolute; border-radius:999px; pointer-events:none; }
  .side-rail::before { width:220px; height:220px; background:rgba(200,32,47,.25); top:-90px; right:-100px; filter:blur(2px); }
  .side-rail::after { width:150px; height:150px; background:rgba(246,196,81,.16); bottom:18px; left:-70px; }
  .brand-block, .rail-card, .rail-footer { position:relative; z-index:1; }

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
  .brand-kicker::before { content:""; width:9px; height:9px; border-radius:50%; background:var(--vsa-red); box-shadow:0 0 0 5px rgba(200,32,47,.18); }
  .brand-block h1 { font-size:1.85rem; line-height:1.02; letter-spacing:-.04em; }

  .rail-card {
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.08);
    border-radius:20px;
    padding:16px;
    backdrop-filter:blur(12px);
  }
  .rail-card h2 { font-size:.77rem; letter-spacing:.11em; text-transform:uppercase; color:rgba(255,255,255,.62); margin-bottom:12px; }

  .health-grid { display:grid; gap:10px; }
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
  .health-item span:first-child { color:rgba(255,255,255,.70); font-size:.82rem; }
  .health-item strong { font-size:1.15rem; font-variant-numeric:tabular-nums; }

  .filter-list { display:grid; gap:8px; }
  .filter-btn {
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
  .filter-btn:hover, .filter-btn.active { background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.20); transform:translateX(2px); }
  .filter-count { min-width:26px; height:22px; padding:0 8px; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; background:rgba(255,255,255,.13); color:#fff; font-size:.78rem; font-weight:800; }

  .next-event { display:grid; gap:8px; }
  .next-date { font-size:.78rem; color:rgba(255,255,255,.62); text-transform:uppercase; letter-spacing:.10em; font-weight:800; }
  .next-name { font-size:1rem; font-weight:850; line-height:1.25; }
  .next-meta { color:rgba(255,255,255,.70); font-size:.84rem; line-height:1.45; }

  .rail-footer { margin-top:auto; color:rgba(255,255,255,.58); font-size:.78rem; line-height:1.45; }

  .work-area { min-width:0; display:flex; flex-direction:column; background:linear-gradient(180deg, var(--vsa-panel-2), var(--vsa-panel)); }
  .topbar {
    min-height:78px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    padding:18px 22px;
    border-bottom:1px solid var(--vsa-line);
    background:rgba(255,255,255,.72);
    position:sticky;
    top:0;
    z-index:10;
    backdrop-filter:blur(18px);
  }
  html.dark-mode .topbar { background:rgba(17,31,46,.76); }
  .top-title { display:flex; align-items:center; gap:12px; min-width:0; }
  .icon-badge {
    width:44px;
    height:44px;
    flex:0 0 44px;
    border-radius:16px;
    display:grid;
    place-items:center;
    color:#fff;
    background:linear-gradient(135deg, var(--vsa-red), var(--vsa-red-2));
    box-shadow:0 12px 22px rgba(200,32,47,.25);
  }
  .top-title h2 { font-size:1.25rem; letter-spacing:-.02em; line-height:1.1; }
  .top-title p { color:var(--vsa-muted); font-size:.86rem; margin-top:3px; }

  .top-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
  .search-wrap { position:relative; min-width:260px; flex:1 1 260px; }
  .search-wrap input {
    width:100%;
    border:1px solid var(--vsa-line);
    background:var(--vsa-panel);
    color:var(--vsa-text);
    border-radius:14px;
    padding:11px 12px 11px 38px;
    outline:none;
    transition:border-color .18s ease, box-shadow .18s ease;
  }
  .search-wrap input:focus { border-color:rgba(200,32,47,.45); box-shadow:0 0 0 4px rgba(200,32,47,.10); }
  .search-wrap span { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--vsa-muted); font-size:.95rem; }

  .btn {
    border:none;
    border-radius:14px;
    padding:11px 15px;
    font-weight:850;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    line-height:1;
    transition:transform .16s ease, box-shadow .16s ease, background .16s ease, border-color .16s ease;
  }
  .btn:hover { transform:translateY(-1px); }
  .btn.primary { color:#fff; background:linear-gradient(135deg, var(--vsa-red), var(--vsa-red-2)); box-shadow:0 12px 24px rgba(200,32,47,.22); }
  .btn.ghost { color:var(--vsa-text); background:var(--vsa-panel); border:1px solid var(--vsa-line); }
  .btn.ghost:hover { border-color:rgba(200,32,47,.35); }
  .btn.danger { color:var(--vsa-danger); background:transparent; border:1px solid rgba(183,38,52,.24); }
  .btn.danger:hover { background:rgba(183,38,52,.08); }
  .btn.sm { padding:8px 10px; border-radius:11px; font-size:.82rem; }

  .flash-stack { padding:14px 22px 0; display:grid; gap:10px; }
  .flash { border-radius:14px; padding:12px 14px; font-weight:750; font-size:.9rem; border:1px solid var(--vsa-line); }
  .flash.ok { color:var(--vsa-green); background:rgba(36,134,91,.10); border-color:rgba(36,134,91,.20); }
  .flash.bad { color:var(--vsa-danger); background:rgba(183,38,52,.10); border-color:rgba(183,38,52,.20); }

  .content-panel { flex:1; min-height:0; padding:18px 22px 22px; display:flex; flex-direction:column; }
  .table-shell {
    flex:1;
    min-height:0;
    border:1px solid var(--vsa-line);
    border-radius:22px;
    background:var(--vsa-panel);
    box-shadow:var(--vsa-shadow-soft);
    overflow:hidden;
    display:flex;
    flex-direction:column;
  }
  .table-headline {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 16px;
    border-bottom:1px solid var(--vsa-line);
    background:linear-gradient(180deg, var(--vsa-panel), var(--vsa-panel-2));
  }
  .table-headline strong { font-size:.92rem; }
  .table-headline span { color:var(--vsa-muted); font-size:.82rem; }
  .table-scroll { overflow:auto; flex:1; }

  table.events { width:100%; min-width:920px; border-collapse:separate; border-spacing:0; }
  table.events thead th {
    position:sticky;
    top:0;
    z-index:3;
    background:var(--vsa-panel-2);
    color:var(--vsa-muted);
    text-align:left;
    font-size:.72rem;
    letter-spacing:.10em;
    text-transform:uppercase;
    padding:12px 14px;
    border-bottom:1px solid var(--vsa-line);
    white-space:nowrap;
  }
  table.events tbody td {
    padding:14px;
    border-bottom:1px solid var(--vsa-line);
    color:var(--vsa-text);
    vertical-align:middle;
    font-size:.9rem;
  }
  table.events tbody tr:last-child td { border-bottom:none; }
  table.events tbody tr { transition:background .16s ease; }
  table.events tbody tr:hover { background:rgba(200,32,47,.035); }
  html.dark-mode table.events tbody tr:hover { background:rgba(255,255,255,.035); }

  .date-stack { display:grid; gap:5px; white-space:nowrap; }
  .date-main { font-weight:900; font-variant-numeric:tabular-nums; }
  .date-sub { color:var(--vsa-muted); font-size:.78rem; }
  .time-block { font-variant-numeric:tabular-nums; font-weight:800; white-space:nowrap; color:var(--vsa-navy); }
  html.dark-mode .time-block { color:#dce9f8; }
  .event-title { font-weight:900; letter-spacing:-.01em; }
  .event-desc { color:var(--vsa-muted); max-width:360px; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; line-height:1.35; }
  .muted-dash { color:var(--vsa-muted); }
  .status-pill {
    display:inline-flex;
    align-items:center;
    gap:6px;
    min-height:26px;
    padding:4px 10px;
    border-radius:999px;
    font-size:.76rem;
    font-weight:900;
    white-space:nowrap;
  }
  .status-pill::before { content:""; width:7px; height:7px; border-radius:50%; background:currentColor; }
  .status-pill.upcoming { color:#1d4ed8; background:rgba(29,78,216,.10); }
  .status-pill.today { color:var(--vsa-green); background:rgba(36,134,91,.12); }
  .status-pill.past { color:var(--vsa-muted); background:rgba(98,115,134,.13); }
  .actions { display:flex; gap:8px; white-space:nowrap; }
  .empty { padding:42px 18px; text-align:center; color:var(--vsa-muted); }
  .empty strong { display:block; color:var(--vsa-text); font-size:1.05rem; margin-bottom:5px; }

  .drawer-backdrop {
    position:fixed;
    inset:0;
    z-index:80;
    background:rgba(7,14,22,.46);
    display:none;
    align-items:stretch;
    justify-content:flex-end;
    padding:18px;
  }
  .drawer-backdrop.show { display:flex; }
  .drawer {
    width:min(520px, 100%);
    background:var(--vsa-panel);
    color:var(--vsa-text);
    border:1px solid var(--vsa-line);
    border-radius:24px;
    box-shadow:0 28px 80px rgba(0,0,0,.34);
    display:flex;
    flex-direction:column;
    max-height:calc(100vh - 36px);
    animation:slideIn .20s ease-out both;
    overflow:hidden;
  }
  @keyframes slideIn { from { transform:translateX(20px); opacity:.3; } to { transform:translateX(0); opacity:1; } }
  .drawer-header { padding:20px 20px 16px; border-bottom:1px solid var(--vsa-line); display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
  .drawer-header h2 { font-size:1.2rem; letter-spacing:-.02em; }
  .drawer-header p { color:var(--vsa-muted); margin-top:4px; font-size:.88rem; }
  .icon-close { width:38px; height:38px; border-radius:12px; border:1px solid var(--vsa-line); background:var(--vsa-panel-2); color:var(--vsa-text); cursor:pointer; }
  .drawer-body { padding:18px 20px; overflow:auto; }
  .field { margin-bottom:14px; }
  .field label { display:flex; align-items:center; justify-content:space-between; gap:8px; color:var(--vsa-muted); font-size:.74rem; font-weight:900; letter-spacing:.09em; text-transform:uppercase; margin-bottom:6px; }
  .req { color:var(--vsa-red); }
  .field input, .field textarea {
    width:100%;
    border:1px solid var(--vsa-line);
    border-radius:14px;
    background:var(--vsa-panel-2);
    color:var(--vsa-text);
    padding:11px 12px;
    outline:none;
  }
  .field input:focus, .field textarea:focus { border-color:rgba(200,32,47,.45); box-shadow:0 0 0 4px rgba(200,32,47,.10); }
  .field textarea { min-height:118px; resize:vertical; line-height:1.45; }
  .field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .field-row.three { grid-template-columns:1fr 1fr 1fr; }
  .drawer-actions { margin-top:18px; display:flex; justify-content:flex-end; gap:10px; }

  .confirm-card {
    width:min(420px, 100%);
    background:var(--vsa-panel);
    border:1px solid var(--vsa-line);
    border-radius:24px;
    box-shadow:0 28px 80px rgba(0,0,0,.34);
    align-self:center;
    margin:auto;
    padding:24px;
    text-align:center;
    animation:popIn .18s ease-out both;
  }
  @keyframes popIn { from { transform:scale(.97); opacity:.3; } to { transform:scale(1); opacity:1; } }
  .confirm-card h2 { font-size:1.18rem; margin-bottom:8px; }
  .confirm-card p { color:var(--vsa-muted); line-height:1.5; margin-bottom:20px; }
  .confirm-actions { display:flex; justify-content:center; gap:10px; }

  @media (max-width:980px) {
    .events-app { grid-template-columns:1fr; }
    .side-rail { display:block; padding:18px; }
    .brand-block { margin-bottom:14px; }
    .rail-card { margin-top:12px; }
    .rail-footer { display:none; }
    .health-grid { grid-template-columns:repeat(4, minmax(0,1fr)); }
    .filter-list { grid-template-columns:repeat(4, minmax(0,1fr)); }
    .filter-btn { justify-content:center; }
    .next-event { display:none; }
  }

  @media (max-width:720px) {
    .page-shell { padding:10px; }
    .events-app { border-radius:20px; }
    .topbar { align-items:stretch; flex-direction:column; padding:16px; }
    .top-actions { justify-content:stretch; }
    .search-wrap { min-width:100%; }
    .top-actions .btn { flex:1; }
    .content-panel { padding:14px; }
    .health-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .filter-list { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .field-row, .field-row.three { grid-template-columns:1fr; }
    .drawer-backdrop { padding:10px; }
    .drawer { border-radius:20px; max-height:calc(100vh - 20px); }
  }
</style>
</head>
<body<?php echo $panel ? ' class="panel-mode"' : ''; ?>>
<?php $user = $me; include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-chrome.php'; ?>
<div class="page-shell">
  <main class="events-app">
    <aside class="side-rail" aria-label="Event summary and filters">
      <section class="brand-block">
        <div class="brand-kicker">Events Workspace</div>
        <h1>Event Management</h1>
      </section>

      <section class="rail-card">
        <h2>Event status</h2>
        <div class="health-grid">
          <div class="health-item"><span>Total</span><strong><?= (int)$totalEv ?></strong></div>
          <div class="health-item"><span>Upcoming</span><strong><?= (int)$upCount ?></strong></div>
          <div class="health-item"><span>Today</span><strong><?= (int)$todayCount ?></strong></div>
          <div class="health-item"><span>Past</span><strong><?= (int)$pastCount ?></strong></div>
        </div>
      </section>

      <section class="rail-card">
        <h2>Views</h2>
        <div class="filter-list">
          <button class="filter-btn active" data-filter="all" type="button"><span>All Events</span><span class="filter-count"><?= (int)$totalEv ?></span></button>
          <button class="filter-btn" data-filter="upcoming" type="button"><span>Upcoming</span><span class="filter-count"><?= (int)$upCount ?></span></button>
          <button class="filter-btn" data-filter="today" type="button"><span>Today</span><span class="filter-count"><?= (int)$todayCount ?></span></button>
          <button class="filter-btn" data-filter="past" type="button"><span>Past</span><span class="filter-count"><?= (int)$pastCount ?></span></button>
        </div>
      </section>

      <section class="rail-card next-event">
        <h2>Next event</h2>
        <?php if ($nextEvent !== null):
          $nextTime = fmt_time($nextEvent['start_time'] ?? '');
          $nextEnd = fmt_time($nextEvent['end_time'] ?? '');
          if ($nextTime !== '' && $nextEnd !== '') { $nextTime .= '–'.$nextEnd; }
        ?>
          <div class="next-date"><?= h((string)($nextEvent['event_date'] ?? '')) ?><?= $nextTime !== '' ? ' · '.h($nextTime) : '' ?></div>
          <div class="next-name"><?= h((string)($nextEvent['name'] ?? '')) ?></div>
          <div class="next-meta"><?= h((string)($nextEvent['location'] ?? 'No location set')) ?></div>
        <?php else: ?>
          <div class="next-meta">No upcoming events scheduled.</div>
        <?php endif; ?>
      </section>

      <div class="rail-footer">UMKC VSA officer tools</div>
    </aside>

    <section class="work-area">
      <header class="topbar">
        <div class="top-title">
          <div class="icon-badge" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M7 3v3M17 3v3M4.5 9.2h15M6 21h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </div>
          <div>
            <h2>Events</h2>
            <p>Manage dates, times, locations, and details from one full-page workspace.</p>
          </div>
        </div>
        <div class="top-actions">
          <div class="search-wrap">
            <span aria-hidden="true">⌕</span>
            <input type="text" id="searchBox" placeholder="Search events, locations, descriptions…" autocomplete="off">
          </div>
          <button class="btn primary" type="button" id="addBtn">+ Add Event</button>
        </div>
      </header>

      <?php if ($msg !== '' || $err !== ''): ?>
      <div class="flash-stack">
        <?php if ($msg !== ''): ?><div class="flash ok"><?= h($msg) ?></div><?php endif; ?>
        <?php if ($err !== ''): ?><div class="flash bad"><?= h($err) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="content-panel">
        <div class="table-shell">
          <div class="table-headline">
            <div><strong>Event list</strong> <span id="visibleCount">Showing <?= (int)$totalEv ?> of <?= (int)$totalEv ?></span></div>
            <button class="btn ghost sm" type="button" id="clearFilters">Clear filters</button>
          </div>
          <div class="table-scroll">
            <table class="events">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Event</th>
                  <th>Location</th>
                  <th>Status</th>
                  <th>Description</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="eventBody">
              <?php if (empty($events)): ?>
                <tr class="no-row"><td colspan="7"><div class="empty"><strong>No events yet.</strong>Add your first event to start building the schedule.</div></td></tr>
              <?php else: foreach ($events as $ev):
                  $evDate = (string)($ev['event_date'] ?? '');
                  $isToday = ($evDate === $todayStr);
                  $isUp = ($evDate >= $todayStr);
                  $status = $isToday ? 'today' : ($isUp ? 'upcoming' : 'past');
                  $statusLabel = $isToday ? 'Today' : ($isUp ? 'Upcoming' : 'Past');
                  $st = fmt_time($ev['start_time'] ?? '');
                  $et = fmt_time($ev['end_time'] ?? '');
                  $timeStr = $st;
                  if ($st !== '' && $et !== '') { $timeStr .= '–'.$et; }
                  $dateLabel = $evDate !== '' ? date('M j, Y', strtotime($evDate)) : '';
                  $dayLabel = $evDate !== '' ? date('D', strtotime($evDate)) : '';
                  $hay = strtolower(($ev['name']??'').' '.($ev['location']??'').' '.($ev['description']??'').' '.$evDate.' '.$timeStr.' '.$statusLabel);
              ?>
                <tr class="event-row" data-when="<?= h($status) ?>" data-search="<?= h($hay) ?>">
                  <td>
                    <div class="date-stack">
                      <span class="date-main"><?= h($dateLabel ?: $evDate) ?></span>
                      <span class="date-sub"><?= h($dayLabel) ?></span>
                    </div>
                  </td>
                  <td><span class="time-block"><?= h($timeStr) ?: '<span class="muted-dash">—</span>' ?></span></td>
                  <td><div class="event-title"><?= h((string)($ev['name'] ?? '')) ?></div></td>
                  <td><?= h((string)($ev['location'] ?? '')) ?: '<span class="muted-dash">—</span>' ?></td>
                  <td><span class="status-pill <?= h($status) ?>"><?= h($statusLabel) ?></span></td>
                  <td><div class="event-desc"><?= h((string)($ev['description'] ?? '')) ?: '<span class="muted-dash">No description</span>' ?></div></td>
                  <td>
                    <div class="actions">
                      <button type="button" class="btn ghost sm edit-btn"
                        data-id="<?= (int)$ev['id'] ?>"
                        data-name="<?= h((string)($ev['name'] ?? '')) ?>"
                        data-date="<?= h($evDate) ?>"
                        data-start="<?= h($st) ?>"
                        data-end="<?= h($et) ?>"
                        data-location="<?= h((string)($ev['location'] ?? '')) ?>"
                        data-desc="<?= h((string)($ev['description'] ?? '')) ?>">Edit</button>
                      <button type="button" class="btn danger sm del-btn" data-id="<?= (int)$ev['id'] ?>" data-name="<?= h((string)($ev['name'] ?? '')) ?>">Delete</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<div class="drawer-backdrop" id="formDrawer" aria-hidden="true">
  <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="formTitle">
    <div class="drawer-header">
      <div>
        <h2 id="formTitle">Add Event</h2>
        <p>Fields marked with <span class="req">*</span> are required.</p>
      </div>
      <button class="icon-close" type="button" data-close="formDrawer" aria-label="Close">×</button>
    </div>
    <form method="post" id="eventForm" class="drawer-body">
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="id" id="formId" value="">
      <div class="field"><label>Event Name <span class="req">*</span></label><input type="text" name="name" id="fName" required></div>
      <div class="field-row three">
        <div class="field"><label>Date <span class="req">*</span></label><input type="date" name="event_date" id="fDate" required></div>
        <div class="field"><label>Start Time</label><input type="time" name="start_time" id="fStart"></div>
        <div class="field"><label>End Time</label><input type="time" name="end_time" id="fEnd"></div>
      </div>
      <div class="field"><label>Location</label><input type="text" name="location" id="fLoc" placeholder="Room, campus building, venue…"></div>
      <div class="field"><label>Description</label><textarea name="description" id="fDesc" placeholder="Add internal notes, public description, agenda, or reminders…"></textarea></div>
      <div class="drawer-actions">
        <button type="button" class="btn ghost" data-close="formDrawer">Cancel</button>
        <button type="submit" class="btn primary" id="formSubmit">Add Event</button>
      </div>
    </form>
  </aside>
</div>

<div class="drawer-backdrop" id="deleteDialog" aria-hidden="true">
  <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
    <h2 id="deleteTitle">Delete Event</h2>
    <p id="deleteMsg">Are you sure you want to delete this event? This cannot be undone.</p>
    <form method="post" id="deleteForm">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="deleteId" value="">
      <div class="confirm-actions">
        <button type="button" class="btn ghost" data-close="deleteDialog">Cancel</button>
        <button type="submit" class="btn primary">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var formDrawer = document.getElementById('formDrawer');
  var deleteDialog = document.getElementById('deleteDialog');
  var searchBox = document.getElementById('searchBox');
  var visibleCount = document.getElementById('visibleCount');
  var totalRows = document.querySelectorAll('.event-row').length;
  var curFilter = 'all';

  function show(el){ el.classList.add('show'); el.setAttribute('aria-hidden','false'); }
  function hide(el){ el.classList.remove('show'); el.setAttribute('aria-hidden','true'); }

  document.querySelectorAll('[data-close]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var target = document.getElementById(btn.getAttribute('data-close'));
      if (target) hide(target);
    });
  });

  [formDrawer, deleteDialog].forEach(function(modal){
    modal.addEventListener('click', function(e){ if (e.target === modal) hide(modal); });
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { hide(formDrawer); hide(deleteDialog); }
  });

  function setForm(mode, data){
    var isEdit = mode === 'edit';
    document.getElementById('formTitle').textContent = isEdit ? 'Edit Event' : 'Add Event';
    document.getElementById('formSubmit').textContent = isEdit ? 'Save Changes' : 'Add Event';
    document.getElementById('formAction').value = isEdit ? 'update' : 'add';
    document.getElementById('formId').value = data.id || '';
    document.getElementById('fName').value = data.name || '';
    document.getElementById('fDate').value = data.date || '';
    document.getElementById('fStart').value = data.start || '';
    document.getElementById('fEnd').value = data.end || '';
    document.getElementById('fLoc').value = data.location || '';
    document.getElementById('fDesc').value = data.desc || '';
  }

  var addBtn = document.getElementById('addBtn');
  if (addBtn) addBtn.addEventListener('click', function(){
    document.getElementById('eventForm').reset();
    setForm('add', {});
    show(formDrawer);
    setTimeout(function(){ document.getElementById('fName').focus(); }, 60);
  });

  document.querySelectorAll('.edit-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      setForm('edit', {
        id: btn.getAttribute('data-id'),
        name: btn.getAttribute('data-name'),
        date: btn.getAttribute('data-date'),
        start: btn.getAttribute('data-start'),
        end: btn.getAttribute('data-end'),
        location: btn.getAttribute('data-location'),
        desc: btn.getAttribute('data-desc')
      });
      show(formDrawer);
      setTimeout(function(){ document.getElementById('fName').focus(); }, 60);
    });
  });

  document.querySelectorAll('.del-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.getElementById('deleteId').value = btn.getAttribute('data-id') || '';
      var nm = btn.getAttribute('data-name') || 'this event';
      document.getElementById('deleteMsg').textContent = 'Are you sure you want to delete “' + nm + '”? This cannot be undone.';
      show(deleteDialog);
    });
  });

  function applyFilter(){
    var q = (searchBox ? searchBox.value : '').toLowerCase().trim();
    var shown = 0;
    document.querySelectorAll('.event-row').forEach(function(row){
      var rowStatus = row.getAttribute('data-when') || '';
      var okFilter = curFilter === 'all' || rowStatus === curFilter;
      var okSearch = q === '' || (row.getAttribute('data-search') || '').indexOf(q) > -1;
      var ok = okFilter && okSearch;
      row.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    if (visibleCount) visibleCount.textContent = 'Showing ' + shown + ' of ' + totalRows;
  }

  document.querySelectorAll('.filter-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.filter-btn').forEach(function(x){ x.classList.remove('active'); });
      btn.classList.add('active');
      curFilter = btn.getAttribute('data-filter') || 'all';
      applyFilter();
    });
  });

  if (searchBox) searchBox.addEventListener('input', applyFilter);
  var clearBtn = document.getElementById('clearFilters');
  if (clearBtn) clearBtn.addEventListener('click', function(){
    curFilter = 'all';
    if (searchBox) searchBox.value = '';
    document.querySelectorAll('.filter-btn').forEach(function(x){ x.classList.toggle('active', x.getAttribute('data-filter') === 'all'); });
    applyFilter();
  });

  <?php if (!empty($editRow)): ?>
  (function(){
    setForm('edit', {
      id: <?= (int)($editRow['id'] ?? 0) ?>,
      name: <?= json_encode($editRow['name'] ?? '') ?>,
      date: <?= json_encode($editRow['event_date'] ?? '') ?>,
      start: <?= json_encode(fmt_time($editRow['start_time'] ?? '')) ?>,
      end: <?= json_encode(fmt_time($editRow['end_time'] ?? '')) ?>,
      location: <?= json_encode($editRow['location'] ?? '') ?>,
      desc: <?= json_encode($editRow['description'] ?? '') ?>
    });
    show(formDrawer);
  })();
  <?php endif; ?>
})();
</script>
<?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-script.php'; ?>
</body>
</html>
