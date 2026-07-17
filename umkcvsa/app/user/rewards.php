<?php
// ============================================================
// UMKC VSA - Rewards Page
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../partials/audit.php';

$user = require_login();
$pdo  = db();
$me   = current_user();
$isOfficer = (has_role($me,'officer') || has_role($me,'admin'));
$isAdmin   = has_role($me,'admin');

// Auto-create rewards table (consistent with audit helper pattern)
$pdo->exec("CREATE TABLE IF NOT EXISTS app_rewards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(500) NULL,
  point_cost INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Reward CRUD (officers/admins only) ----
    if (in_array($action, ['reward_add','reward_update','reward_delete'], true)) {
        if (!$isOfficer) { $err = 'Not authorized.'; }
        elseif ($action === 'reward_add') {
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $cost = (int)($_POST['point_cost'] ?? 0);
            if ($name === '' || $cost < 0) { $err = 'Reward name is required and cost must be 0 or more.'; }
            else {
                $stmt = $pdo->prepare('INSERT INTO app_rewards (name, description, point_cost, active, created_by) VALUES (?, ?, ?, 1, ?)');
                $stmt->execute([$name, ($desc !== '' ? $desc : null), $cost, (int)($me['id'] ?? 0)]);
                log_audit('reward_create', 'reward', $name . ' (' . $cost . ' pts)');
                $msg = 'Reward added.';
            }
        }
        elseif ($action === 'reward_update') {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $cost = (int)($_POST['point_cost'] ?? 0);
            $act  = isset($_POST['active']) ? 1 : 0;
            if ($id <= 0 || $name === '' || $cost < 0) { $err = 'Reward name is required and cost must be 0 or more.'; }
            else {
                $stmt = $pdo->prepare('UPDATE app_rewards SET name=?, description=?, point_cost=?, active=? WHERE id=?');
                $stmt->execute([$name, ($desc !== '' ? $desc : null), $cost, $act, $id]);
                log_audit('reward_update', 'reward', $name . ' (' . $cost . ' pts)');
                $msg = 'Reward updated.';
            }
        }
        elseif ($action === 'reward_delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $r = $pdo->prepare('SELECT name FROM app_rewards WHERE id=?'); $r->execute([$id]);
                $rn = (string)($r->fetchColumn() ?: ('#' . $id));
                $stmt = $pdo->prepare('DELETE FROM app_rewards WHERE id=?');
                $stmt->execute([$id]);
                log_audit('reward_delete', 'reward', $rn);
                $msg = 'Reward deleted.';
            }
        }
    }

    // ---- Grant / adjust points ----
    elseif ($action === 'grant_points') {
        if (!$isOfficer) { $err = 'Not authorized.'; }
        else {
            $ids    = $_POST['member_ids'] ?? [];
            if (!is_array($ids)) { $ids = [$ids]; }
            $ids    = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
            $amount = (int)($_POST['amount'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? ''));
            if (!$ids) { $err = 'Please select at least one member.'; }
            elseif ($amount === 0) { $err = 'Enter a non-zero amount.'; }
            elseif ($amount < 0 && !$isAdmin) { $err = 'Only an admin can deduct points.'; }
            else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sel = $pdo->prepare('SELECT id, full_name, email, points FROM app_users WHERE id IN (' . $placeholders . ')');
                $sel->execute($ids);
                $targets = $sel->fetchAll(PDO::FETCH_ASSOC);
                if (!$targets) { $err = 'No matching members found.'; }
                else {
                    $names = [];
                    $upd = $pdo->prepare('UPDATE app_users SET points=? WHERE id=?');
                    foreach ($targets as $target) {
                        $newTotal = (int)$target['points'] + $amount;
                        if ($newTotal < 0) { $newTotal = 0; }
                        $upd->execute([$newTotal, (int)$target['id']]);
                        $who  = (string)($target['full_name'] !== '' ? $target['full_name'] : $target['email']);
                        $verb = $amount >= 0 ? ('Granted ' . $amount) : ('Deducted ' . abs($amount));
                        $prep = $amount >= 0 ? 'to ' : 'from ';
                        $detail = $verb . ' pts ' . $prep . $who . ' (new total ' . $newTotal . ')'
                                  . ($reason !== '' ? (' — ' . $reason) : '');
                        log_audit('grant_points', 'user', $detail);
                        $names[] = $who;
                    }
                    $verb = $amount >= 0 ? ('Granted ' . $amount) : ('Deducted ' . abs($amount));
                    $prep = $amount >= 0 ? 'to ' : 'from ';
                    $msg  = $verb . ' points ' . $prep . count($names) . ' member' . (count($names) === 1 ? '' : 's') . ': ' . implode(', ', $names) . '.';
                }
            }
        }
    }
}

