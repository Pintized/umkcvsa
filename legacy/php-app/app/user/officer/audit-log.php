<?php
// =====================================================================
// UMKC VSA - Officer Audit Log
// Officers (and admins) can VIEW the log. Admins can additionally edit
// an entry's details, delete a single entry, or clear the whole log.
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../partials/audit.php';
require_login();
require_officer();

$user    = current_user();
$isAdmin = has_role($user, 'admin');
$panel   = isset($_GET['panel']);
$notice  = '';
$error   = '';

audit_ensure_table();

// ---- Admin-only actions -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        $error = 'Only admins can modify the audit log.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'clear') {
            db()->exec('DELETE FROM app_audit_log');
            $notice = 'Audit log cleared.';
        } elseif ($action === 'delete_one') {
            $eid = (int)($_POST['id'] ?? 0);
            if ($eid > 0) {
                $stmt = db()->prepare('DELETE FROM app_audit_log WHERE id = ?');
                $stmt->execute([$eid]);
                $notice = 'Entry deleted.';
            }
        } elseif ($action === 'edit') {
            $eid = (int)($_POST['id'] ?? 0);
            $det = mb_substr(trim($_POST['details'] ?? ''), 0, 500);
            if ($eid > 0) {
                $stmt = db()->prepare('UPDATE app_audit_log SET details = ? WHERE id = ?');
                $stmt->execute([$det, $eid]);
                $notice = 'Entry updated.';
            }
        }
    }
}

