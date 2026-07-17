<?php
require_once __DIR__ . '/../../auth.php';
require_login();
require_officer();

$user = current_user();
$panel = isset($_GET['panel']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Roles | UMKC VSA</title>
<script>(function(){try{if(localStorage.getItem('vsa-theme')==='dark'){document.documentElement.classList.add('dark-mode');}}catch(e){}})();</script>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#eef2f7; margin:0; color:#1f2d3d; }
  .wrap { max-width: 860px; margin: 30px auto; padding: 0 16px; }
  .card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:28px; }
  h1 { margin-top:0; }
  .topbar { margin-bottom:16px; }
  .topbar a { color:#b1283b; text-decoration:none; font-size:14px; }
  .muted { color:#64748b; line-height:1.6; }
  .roles-list { margin-top:18px; }
  .role-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #edf1f5; }
  .badge { display:inline-block; padding:3px 12px; border-radius:999px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .badge.member { background:#e3edf9; color:#1d4e89; }
  .badge.officer { background:#e7f6ec; color:#1b7a3d; }
  .badge.alumni { background:#f3e8fa; color:#7b2d9e; }
  .badge.intern { background:#fdf0e3; color:#b56a18; }
  .role-desc { font-size:14px; color:#475569; }
  .soon { display:inline-block; margin-top:20px; padding:10px 14px; background:#fff7e6; color:#92670c; border-radius:8px; font-size:14px; }
  /* Dark mode */
  html.dark-mode body { background:#0f1a26; color:#e6edf3; }
  html.dark-mode .card { background:#16222f; box-shadow:none; }
  html.dark-mode h1, html.dark-mode h2, html.dark-mode h3 { color:#e6edf3; }
  html.dark-mode .muted, html.dark-mode .role-desc { color:#9fb0c0; }
  html.dark-mode .topbar a { color:#e2554f; }
  html.dark-mode .role-item { border-bottom-color:#28394a; }
  html.dark-mode .badge.member { background:#20364a; color:#cfe0f0; }
  html.dark-mode .badge.officer { background:#3a2520; color:#f0c9c5; }
  html.dark-mode .badge.alumni { background:#2a2438; color:#d6c9f0; }
  html.dark-mode .badge.intern { background:#2f2a18; color:#f0e2b0; }
  html.dark-mode .soon { background:#3a3416; color:#f0d98a; }
  /* Panel mode (loaded inside dashboard pop-up) */
  body.panel-mode { background:transparent; }
  body.panel-mode .wrap { max-width:none; margin:0; padding:18px; }
  body.panel-mode .topbar { display:none; }
</style>
</head>
<body<?php echo $panel ? ' class="panel-mode"' : ''; ?>>
  <?php $officerActive = 'roles'; include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-chrome.php'; ?>
  <div class="wrap">
  <div class="card">
    <h1>Roles</h1>
    <p class="muted">The organization uses the following membership roles. A member can hold more than one role at a time (for example, both Member and Officer).</p>
    <div class="roles-list">
      <div class="role-item"><span class="badge member">Member</span><span class="role-desc">Default role for every registered member.</span></div>
      <div class="role-item"><span class="badge officer">Officer</span><span class="role-desc">Board members with access to the Officer tools (events, roles, permissions, tasks).</span></div>
      <div class="role-item"><span class="badge alumni">Alumni</span><span class="role-desc">Former members who have graduated or moved on.</span></div>
      <div class="role-item"><span class="badge intern">Intern</span><span class="role-desc">Members serving in an internship capacity.</span></div>
    </div>
    <div class="soon">Assigning and removing roles from members will be available here soon.</div>
  </div>
</div>
</body>
</html>
