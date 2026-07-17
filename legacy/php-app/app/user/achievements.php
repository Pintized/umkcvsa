<?php
// ======================================================================
// UMKC VSA - Achievements Page
// ======================================================================
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../partials/audit.php';

$user = require_login();
$pdo  = db();
$pdo->exec("SET NAMES utf8mb4");

$isAdmin   = has_role($user, 'admin');
$isOfficer = has_role($user, 'officer') || $isAdmin;

if (!function_exists('h2')) {
    function h2($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// ---- Ensure tables exist ----------------------------------------------
$pdo->exec("CREATE TABLE IF NOT EXISTS app_achievements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    points INT UNSIGNED NOT NULL DEFAULT 0,
    icon VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS app_achievement_awards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    achievement_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    awarded_by INT UNSIGNED NULL,
    points_awarded INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_ach (achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$err = '';

// ---- Handle POST actions ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ===== ADMIN ONLY: Create / Update / Delete achievement definitions =====
    if (in_array($action, ['ach_create', 'ach_update', 'ach_delete'], true)) {
        if (!$isAdmin) {
            $err = 'Only admins can manage achievements.';
        } elseif ($action === 'ach_create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $pts  = max(0, (int)($_POST['points'] ?? 0));
            $icon = trim((string)($_POST['icon'] ?? ''));
            if ($name === '') {
                $err = 'Achievement name is required.';
            } else {
                $st = $pdo->prepare("INSERT INTO app_achievements (name, description, points, icon, created_by) VALUES (?, ?, ?, ?, ?)");
                $st->execute([$name, ($desc !== '' ? $desc : null), $pts, ($icon !== '' ? $icon : null), (int)$user['id']]);
                log_audit('achievement_create', 'achievement', $name . ' (' . $pts . ' pts)');
                $msg = 'Achievement "' . $name . '" created.';
            }
        } elseif ($action === 'ach_update') {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $pts  = max(0, (int)($_POST['points'] ?? 0));
            $icon = trim((string)($_POST['icon'] ?? ''));
            $active = isset($_POST['active']) ? 1 : 0;
            if ($id <= 0 || $name === '') {
                $err = 'Invalid achievement data.';
            } else {
                $st = $pdo->prepare("UPDATE app_achievements SET name=?, description=?, points=?, icon=?, active=? WHERE id=?");
                $st->execute([$name, ($desc !== '' ? $desc : null), $pts, ($icon !== '' ? $icon : null), $active, $id]);
                log_audit('achievement_update', 'achievement', '#' . $id . ' ' . $name . ' (' . $pts . ' pts)');
                $msg = 'Achievement "' . $name . '" updated.';
            }
        } elseif ($action === 'ach_delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $row = $pdo->prepare("SELECT name FROM app_achievements WHERE id=?");
                $row->execute([$id]);
                $nm = (string)($row->fetchColumn() ?: ('#' . $id));
                $pdo->prepare("DELETE FROM app_achievements WHERE id=?")->execute([$id]);
                log_audit('achievement_delete', 'achievement', $nm);
                $msg = 'Achievement deleted.';
            }
        }
    }

    // ===== OFFICER / ADMIN: Award an achievement to one or more members =====
    elseif ($action === 'award_achievement') {
        if (!$isOfficer) {
            $err = 'Only officers can award achievements.';
        } else {
            $achId = (int)($_POST['achievement_id'] ?? 0);
            $rawIds = $_POST['member_ids'] ?? [];
            if (!is_array($rawIds)) { $rawIds = [$rawIds]; }
            $memberIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn($v) => $v > 0)));

            // Load the achievement
            $aStmt = $pdo->prepare("SELECT id, name, points, active FROM app_achievements WHERE id=?");
            $aStmt->execute([$achId]);
            $ach = $aStmt->fetch(PDO::FETCH_ASSOC);

            if (!$ach) {
                $err = 'Please choose a valid achievement.';
            } elseif ((int)$ach['active'] !== 1) {
                $err = 'That achievement is inactive and cannot be awarded.';
            } elseif (count($memberIds) === 0) {
                $err = 'Please select at least one member to award.';
            } else {
                $pts = (int)$ach['points'];
                // Fetch the names of the selected members for the flash + audit
                $ph = implode(',', array_fill(0, count($memberIds), '?'));
                $mStmt = $pdo->prepare("SELECT id, full_name, email, points FROM app_users WHERE id IN ($ph)");
                $mStmt->execute($memberIds);
                $members = $mStmt->fetchAll(PDO::FETCH_ASSOC);

                $awardIns = $pdo->prepare("INSERT INTO app_achievement_awards (achievement_id, user_id, awarded_by, points_awarded) VALUES (?, ?, ?, ?)");
                $ptsUpd   = $pdo->prepare("UPDATE app_users SET points = points + ? WHERE id = ?");

                $names = [];
                foreach ($members as $m) {
                    $awardIns->execute([(int)$ach['id'], (int)$m['id'], (int)$user['id'], $pts]);
                    if ($pts > 0) {
                        $ptsUpd->execute([$pts, (int)$m['id']]);
                    }
                    $newTotal = (int)$m['points'] + $pts;
                    $label = $m['full_name'] !== '' ? $m['full_name'] : $m['email'];
                    $names[] = $label;
                    log_audit(
                        'achievement_award',
                        'achievement',
                        'Awarded "' . $ach['name'] . '" (+' . $pts . ' pts) to ' . $label . ' — new total ' . $newTotal . ' pts'
                    );
                }
                $msg = 'Awarded "' . $ach['name'] . '" (+' . $pts . ' pts) to ' . count($names) . ' member' . (count($names) === 1 ? '' : 's') . ': ' . implode(', ', $names) . '.';
            }
        }
    }
}