// ---- Load entries (newest first) ---------------------------------------
$panelQS = $panel ? '?panel=1' : '';
$editId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$rows = db()->query(
    'SELECT id, user_email, action, entity, details, created_at
     FROM app_audit_log ORDER BY created_at DESC, id DESC LIMIT 500'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Log | UMKC VSA</title>
<script>(function(){try{if(localStorage.getItem('vsa-theme')==='dark'){document.documentElement.classList.add('dark-mode');}}catch(e){}})();</script>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#eef2f7; margin:0; color:#1f2d3d; }
  .wrap { max-width: 980px; margin: 30px auto; padding: 0 16px; }
  .wrap.audit-wrap { max-width: 1180px; }
  .card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:28px; }
  h1 { margin-top:0; }
  .page-backbar { margin-bottom:16px; }
  .page-backbar a { color:#b1283b; text-decoration:none; font-size:14px; }
  .muted { color:#64748b; line-height:1.6; }
  table { width:100%; border-collapse:collapse; margin-top:18px; font-size:14px; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid #edf1f5; vertical-align:top; }
  th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:700; text-transform:uppercase; }
  .badge.create { background:#e3f8ea; color:#1d7a3a; }
  .badge.update { background:#e7eefc; color:#1b4ea3; }
  .badge.delete { background:#fde3e3; color:#b51d1d; }
  .badge.other  { background:#eceff3; color:#52606d; }
  .when { white-space:nowrap; color:#64748b; }
  .actions form { display:inline; }
  .btn { border:none; border-radius:6px; padding:5px 10px; font-size:13px; cursor:pointer; }
  .btn-edit { background:#e7eefc; color:#1b4ea3; }
  .btn-del  { background:#fde3e3; color:#b51d1d; }
  .btn-clear{ background:#b51d1d; color:#fff; padding:8px 16px; }
  .btn-save { background:#1d7a3a; color:#fff; }
  .notice { background:#e3f8ea; color:#1d7a3a; padding:10px 14px; border-radius:8px; margin-bottom:14px; }
  .error  { background:#fde3e3; color:#b51d1d; padding:10px 14px; border-radius:8px; margin-bottom:14px; }
  .empty  { color:#64748b; padding:24px 0; }
  .readonly-note { display:inline-block; margin-top:14px; padding:8px 14px; background:#fff7e6; color:#92670c; border-radius:8px; font-size:13px; }
  .edit-input { width:100%; padding:6px 8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px; }
  .clear-row { margin-top:20px; }
  /* Dark mode */
  html.dark-mode body { background:#0f1a26; color:#e6edf3; }
  html.dark-mode .card { background:#16222f; box-shadow:none; }
  html.dark-mode h1, html.dark-mode h2, html.dark-mode h3 { color:#e6edf3; }
  html.dark-mode .muted, html.dark-mode .when, html.dark-mode th { color:#9fb0c0; }
  html.dark-mode .page-backbar a { color:#e2554f; }
  html.dark-mode th, html.dark-mode td { border-bottom-color:#28394a; }
  html.dark-mode .badge.create { background:#143020; color:#7fe0a0; }
  html.dark-mode .badge.update { background:#142038; color:#9fc0f0; }
  html.dark-mode .badge.delete { background:#3a1818; color:#f09a9a; }
  html.dark-mode .badge.other  { background:#26303a; color:#b6c6d6; }
  html.dark-mode .notice { background:#143020; color:#7fe0a0; }
  html.dark-mode .error  { background:#3a1818; color:#f09a9a; }
  html.dark-mode .readonly-note { background:#3a3416; color:#f0d98a; }
  html.dark-mode .edit-input { background:#0f1a26; color:#e6edf3; border-color:#28394a; }
  /* Panel mode (loaded inside dashboard pop-up) */
  body.panel-mode { background:transparent; }
  body.panel-mode .wrap { max-width:none; margin:0; padding:18px; }
  body.panel-mode .page-backbar { display:none; }
  /* ===== Filter bar + scrollable log (added) ===== */
  .log-toolbar { d<div class="wrap">
  <div class="page-backbar"><a href="/app/profile.php">&larr; Back to Dashboard</a></div>lay:flex; flex-direction:column; gap:4px; }
  .log-toolbar label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; font-weight:700; }
  .log-toolbar input { padding:7px 10px; border:1px solid #cbd5e0; border-radius:8px; font-size:13px; background:#fff; color:#1f2d3d; }
  .log-toolbar input.search { min-width:240px; }
  .log-toolbar .reset-btn { padding:7px 14px; border:1px solid #cbd5e0; border-radius:8px; background:#f5f7fa; color:#1f2d3d; font-size:13px; cursor:pointer; }
  .log-toolbar .reset-btn:hover { background:#eaeef3; }
  .log-count { font-size:12px; color:#64748b; margin:4px 0 0; }
  .log-scroll { max-height:460px; overflow-y:auto; border:1px solid #edf1f5; border-radius:10px; margin-top:8px; }
  .log-scroll table { margin-top:0; }
  .log-scroll thead th { position:sticky; top:0; background:#f7f9fc; z-index:2; box-shadow:inset 0 -1px 0 #e2e8f0; }
  /* Compact rows so the log no longer looks chunky */
  .log-scroll th, .log-scroll td { padding:7px 10px; }
  .log-scroll td.when { white-space:nowrap; font-variant-numeric:tabular-nums; color:#64748b; }
  /* Constrain the Details column (5th col) so long text wraps instead of stretching the row */
  /* Fixed table layout keeps columns sane so Details no longer wraps into a tall narrow strip */
  .log-scroll table { table-layout:fixed; width:100%; }
  .log-scroll th:nth-child(1), .log-scroll td:nth-child(1) { width:140px; }
  .log-scroll th:nth-child(2), .log-scroll td:nth-child(2) { width:150px; word-break:break-word; }
  .log-scroll th:nth-child(3), .log-scroll td:nth-child(3) { width:158px; white-space:normal; }
  .log-scroll td:nth-child(3) .badge { white-space:normal; word-break:break-word; line-height:1.3; }
  .log-scroll th:nth-child(4), .log-scroll td:nth-child(4) { width:88px; }
  .log-scroll th:nth-child(5), .log-scroll td:nth-child(5) { width:auto; line-height:1.45; word-break:break-word; white-space:normal; }
  html.dark-mode .log-toolbar label, html.dark-mode .log-count { color:#9fb0c0; }
  html.dark-mode .log-toolbar input { background:#0f1a26; color:#e6edf3; border-color:#28394a; }
  html.dark-mode .log-toolbar .reset-btn { background:#1c2a38; color:#e6edf3; border-color:#28394a; }
  html.dark-mode .log-scroll { border-color:#28394a; }
  html.dark-mode .log-scroll thead th { background:#13202c; box-shadow:inset 0 -1px 0 #28394a; }
  html.dark-mode .log-scroll td.when { color:#9fb0c0; }

</style>
</head>
<body<?php echo $panel ? ' class="panel-mode"' : ''; ?>>
  <?php $officerActive = 'audit'; include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-chrome.php'; ?>
  <div class="wrap audit-wrap">
  <div class="card">
    <h1>Audit Log</h1>
    <p class="muted">A record of officer actions such as event creation, updates, and deletions.
    <?php if ($isAdmin): ?>As an admin you can edit or remove entries.<?php else: ?>This view is read-only.<?php endif; ?></p>

    <?php if ($notice !== ''): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error  !== ''): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

    <?php if (!$rows): ?>
      <div class="empty">No audit entries yet. Actions will appear here as officers manage events.</div>
    <?php else: ?>
      <div class="log-toolbar">
        <div class="field" style="flex:1 1 240px;">
          <label for="logSearch">Search</label>
          <input type="text" id="logSearch" class="search" placeholder="Search user, action, entity, details...">
        </div>
        <div class="field">
          <label for="logFrom">From date</label>
          <input type="date" id="logFrom">
        </div>
        <div class="field">
          <label for="logTo">To date</label>
          <input type="date" id="logTo">
        </div>
        <button type="button" class="reset-btn" id="logReset">Reset</button>
      </div>
      <p class="log-count" id="logCount"></p>
      <div class="log-scroll">
      <table>
        <thead>
          <tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><?php if ($isAdmin): ?><th>Manage</th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $cls = in_array($r['action'], ['create','update','delete'], true) ? $r['action'] : 'other'; ?>
          <tr>
            <td class="when"><?= e($r['created_at']) ?></td>
            <td><?= e($r['user_email'] ?? 'unknown') ?></td>
            <td><span class="badge <?= e($cls) ?>"><?= e($r['action']) ?></span></td>
            <td><?= e($r['entity']) ?></td>
            <?php if ($isAdmin && $editId === (int)$r['id']): ?>
              <td colspan="2">
                <form method="post" action="<?= e($panelQS) ?>">
                  <input type="hidden" name="action" value="edit">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input class="edit-input" type="text" name="details" value="<?= e($r['details'] ?? '') ?>">
                  <div style="margin-top:8px">
                    <button class="btn btn-save" type="submit">Save</button>
                    <a class="btn btn-edit" href="audit-log.php<?= e($panelQS) ?>">Cancel</a>
                  </div>
                </form>
              </td>
            <?php else: ?>
              <td><?= e($r['details'] ?? '') ?></td>
              <?php if ($isAdmin): ?>
              <td class="actions">
                <a class="btn btn-edit" href="audit-log.php?edit=<?= (int)$r['id'] ?><?= $panel ? '&panel=1' : '' ?>">Edit</a>
                <form method="post" action="<?= e($panelQS) ?>" onsubmit="return confirm('Delete this audit entry?');">
                  <input type="hidden" name="action" value="delete_one">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-del" type="submit">Delete</button>
                </form>
              </td>
              <?php endif; ?>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>

    <?php if ($isAdmin && $rows): ?>
      <div class="clear-row">
        <form method="post" action="<?= e($panelQS) ?>" onsubmit="return confirm('Permanently clear the ENTIRE audit log? This cannot be undone.');">
          <input type="hidden" name="action" value="clear">
          <button class="btn btn-clear" type="submit">Clear entire log</button>
        </form>
      </div>
    <?php elseif (!$isAdmin): ?>
      <span class="readonly-note">Read-only &mdash; only admins can edit or delete audit entries.</span>
    <?php endif; ?>
  </div>
</div>
  <script>
  (function(){
    var search = document.getElementById("logSearch");
    var fromEl = document.getElementById("logFrom");
    var toEl   = document.getElementById("logTo");
    var reset  = document.getElementById("logReset");
    var countEl= document.getElementById("logCount");
    var table  = document.querySelector(".log-scroll table");
    if(!table) return;
    var rows = Array.prototype.slice.call(table.querySelectorAll("tbody tr"));
    var noResults;
    var colCount = (table.querySelector("thead tr")||{children:[]}).children.length || 5;
    function ensureNoResults(){
      if(noResults) return;
      var tb = table.querySelector("tbody");
      var tr = document.createElement("tr");
      var td = document.createElement("td");
      td.colSpan = colCount;
      td.style.textAlign = "center"; td.style.color = "#64748b"; td.style.padding = "22px 0";
      td.textContent = "No entries match your filters.";
      tr.appendChild(td); tb.appendChild(tr); noResults = tr;
    }
    function rowDate(tr){
      var c = tr.querySelector("td.when");
      if(!c) return null;
      var t = (c.textContent||"").trim().slice(0,10);
      return /^\d{4}-\d{2}-\d{2}$/.test(t) ? t : null;
    }
    function apply(){
      var q = (search.value||"").trim().toLowerCase();
      var f = fromEl.value || "";
      var t = toEl.value || "";
      var shown = 0;
      rows.forEach(function(tr){
        var txt = (tr.textContent||"").toLowerCase();
        var d = rowDate(tr);
        var ok = true;
        if(q && txt.indexOf(q) === -1) ok = false;
        if(ok && f && d && d < f) ok = false;
        if(ok && t && d && d > t) ok = false;
        tr.style.display = ok ? "" : "none";
        if(ok) shown++;
      });
      ensureNoResults();
      noResults.style.display = shown === 0 ? "" : "none";
      if(countEl) countEl.textContent = "Showing " + shown + " of " + rows.length + " entries";
    }
    search.addEventListener("input", apply);
    fromEl.addEventListener("change", apply);
    toEl.addEventListener("change", apply);
    reset.addEventListener("click", function(){ search.value=""; fromEl.value=""; toEl.value=""; apply(); });
    apply();
  })();
  </script>
  
</body>
</html>