// ---- Data for rendering ----
$mp = $pdo->prepare('SELECT points FROM app_users WHERE id=?'); $mp->execute([(int)($me['id'] ?? 0)]);
$myPoints = (int)($mp->fetchColumn() ?: 0);
if ($isOfficer) {
    $rewards = $pdo->query('SELECT * FROM app_rewards ORDER BY point_cost ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rewards = $pdo->query('SELECT * FROM app_rewards WHERE active=1 ORDER BY point_cost ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
}
$members = [];
if ($isOfficer) {
    $members = $pdo->query('SELECT id, full_name, email, points FROM app_users ORDER BY full_name ASC')->fetchAll(PDO::FETCH_ASSOC);
}
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($isOfficer && $editId > 0) {
    $s = $pdo->prepare('SELECT * FROM app_rewards WHERE id=?'); $s->execute([$editId]);
    $editRow = $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!function_exists('h2')) { function h2(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Rewards | UMKC VSA</title>
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-head.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --navy: #16314d;
    --red: #c8202f;
    --light: #eef3f8;
    --text: #1f2933;
    --muted: #5b6b7b;
    --white: #ffffff;
    --sidebar-width: 270px;
  }
    html.dark-mode .calendar-card,
    html.dark-mode .events-card,
    html.dark-mode .rsvp-card { background: #16222f; border-color: #28394a; }
    html.dark-mode .day,
    html.dark-mode .empty-day { background: #16222f; border-color: #28394a; }
    html.dark-mode .day.has-rsvp { background: #2a1f24; }
    html.dark-mode .event-item { background: #1c2c3c; }
    /* dark contrast fixes */
    html.dark-mode .page-header h1 { color: var(--text); }
    html.dark-mode .day-number { color: var(--text); }
    html.dark-mode .event-title { color: var(--text); }
    html.dark-mode .weekday { color: #e6edf3; background: #0c1722; }
    html.dark-mode .card-heading,
    html.dark-mode .card-heading h2,
    html.dark-mode .calendar-heading-left h2 { color: #ffffff; }
    html.dark-mode .events-card h2,
    html.dark-mode .rsvp-card h2 { color: #ffffff; }
    html.dark-mode .page-header p,
    html.dark-mode .sub { color: var(--muted); }

  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: 'Source Sans 3', sans-serif;
    color: var(--text);
    background: var(--light);
    min-height: 100vh;
    overflow-x: hidden;
  }

  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar-styles.php'; ?><?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar-styles.php'; ?>/* =========================
Calendar Content
========================= */

    .wrap {
    max-width: 1400px;
    margin: 40px auto;
    padding: 0 28px;
    }

    .page-header {
    margin-bottom: 24px;
    }

    .page-header h1 {
    font-family: 'Playfair Display', serif;
    color: var(--navy);
    font-size: 2rem;
    margin-bottom: 6px;
    }

    .page-header p {
    color: var(--muted);
    }

    .calendar-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 24px;
    align-items: start;
    }

    .calendar-card,
    .events-card,
    .rsvp-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 18px 50px rgba(22, 49, 77, .12);
    animation: rise .6s ease both;
    overflow: hidden;
    }

    .calendar-card {
    padding: 0;
    }

    .events-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
    }

    .events-card,
    .rsvp-card {
    padding: 0;
    }

    @keyframes rise {
    from {
        opacity: 0;
        transform: translateY(24px);
    }

    to {
        opacity: 1;
        transform: none;
    }
    }

    /* Navy heading bands */

    .card-heading {
    background: var(--navy);
    color: var(--white);
    padding: 18px 24px;
    }

    .card-heading h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.45rem;
    color: var(--white);
    }

    .card-body {
    padding: 24px;
    }

    .calendar-heading-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    }

    .calendar-heading-left h2 {
    font-family: 'Playfair Display', serif;
    color: var(--white);
    font-size: 1.75rem;
    margin-bottom: 8px;
    }

    .calendar-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    color: rgba(255, 255, 255, .85);
    font-size: .9rem;
    font-weight: 700;
    }

    .legend-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    }

    .legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e3eef9;
    border: 1px solid #c8d8e8;
    }

    .legend-dot.rsvp {
    background: var(--red);
    border-color: var(--red);
    }

    .calendar-nav {
    display: flex;
    gap: 8px;
    align-items: center;
    }

    .calendar-arrow,
    .today-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    text-decoration: none;
    font-weight: 800;
    border: 1.5px solid rgba(255, 255, 255, .45);
    border-radius: 10px;
    transition: .2s ease;
    }

    .calendar-arrow {
    width: 42px;
    height: 38px;
    font-size: 1.25rem;
    }

    .today-link {
    height: 38px;
    padding: 0 14px;
    font-size: .95rem;
    }

    .calendar-arrow:hover,
    .today-link:hover {
    background: var(--white);
    color: var(--navy);
    border-color: var(--white);
    }

    /* Wider calendar */

    .calendar-scroll {
    width: 100%;
    overflow-x: auto;
    }

    .calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(135px, 1fr));
    border: 1px solid #e3ebf3;
    border-radius: 14px;
    overflow: hidden;
    min-width: 945px;
    }

    .weekday {
    background: var(--navy);
    color: var(--white);
    font-weight: 700;
    text-align: center;
    padding: 12px 6px;
    font-size: .92rem;
    }

    .day {
    min-height: 130px;
    background: #fff;
    border-right: 1px solid #e3ebf3;
    border-bottom: 1px solid #e3ebf3;
    padding: 11px;
    overflow: hidden;
    }

    .day:nth-child(7n) {
    border-right: none;
    }

    .empty-day {
    background: #f6f9fc;
    }

    .day.has-rsvp {
    background: #fff8f9;
    box-shadow: inset 0 0 0 2px rgba(200, 32, 47, .14);
    }

    .day-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 8px;
    }

    .today .day-number {
    background: var(--navy);
    color: var(--white);
    }

    /* Event pills */

    .event-pill {
    display: block;
    width: 100%;
    background: #e3eef9;
    color: var(--navy);
    font-size: .8rem;
    font-weight: 700;
    line-height: 1.2;
    padding: 7px 8px;
    border-radius: 8px;
    margin-top: 6px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    position: relative;
    }

    .event-pill.rsvped {
    background: var(--red);
    color: var(--white);
    }

    .event-pill .event-text {
    display: inline-block;
    min-width: 100%;
    white-space: nowrap;
    }

    /* Only animate long event names */
    .event-pill.long-event .event-text {
    animation: subtleSlide 7s ease-in-out infinite;
    }

    @keyframes subtleSlide {
    0% {
        transform: translateX(0);
    }

    18% {
        transform: translateX(0);
    }

    55% {
        transform: translateX(calc(-100% + 115px));
    }

    75% {
        transform: translateX(calc(-100% + 115px));
    }

    100% {
        transform: translateX(0);
    }
    }

    .event-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    }

    .event-item {
    border-left: 4px solid var(--navy);
    background: var(--light);
    border-radius: 10px;
    padding: 12px 14px;
    }

    .event-item.rsvped {
    border-left-color: var(--red);
    background: #fff5f6;
    }

    .event-date {
    color: var(--muted);
    font-size: .85rem;
    font-weight: 700;
    margin-bottom: 3px;
    }

    .event-title {
    color: var(--navy);
    font-weight: 700;
    }

    .rsvp-tag {
    display: inline-block;
    margin-top: 7px;
    background: var(--red);
    color: var(--white);
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 3px 8px;
    border-radius: 999px;
    }

    .empty-events {
    color: var(--muted);
    font-size: .95rem;
    }

  /* =========================
     Responsive
  ========================= */

  @media (max-width: 1050px) {
    .calendar-layout {
      grid-template-columns: 1fr;
    }

    .events-sidebar {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 760px) {
    .events-sidebar {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 680px) {
    .topbar {
      padding-right: 18px;
    }

    .topbar .name {
      font-size: 1rem;
    }

    .topbar a {
      padding: 6px 12px;
      font-size: .85rem;
    }

    .wrap {
      margin: 28px auto;
      padding: 0 16px;
    }

    .calendar-card,
    .events-card,
    .rsvp-card {
      padding: 20px;
    }

    .calendar-header {
      flex-direction: column;
      align-items: flex-start;
    }

    .calendar-grid {
      overflow-x: auto;
    }

    .weekday,
    .day {
      min-width: 105px;
    }
  }
  </style>
<style>
  .rw-panel{background:var(--vsa-card,#fff);border-radius:14px;padding:22px 26px;margin:0 0 22px;box-shadow:0 2px 10px rgba(0,0,0,.07);}
  html.dark-mode .rw-panel{background:#1b2c40;color:#dce8f5;box-shadow:0 2px 12px rgba(0,0,0,.4);}
  .rw-h2{font-size:22px;font-weight:700;margin:0 0 16px;}
  .rw-balance{font-size:16px;margin:0 0 18px;color:#7a7363;} html.dark-mode .rw-balance{color:#8aa0b6;}
  .rw-balance b{color:#b11b2b;font-size:22px;}
  .rw-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;}
  .rw-reward{border:1px solid #e2ddd0;border-radius:12px;padding:18px;display:flex;flex-direction:column;gap:8px;}
  html.dark-mode .rw-reward{border-color:#2a3c50;}
  .rw-reward.locked{opacity:.55;}
  .rw-rname{font-size:18px;font-weight:700;}
  .rw-rdesc{font-size:14px;color:#6f6857;flex:1;} html.dark-mode .rw-rdesc{color:#9fb2c6;}
  .rw-rcost{font-size:15px;font-weight:700;color:#b11b2b;}
  .rw-chip{display:inline-block;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;}
  .rw-chip-ok{background:#e3f3e8;color:#1d6b3a;} .rw-chip-no{background:#f3e7d6;color:#8a5a12;} .rw-chip-off{background:#eee;color:#888;}
  .rw-panel label{display:block;font-size:13px;font-weight:700;margin:0 0 6px;}
  .rw-field{margin:0 0 16px;} .rw-row{display:flex;gap:16px;flex-wrap:wrap;} .rw-row .rw-field{flex:1;min-width:160px;}
  .rw-panel input[type=text],.rw-panel input[type=number],.rw-panel textarea,.rw-panel select{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #cfcabb;border-radius:8px;font-size:15px;font-family:inherit;background:#fff;color:#1a1a1a;}
  html.dark-mode .rw-panel input,html.dark-mode .rw-panel textarea,html.dark-mode .rw-panel select{background:#26384c;border-color:#3a4d63;color:#dce8f5;}
  .rw-panel textarea{min-height:70px;resize:vertical;}
  .rw-btn{background:#b11b2b;color:#fff;border:none;padding:11px 22px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block;}
  .rw-btn:hover{background:#931625;} .rw-btn-sm{padding:5px 12px;font-size:13px;border-radius:6px;}
  .rw-btn-edit{background:#e8e4d8;color:#1a1a1a;} .rw-btn-edit:hover{background:#dcd7c8;}
  .rw-btn-del{background:transparent;color:#b11b2b;border:1px solid #b11b2b;} .rw-btn-del:hover{background:#b11b2b;color:#fff;}
  .rw-table{width:100%;border-collapse:collapse;font-size:14px;}
  .rw-table th{text-align:left;font-size:12px;color:#8a8472;padding:0 10px 10px;border-bottom:1px solid #e2ddd0;}
  html.dark-mode .rw-table th{color:#8aa0b6;border-color:#2a3c50;}
  .rw-table td{padding:11px 10px;vertical-align:top;border-bottom:1px solid #efeadd;} html.dark-mode .rw-table td{border-color:#243648;}
  .rw-flash{padding:10px 14px;border-radius:8px;margin:0 0 16px;font-size:14px;}
  .rw-flash-ok{background:#e3f3e8;color:#1d6b3a;} .rw-flash-err{background:#f7e0e0;color:#9a2020;}
  .rw-actions{display:flex;gap:8px;white-space:nowrap;align-items:center;}
  .rw-hint{font-size:12px;color:#8a8472;margin:6px 0 0;}
  .rw-cancel{display:inline-block;margin-left:12px;font-size:14px;color:#8a8472;}
  .rw-reward{border:1px solid #e6e1d4;border-radius:14px;padding:0;overflow:hidden;display:flex;flex-direction:column;background:#fff;transition:transform .15s ease,box-shadow .15s ease;}
  .rw-reward:hover{transform:translateY(-3px);box-shadow:0 8px 22px rgba(0,0,0,.12);}
  html.dark-mode .rw-reward{background:#16263a;border-color:#2a3c50;}
  .rw-reward.locked{opacity:.62;}
  .rw-imgwrap{position:relative;background:#f4f1ea;display:flex;align-items:center;justify-content:center;height:140px;}
  html.dark-mode .rw-imgwrap{background:#10202f;}
  .rw-img{max-width:78%;max-height:108px;object-fit:contain;}
  .rw-badge{position:absolute;top:10px;right:10px;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;letter-spacing:.02em;}
  .rw-badge-ok{background:#1d6b3a;color:#fff;} .rw-badge-off{background:#9a9a9a;color:#fff;}
  .rw-body{padding:16px 18px 18px;display:flex;flex-direction:column;gap:7px;flex:1;}
  .rw-rfoot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:6px;flex-wrap:wrap;}
  .rw-rcost{font-size:22px;font-weight:700;color:#b11b2b;} .rw-rcost-lbl{font-size:13px;font-weight:600;}
  .rw-picker{position:relative;}
  .rw-selected{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0 0;}
  .rw-selected:empty{margin:0;}
  .rw-tag{display:inline-flex;align-items:center;gap:7px;background:#b11b2b;color:#fff;font-size:13px;font-weight:700;padding:5px 8px 5px 12px;border-radius:20px;}
  .rw-tag button{background:rgba(255,255,255,.25);border:none;color:#fff;width:18px;height:18px;border-radius:50%;cursor:pointer;font-size:13px;line-height:1;display:flex;align-items:center;justify-content:center;}
  .rw-tag button:hover{background:rgba(255,255,255,.45);}
  .rw-results{margin-top:10px;max-height:230px;overflow-y:auto;border:1px solid #e2ddd0;border-radius:10px;}
  html.dark-mode .rw-results{border-color:#3a4d63;}
  .rw-result{display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid #f0ebde;}
  html.dark-mode .rw-result{border-color:#243648;}
  .rw-result:last-child{border-bottom:none;} .rw-result:hover{background:#f7f4ec;} html.dark-mode .rw-result:hover{background:#1f3148;}
  .rw-result input{width:auto;margin:0;flex:none;}
  .rw-result-name{font-weight:700;font-size:14px;} .rw-result-meta{font-size:12px;color:#8a8472;margin-left:auto;} html.dark-mode .rw-result-meta{color:#8aa0b6;}
  .rw-noresult{padding:12px;font-size:13px;color:#8a8472;}
</style>
</head>

<body>

  

  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar Menu -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar.php'; ?>

  <!-- Top Bar -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar.php'; ?>

  <!-- Calendar Content -->
  <main class="wrap">
    <div class="page-header">
      <h1>Rewards</h1>
      <p>Earn points for participating in UMKC VSA and redeem them for rewards.</p>
    </div>

    <?php if ($msg !== ''): ?><div class="rw-flash rw-flash-ok"><?= h2($msg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="rw-flash rw-flash-err"><?= h2($err) ?></div><?php endif; ?>

    <div class="rw-panel">
      <p class="rw-balance">Your balance: <b><?= $myPoints ?></b> points</p>
      <?php if (!$rewards): ?>
        <p>No rewards available yet.</p>
      <?php else: ?>
      <div class="rw-grid">
        <?php foreach ($rewards as $rw): ?>
          <?php $afford = $myPoints >= (int)$rw['point_cost']; $isActive = (int)$rw['active'] === 1; ?>
          <div class="rw-reward<?= $afford ? ' affordable' : ' locked' ?>">
            <div class="rw-imgwrap">
              <img class="rw-img" src="/app/logo.png" alt="<?= h2($rw['name']) ?>" onerror="this.onerror=null;this.src='/assets/logo.png';">
              <?php if (!$isActive): ?><span class="rw-badge rw-badge-off">Inactive</span>
              <?php elseif ($afford): ?><span class="rw-badge rw-badge-ok">Available</span><?php endif; ?>
            </div>
            <div class="rw-body">
              <div class="rw-rname"><?= h2($rw['name']) ?></div>
              <div class="rw-rdesc"><?= h2($rw['description'] ?? '') ?></div>
              <div class="rw-rfoot">
                <span class="rw-rcost"><?= (int)$rw['point_cost'] ?> <span class="rw-rcost-lbl">pts</span></span>
                <?php if (!$isActive): ?><span class="rw-chip rw-chip-off">Inactive</span>
                <?php elseif ($afford): ?><span class="rw-chip rw-chip-ok">You can redeem this</span>
                <?php else: ?><span class="rw-chip rw-chip-no"><?= (int)$rw['point_cost'] - $myPoints ?> more pts</span><?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($isOfficer): ?>
    <div class="rw-panel">
      <h2 class="rw-h2">Grant Points</h2>
      <form method="post" action="">
        <input type="hidden" name="action" value="grant_points">
        <div class="rw-field">
          <label>Members</label>
          <div class="rw-picker">
            <input type="text" class="rw-search" id="rwSearch" placeholder="Search members by name or email…" autocomplete="off">
            <div class="rw-selected" id="rwSelected"></div>
            <div class="rw-results" id="rwResults">
              <?php foreach ($members as $mb): ?>
                <?php $mname = $mb['full_name'] !== '' ? $mb['full_name'] : $mb['email']; ?>
                <label class="rw-result" data-search="<?= h2(strtolower($mname . ' ' . $mb['email'])) ?>">
                  <input type="checkbox" name="member_ids[]" value="<?= (int)$mb['id'] ?>" data-name="<?= h2($mname) ?>">
                  <span class="rw-result-name"><?= h2($mname) ?></span>
                  <span class="rw-result-meta"><?= h2($mb['email']) ?> · <?= (int)$mb['points'] ?> pts</span>
                </label>
              <?php endforeach; ?>
              <div class="rw-noresult" id="rwNoResult" style="display:none;">No members match your search.</div>
            </div>
          </div>
          <p class="rw-hint">Select one or more members. Points are granted to everyone selected in a single transaction.</p>
        </div>
        <div class="rw-row">
          <div class="rw-field">
            <label>Amount</label>
            <input type="number" name="amount"<?= $isAdmin ? '' : ' min="1"' ?> step="1" placeholder="e.g. 50" required>
            <?php if ($isAdmin): ?><p class="rw-hint">Admins may enter a negative amount to deduct points.</p>
            <?php else: ?><p class="rw-hint">Positive amounts only. Deductions are admin-only.</p><?php endif; ?>
          </div>
          <div class="rw-field">
            <label>Reason (optional)</label>
            <input type="text" name="reason" placeholder="e.g. Volunteered at fall festival">
          </div>
        </div>
        <button type="submit" class="rw-btn">Grant Points</button>
      </form>
    </div>

    <div class="rw-panel">
      <h2 class="rw-h2"><?= $editRow ? 'Edit Reward' : 'Add Reward' ?></h2>
      <form method="post" action="">
        <input type="hidden" name="action" value="<?= $editRow ? 'reward_update' : 'reward_add' ?>">
        <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
        <div class="rw-field"><label>Reward Name *</label><input type="text" name="name" required value="<?= h2($editRow['name'] ?? '') ?>"></div>
        <div class="rw-field"><label>Description</label><textarea name="description"><?= h2($editRow['description'] ?? '') ?></textarea></div>
        <div class="rw-row">
          <div class="rw-field"><label>Point Cost *</label><input type="number" name="point_cost" min="0" step="1" required value="<?= (int)($editRow['point_cost'] ?? 0) ?>"></div>
          <?php if ($editRow): ?>
          <div class="rw-field"><label>Active</label><label style="font-weight:400;"><input type="checkbox" name="active" value="1" <?= ((int)$editRow['active'] === 1) ? 'checked' : '' ?>> Visible to members</label></div>
          <?php endif; ?>
        </div>
        <button type="submit" class="rw-btn"><?= $editRow ? 'Save Changes' : 'Add Reward' ?></button>
        <?php if ($editRow): ?><a class="rw-cancel" href="rewards.php">Cancel</a><?php endif; ?>
      </form>
    </div>

    <div class="rw-panel">
      <h2 class="rw-h2">Manage Rewards</h2>
      <?php if (!$rewards): ?>
        <p>No rewards yet.</p>
      <?php else: ?>
      <table class="rw-table">
        <thead><tr><th>Name</th><th>Cost</th><th>Status</th><th>Description</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rewards as $rw): ?>
          <tr>
            <td><?= h2($rw['name']) ?></td>
            <td><?= (int)$rw['point_cost'] ?></td>
            <td><?= ((int)$rw['active'] === 1) ? 'Active' : 'Inactive' ?></td>
            <td><?= h2($rw['description'] ?? '') ?></td>
            <td>
              <div class="rw-actions">
                <a class="rw-btn rw-btn-sm rw-btn-edit" href="rewards.php?edit=<?= (int)$rw['id'] ?>">Edit</a>
                <form method="post" action="" onsubmit="return confirm('Delete this reward? This cannot be undone.');" style="margin:0;">
                  <input type="hidden" name="action" value="reward_delete">
                  <input type="hidden" name="id" value="<?= (int)$rw['id'] ?>">
                  <button type="submit" class="rw-btn rw-btn-sm rw-btn-del">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </main>

  <script>
    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    const mainMenuToggle = document.getElementById("mainMenuToggle");
    const mainMenuContent = document.getElementById("mainMenuContent");

    const profileToggle = document.getElementById("profileToggle");
    const profileSubmenu = document.getElementById("profileSubmenu");

    function openSidebar() {
      sidebar.classList.add("open");
      sidebarOverlay.classList.add("show");
      menuToggle.classList.add("active");
      menuToggle.setAttribute("aria-expanded", "true");
    }

    function closeSidebar() {
      sidebar.classList.remove("open");
      sidebarOverlay.classList.remove("show");
      menuToggle.classList.remove("active");
      menuToggle.setAttribute("aria-expanded", "false");
    }

    function toggleSection(button, content) {
      button.classList.toggle("open");
      content.classList.toggle("open");
    }

    menuToggle.addEventListener("click", () => {
      const isOpen = sidebar.classList.contains("open");

      if (isOpen) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    sidebarOverlay.addEventListener("click", closeSidebar);

    mainMenuToggle.addEventListener("click", () => {
      toggleSection(mainMenuToggle, mainMenuContent);
    });

    profileToggle.addEventListener("click", () => {
      toggleSection(profileToggle, profileSubmenu);
    });
    (function(){
      var officerToggle = document.getElementById("officerToggle");
      var officerSubmenu = document.getElementById("officerSubmenu");
      if (officerToggle && officerSubmenu) {
        officerToggle.addEventListener("click", function(){
          officerSubmenu.classList.toggle("open");
        });
      }
    })();
  </script>

  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-panel.php'; ?>
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-script.php'; ?>
<script>
(function(){
  var search = document.getElementById('rwSearch');
  if(!search) return;
  var results = document.getElementById('rwResults');
  var selected = document.getElementById('rwSelected');
  var noResult = document.getElementById('rwNoResult');
  var rows = Array.prototype.slice.call(results.querySelectorAll('.rw-result'));
  function renderTags(){
    selected.innerHTML = '';
    rows.forEach(function(row){
      var cb = row.querySelector('input[type=checkbox]');
      if(cb.checked){
        var tag = document.createElement('span');
        tag.className = 'rw-tag';
        tag.appendChild(document.createTextNode(cb.getAttribute('data-name')));
        var x = document.createElement('button');
        x.type = 'button';
        x.setAttribute('aria-label','Remove');
        x.textContent = '\u00d7';
        x.addEventListener('click', function(){ cb.checked = false; renderTags(); });
        tag.appendChild(x);
        selected.appendChild(tag);
      }
    });
  }
  rows.forEach(function(row){
    var cb = row.querySelector('input[type=checkbox]');
    cb.addEventListener('change', renderTags);
  });
  search.addEventListener('input', function(){
    var q = search.value.trim().toLowerCase();
    var any = false;
    rows.forEach(function(row){
      var hay = row.getAttribute('data-search') || '';
      var show = q === '' || hay.indexOf(q) >= 0;
      row.style.display = show ? '' : 'none';
      if(show) any = true;
    });
    if(noResult) noResult.style.display = any ? 'none' : '';
  });
  renderTags();
})();
</script>
</body>
</html>