// ---- Load data for the page -------------------------------------------
// All achievements (members see active ones; admins/officers see all)
if ($isOfficer) {
    $achievements = $pdo->query("SELECT id, name, description, points, icon, active FROM app_achievements ORDER BY points ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $achievements = $pdo->query("SELECT id, name, description, points, icon, active FROM app_achievements WHERE active = 1 ORDER BY points ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Members list for the award picker (officers/admins only)
$members = [];
if ($isOfficer) {
    $members = $pdo->query("SELECT id, full_name, email, points FROM app_users ORDER BY full_name ASC, email ASC")->fetchAll(PDO::FETCH_ASSOC);
    $memberAwardCounts = [];
    foreach ($pdo->query("SELECT user_id, achievement_id, COUNT(*) AS c FROM app_achievement_awards GROUP BY user_id, achievement_id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $memberAwardCounts[(int)$r['user_id']][(int)$r['achievement_id']] = (int)$r['c'];
    }
}

// Achievements the current user has earned (for the "Earned" badge)
$earnedCounts = [];
$eStmt = $pdo->prepare("SELECT achievement_id, COUNT(*) AS c FROM app_achievement_awards WHERE user_id = ? GROUP BY achievement_id");
$eStmt->execute([(int)$user['id']]);
foreach ($eStmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $earnedCounts[(int)$r['achievement_id']] = (int)$r['c']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Achievements | UMKC VSA</title>
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
/* ===== Achievements page styles (rw- shared) ===== */
.rw-flash{padding:12px 16px;border-radius:10px;margin:0 0 18px;font-weight:600;}
.rw-flash-ok{background:#e7f6ec;color:#1c7a3e;border:1px solid #bfe6cd;}
.rw-flash-err{background:#fdeaea;color:#b3261e;border:1px solid #f3c4c1;}
.rw-section{margin:0 0 34px;}
.rw-section-title{font-size:1.25rem;margin:0 0 14px;color:var(--text);display:flex;align-items:center;gap:10px;}
.rw-tag{font-size:.7rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;background:var(--navy);color:#fff;padding:3px 8px;border-radius:999px;}
.rw-empty{color:var(--muted);font-style:italic;}
.rw-muted{color:var(--muted);font-size:.85rem;}

/* Cards grid */
.rw-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px;}
.rw-card{background:var(--vsa-card,#fff);border:1px solid rgba(0,0,0,.08);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;transition:transform .15s ease,box-shadow .15s ease;box-shadow:0 1px 3px rgba(0,0,0,.06);}
.rw-card:hover{transform:translateY(-4px);box-shadow:0 8px 22px rgba(0,0,0,.12);}
.rw-card-inactive{opacity:.6;}
.rw-card-earned{border-color:var(--red);box-shadow:0 0 0 2px rgba(180,30,40,.18);}
.rw-card-media{position:relative;background:linear-gradient(135deg,#f3f5f9,#e7ebf2);display:flex;align-items:center;justify-content:center;padding:20px;min-height:120px;}
.rw-card-img{max-width:90px;max-height:90px;object-fit:contain;}
.rw-badge{position:absolute;top:10px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:4px 9px;border-radius:999px;color:#fff;}
.rw-badge-earned{right:10px;background:var(--red);}
.rw-badge-off{left:10px;background:#888;}
.rw-card-body{padding:14px 16px 6px;flex:1;}
.rw-card-name{margin:0 0 6px;font-size:1.05rem;color:var(--text);}
.rw-card-desc{margin:0;color:var(--muted);font-size:.85rem;line-height:1.4;}
.rw-card-foot{display:flex;align-items:center;justify-content:space-between;padding:12px 16px 16px;}
.rw-cost{font-weight:800;color:var(--red);font-size:1.05rem;}
.rw-chip{font-size:.72rem;font-weight:700;padding:4px 10px;border-radius:999px;}
.rw-chip-ok{background:#e7f6ec;color:#1c7a3e;}
.rw-chip-off{background:#eee;color:#777;}

/* Officer / admin panels */
.rw-officer{background:var(--vsa-card,#fff);border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
.rw-officer-hint{color:var(--muted);font-size:.88rem;margin:0 0 16px;}
.rw-form{display:flex;flex-direction:column;gap:6px;max-width:560px;}
.rw-form-inline{max-width:760px;}
.rw-row{display:flex;gap:14px;flex-wrap:wrap;}
.rw-field{flex:1;min-width:200px;display:flex;flex-direction:column;gap:4px;margin-bottom:8px;}
.rw-field-sm{flex:0 0 140px;min-width:120px;}
.rw-label{font-size:.8rem;font-weight:600;color:var(--text);margin-top:6px;}
.rw-input{padding:10px 12px;border:1px solid rgba(0,0,0,.18);border-radius:9px;font-size:.92rem;background:#fff;color:#1a1a1a;width:100%;box-sizing:border-box;}
.rw-input:focus{outline:none;border-color:var(--red);box-shadow:0 0 0 3px rgba(180,30,40,.12);}
.rw-check{display:flex;align-items:center;gap:8px;font-size:.9rem;margin:8px 0;color:var(--text);}
.rw-btn{cursor:pointer;border:none;border-radius:9px;padding:10px 18px;font-size:.9rem;font-weight:700;margin-top:10px;align-self:flex-start;}
.rw-btn-primary{background:var(--red);color:#fff;}
.rw-btn-primary:hover{filter:brightness(1.08);}
.rw-btn-danger{background:#fff;color:#b3261e;border:1px solid #e7b6b3;}
.rw-btn-danger:hover{background:#fdeaea;}
.rw-btn-sm{padding:6px 12px;font-size:.8rem;margin-top:0;}

/* Member picker */
.rw-picker{border:1px solid rgba(0,0,0,.14);border-radius:10px;padding:10px;margin-bottom:6px;}
.rw-selected{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;}
.rw-selected:empty{display:none;}
.rw-chip-sel{display:inline-flex;align-items:center;gap:6px;background:var(--red);color:#fff;font-size:.8rem;font-weight:600;padding:4px 6px 4px 10px;border-radius:999px;}
.rw-chip-x{background:rgba(255,255,255,.25);border:none;color:#fff;width:18px;height:18px;border-radius:50%;cursor:pointer;line-height:1;font-size:.85rem;display:flex;align-items:center;justify-content:center;}
.rw-chip-x:hover{background:rgba(255,255,255,.45);}
.rw-memberlist{max-height:240px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;margin-top:6px;}
.rw-member{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;}
.rw-member:hover{background:rgba(0,0,0,.04);}
.rw-member-name{flex:1;font-size:.9rem;color:var(--text);}
.rw-member-pts{font-size:.78rem;color:var(--muted);}

/* Manage table */
.rw-table-wrap{overflow-x:auto;margin-top:18px;}
.rw-table{width:100%;border-collapse:collapse;font-size:.9rem;}
.rw-table th,.rw-table td{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(0,0,0,.08);vertical-align:top;}
.rw-table th{color:var(--muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.03em;}
.rw-actions{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;}
.rw-edit summary{list-style:none;display:inline-block;}
.rw-edit summary::-webkit-details-marker{display:none;}
.rw-editform{display:flex;flex-direction:column;gap:4px;margin-top:8px;padding:12px;border:1px solid rgba(0,0,0,.12);border-radius:10px;background:rgba(0,0,0,.02);min-width:240px;}
.rw-inline{margin:0;display:inline;}

/* Dark mode */
html.dark .rw-input{background:#1e2330;color:#e8eaf0;border-color:rgba(255,255,255,.18);}
html.dark .rw-card,html.dark .rw-officer{border-color:rgba(255,255,255,.1);}
html.dark .rw-card-media{background:linear-gradient(135deg,#222838,#1a1f2b);}
html.dark .rw-member:hover{background:rgba(255,255,255,.06);}
html.dark .rw-editform{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.12);}
html.dark .rw-table th,html.dark .rw-table td{border-color:rgba(255,255,255,.1);}
html.dark .rw-chip-off{background:#333;color:#bbb;}
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
      <h1>Achievements</h1>
      <p>Unlock achievements by participating in UMKC VSA. Each achievement is worth points.</p>
    </div>

    <?php if ($msg !== ''): ?><div class="rw-flash rw-flash-ok"><?= h2($msg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="rw-flash rw-flash-err"><?= h2($err) ?></div><?php endif; ?>

    <!-- ===================== ACHIEVEMENTS CATALOG (everyone) ===================== -->
    <section class="rw-section">
      <h2 class="rw-section-title">All Achievements</h2>
      <?php if (count($achievements) === 0): ?>
        <p class="rw-empty">No achievements have been created yet<?= $isAdmin ? ' — add one below.' : '.' ?></p>
      <?php else: ?>
      <div class="rw-grid">
        <?php foreach ($achievements as $a): $cnt = $earnedCounts[(int)$a['id']] ?? 0; $earned = $cnt > 0; ?>
        <div class="rw-card<?= ((int)$a['active'] !== 1) ? ' rw-card-inactive' : '' ?><?= $earned ? ' rw-card-earned' : '' ?>">
          <div class="rw-card-media">
            <img class="rw-card-img" src="<?= h2($a['icon'] ?: '/app/logo.png') ?>" alt="" onerror="this.onerror=null;this.src='/assets/logo.png';">
            <?php if ($earned): ?><span class="rw-badge rw-badge-earned">Earned &times;<?= $cnt ?></span><?php endif; ?>
            <?php if ((int)$a['active'] !== 1): ?><span class="rw-badge rw-badge-off">Inactive</span><?php endif; ?>
          </div>
          <div class="rw-card-body">
            <h3 class="rw-card-name"><?= h2($a['name']) ?></h3>
            <?php if (!empty($a['description'])): ?><p class="rw-card-desc"><?= h2($a['description']) ?></p><?php endif; ?>
          </div>
          <div class="rw-card-foot">
            <span class="rw-cost"><?= (int)$a['points'] ?> pts</span>
            <?php if ($earned): ?><span class="rw-chip rw-chip-ok"><?= $cnt > 1 ? 'Earned &times;' . $cnt : 'Unlocked' ?></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <?php if ($isOfficer): ?>
    <!-- ===================== AWARD ACHIEVEMENT (officers + admins) ===================== -->
    <section class="rw-section rw-officer">
      <h2 class="rw-section-title">Award an Achievement</h2>
      <p class="rw-officer-hint">Awarding an achievement grants its points to each selected member. This is recorded in the audit log.</p>
      <form method="post" class="rw-form" id="awardForm">
        <input type="hidden" name="action" value="award_achievement">

        <label class="rw-label" for="awAch">Achievement</label>
        <select class="rw-input" name="achievement_id" id="awAch" required>
          <option value="">— Choose an achievement —</option>
          <?php foreach ($achievements as $a): if ((int)$a['active'] !== 1) continue; ?>
            <option value="<?= (int)$a['id'] ?>"><?= h2($a['name']) ?> (+<?= (int)$a['points'] ?> pts)</option>
          <?php endforeach; ?>
        </select>

        <label class="rw-label">Members</label>
        <div class="rw-picker">
          <input type="text" class="rw-input" id="rwSearch" placeholder="Search members by name or email…" autocomplete="off">
          <div class="rw-selected" id="rwSelected"></div>
          <div class="rw-memberlist" id="rwMemberList">
            <?php foreach ($members as $m): $label = $m['full_name'] !== '' ? $m['full_name'] : $m['email']; ?>
            <label class="rw-member" data-uid="<?= (int)$m['id'] ?>" data-name="<?= h2(strtolower($m['full_name'] . ' ' . $m['email'])) ?>">
              <input type="checkbox" name="member_ids[]" value="<?= (int)$m['id'] ?>" data-label="<?= h2($label) ?>">
              <span class="rw-member-name"><?= h2($label) ?></span>
              <span class="rw-member-pts"><?= (int)$m['points'] ?> pts</span>
              <span class="rw-member-count" data-uid="<?= (int)$m['id'] ?>"></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="rw-btn rw-btn-primary">Award Achievement</button>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <!-- ===================== MANAGE ACHIEVEMENTS (admins only) ===================== -->
    <section class="rw-section rw-officer">
      <h2 class="rw-section-title">Manage Achievements <span class="rw-tag">Admin only</span></h2>

      <form method="post" class="rw-form rw-form-inline">
        <input type="hidden" name="action" value="ach_create">
        <div class="rw-row">
          <div class="rw-field">
            <label class="rw-label" for="acName">Name</label>
            <input class="rw-input" type="text" name="name" id="acName" required maxlength="150" placeholder="e.g. First Event Attended">
          </div>
          <div class="rw-field rw-field-sm">
            <label class="rw-label" for="acPts">Points</label>
            <input class="rw-input" type="number" name="points" id="acPts" min="0" value="0" required>
          </div>
        </div>
        <div class="rw-row">
          <div class="rw-field">
            <label class="rw-label" for="acDesc">Description</label>
            <input class="rw-input" type="text" name="description" id="acDesc" maxlength="500" placeholder="Optional description">
          </div>
          <div class="rw-field">
            <label class="rw-label" for="acIcon">Image URL</label>
            <input class="rw-input" type="text" name="icon" id="acIcon" maxlength="255" placeholder="/app/logo.png (optional)">
          </div>
        </div>
        <button type="submit" class="rw-btn rw-btn-primary">Add Achievement</button>
      </form>

      <div class="rw-table-wrap">
        <table class="rw-table">
          <thead><tr><th>Name</th><th>Points</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if (count($achievements) === 0): ?>
            <tr><td colspan="4" class="rw-empty">No achievements yet.</td></tr>
          <?php else: foreach ($achievements as $a): ?>
            <tr>
              <td><strong><?= h2($a['name']) ?></strong><?php if (!empty($a['description'])): ?><br><span class="rw-muted"><?= h2($a['description']) ?></span><?php endif; ?></td>
              <td><?= (int)$a['points'] ?></td>
              <td><?= ((int)$a['active'] === 1) ? '<span class="rw-chip rw-chip-ok">Active</span>' : '<span class="rw-chip rw-chip-off">Inactive</span>' ?></td>
              <td class="rw-actions">
                <details class="rw-edit">
                  <summary class="rw-btn rw-btn-sm">Edit</summary>
                  <form method="post" class="rw-editform">
                    <input type="hidden" name="action" value="ach_update">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <label class="rw-label">Name</label>
                    <input class="rw-input" type="text" name="name" value="<?= h2($a['name']) ?>" required maxlength="150">
                    <label class="rw-label">Description</label>
                    <input class="rw-input" type="text" name="description" value="<?= h2($a['description']) ?>" maxlength="500">
                    <label class="rw-label">Points</label>
                    <input class="rw-input" type="number" name="points" value="<?= (int)$a['points'] ?>" min="0" required>
                    <label class="rw-label">Image URL</label>
                    <input class="rw-input" type="text" name="icon" value="<?= h2($a['icon']) ?>" maxlength="255">
                    <label class="rw-check"><input type="checkbox" name="active" <?= ((int)$a['active'] === 1) ? 'checked' : '' ?>> Active</label>
                    <button type="submit" class="rw-btn rw-btn-primary rw-btn-sm">Save</button>
                  </form>
                </details>
                <form method="post" class="rw-inline" onsubmit="return confirm('Delete this achievement? This cannot be undone.');">
                  <input type="hidden" name="action" value="ach_delete">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" class="rw-btn rw-btn-danger rw-btn-sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
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
    var RW_MEMBER_COUNTS = <?= json_encode($memberAwardCounts ?? [], JSON_UNESCAPED_UNICODE) ?>;
    (function () {
      var search = document.getElementById('rwSearch');
      var list = document.getElementById('rwMemberList');
      var selected = document.getElementById('rwSelected');
      var achSel = document.getElementById('awAch');
      if (!list || !selected) return;

      var members = Array.prototype.slice.call(list.querySelectorAll('.rw-member'));

      function renderCounts() {
        var aid = achSel ? parseInt(achSel.value, 10) : 0;
        members.forEach(function (row) {
          var span = row.querySelector('.rw-member-count');
          if (!span) return;
          var uid = row.getAttribute('data-uid');
          var n = (aid && RW_MEMBER_COUNTS[uid] && RW_MEMBER_COUNTS[uid][aid]) ? RW_MEMBER_COUNTS[uid][aid] : 0;
          span.textContent = n > 0 ? ('earned \u00d7' + n) : '';
        });
      }

      function renderChips() {
        selected.innerHTML = '';
        members.forEach(function (row) {
          var cb = row.querySelector('input[type=checkbox]');
          if (cb && cb.checked) {
            var chip = document.createElement('span');
            chip.className = 'rw-chip-sel';
            chip.textContent = cb.getAttribute('data-label') || '';
            var x = document.createElement('button');
            x.type = 'button'; x.className = 'rw-chip-x'; x.setAttribute('aria-label', 'Remove'); x.textContent = '\u00d7';
            x.addEventListener('click', function () { cb.checked = false; renderChips(); });
            chip.appendChild(x); selected.appendChild(chip);
          }
        });
      }

      list.addEventListener('change', function (e) { if (e.target && e.target.type === 'checkbox') renderChips(); });
      if (achSel) achSel.addEventListener('change', renderCounts);
      if (search) {
        search.addEventListener('input', function () {
          var q = search.value.trim().toLowerCase();
          members.forEach(function (row) {
            var name = row.getAttribute('data-name') || '';
            row.style.display = (q === '' || name.indexOf(q) !== -1) ? '' : 'none';
          });
        });
      }

      renderChips();
      renderCounts();
    })();
    </script>
</body>
</html>