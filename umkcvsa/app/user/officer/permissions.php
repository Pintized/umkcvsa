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
<title>Permissions | UMKC VSA</title>
<script>(function(){try{if(localStorage.getItem('vsa-theme')==='dark'){document.documentElement.classList.add('dark-mode');}}catch(e){}})();</script>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#eef2f7; margin:0; color:#1f2d3d; }
  .wrap { max-width: 860px; margin: 30px auto; padding: 0 16px; }
  .card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:28px; }
  h1 { margin-top:0; }
  .topbar { margin-bottom:16px; }
  .topbar a { color:#b1283b; text-decoration:none; font-size:14px; }
  .muted { color:#64748b; line-height:1.6; }
  table { width:100%; border-collapse:collapse; margin-top:18px; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid #edf1f5; font-size:14px; }
  th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .yes { color:#1b7a3d; font-weight:700; }
  .no { color:#cbd5e1; }
  .soon { display:inline-block; margin-top:20px; padding:10px 14px; background:#fff7e6; color:#92670c; border-radius:8px; font-size:14px; }
  /* Dark mode */
  html.dark-mode body { background:#0f1a26; color:#e6edf3; }
  html.dark-mode .card { background:#16222f; box-shadow:none; }
  html.dark-mode h1, html.dark-mode h2, html.dark-mode h3 { color:#e6edf3; }
  html.dark-mode .muted { color:#9fb0c0; }
  html.dark-mode .topbar a { color:#e2554f; }
  html.dark-mode th, html.dark-mode td { border-bottom-color:#28394a; }
  html.dark-mode th { color:#9fb0c0; }
  html.dark-mode .yes { color:#5fcf8a; }
  html.dark-mode .no { color:#5a6b7d; }
  html.dark-mode .soon { background:#3a3416; color:#f0d98a; }
  /* Panel mode (loaded inside dashboard pop-up) */
  body.panel-mode { background:transparent; }
  body.panel-mode .wrap { max-width:none; margin:0; padding:18px; }
  body.panel-mode .topbar { display:none; }
</style>
</head>
<body<?php echo $panel ? ' class="panel-mode"' : ''; ?>>
  <?php $officerActive = 'permissions'; include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-chrome.php'; ?>
  <div class="wrap">
  <div class="card">
    <h1>Permissions</h1>
    <p class="muted">Overview of what each role can do within the member portal. Officers and admins have access to the Officer tools.</p>
    <table>
      <thead><tr><th>Capability</th><th>Member</th><th>Officer</th><th>Admin</th></tr></thead>
      <tbody>
        <tr><td>View dashboard &amp; calendar</td><td class="yes">&#10003;</td><td class="yes">&#10003;</td><td class="yes">&#10003;</td></tr>
        <tr><td>Add / edit / delete events</td><td class="no">&mdash;</td><td class="yes">&#10003;</td><td class="yes">&#10003;</td></tr>
        <tr><td>Access Officer menu</td><td class="no">&mdash;</td><td class="yes">&#10003;</td><td class="yes">&#10003;</td></tr>
        <tr><td>Manage member roles</td><td class="no">&mdash;</td><td class="no">&mdash;</td><td class="yes">&#10003;</td></tr>
      </tbody>
    </table>
    <div class="soon">Fine-grained, editable permission controls will be available here soon.</div>
  </div>
</div>
</body>
</html>
