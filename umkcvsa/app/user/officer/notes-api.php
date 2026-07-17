<?php
declare(strict_types=1);
// ============================================================
// UMKC VSA - Officer Notes API
// Folders + rich-text meeting notes with auto-save.
// Any officer may create/edit/delete folders and notes.
// ============================================================

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../partials/audit.php';
require_login();
require_officer();

header('Content-Type: application/json');

$pdo  = db();
$user = current_user();
$uid  = (int)$user['id'];

function notes_ensure_tables(PDO $pdo): void {
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS app_note_folders (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      created_by INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS app_notes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      folder_id INT NULL,
      title VARCHAR(255) NOT NULL DEFAULT 'Untitled note',
      content MEDIUMTEXT NULL,
      created_by INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (folder_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
}
notes_ensure_tables($pdo);

function out($data): void { echo json_encode($data); exit; }
function fail(string $msg, int $code = 400): void { http_response_code($code); echo json_encode(['error' => $msg]); exit; }

function creator_name(PDO $pdo, ?int $id): string {
  if (!$id) return 'Unknown';
  $st = $pdo->prepare('SELECT full_name, first_name, last_name FROM app_users WHERE id = ? LIMIT 1');
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) return 'Unknown';
  if (!empty($r['full_name'])) return $r['full_name'];
  return trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Unknown';
}
function folders_with_meta(PDO $pdo): array {
  $rows = $pdo->query('SELECT id, name, created_by, created_at, updated_at FROM app_note_folders ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$f) {
    $c = $pdo->prepare('SELECT COUNT(*) FROM app_notes WHERE folder_id = ?');
    $c->execute([(int)$f['id']]);
    $f['note_count'] = (int)$c->fetchColumn();
    $f['created_by_name'] = creator_name($pdo, $f['created_by'] !== null ? (int)$f['created_by'] : null);
  }
  return $rows;
}

function notes_in_folder(PDO $pdo, int $fid): array {
  $st = $pdo->prepare('SELECT id, folder_id, title, created_by, created_at, updated_at FROM app_notes WHERE folder_id = ? ORDER BY updated_at DESC');
  $st->execute([$fid]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$n) { $n['created_by_name'] = creator_name($pdo, $n['created_by'] !== null ? (int)$n['created_by'] : null); }
  return $rows;
}

$action = $_GET['action'] ?? '';

try {
  switch ($action) {

    case 'list':
      out(['folders' => folders_with_meta($pdo)]);
      break;

    case 'folder_notes': {
      $fid = (int)($_GET['folder_id'] ?? 0);
      if (!$fid) fail('Missing folder_id');
      out(['notes' => notes_in_folder($pdo, $fid)]);
      break;
    }

    case 'create_folder': {
      $name = trim((string)($_POST['name'] ?? ''));
      if ($name === '') $name = 'New folder';
      $st = $pdo->prepare('INSERT INTO app_note_folders (name, created_by) VALUES (?, ?)');
      $st->execute([$name, $uid]);
      $newId = (int)$pdo->lastInsertId();
      log_audit('create', 'note_folder', 'Created folder "' . $name . '" (#' . $newId . ')');
      out(['ok' => true, 'id' => $newId]);
      break;
    }

    case 'rename_folder': {
      $id = (int)($_POST['id'] ?? 0);
      $name = trim((string)($_POST['name'] ?? ''));
      if (!$id || $name === '') fail('Missing id or name');
      $st = $pdo->prepare('UPDATE app_note_folders SET name = ? WHERE id = ?');
      $st->execute([$name, $id]);
      log_audit('rename', 'note_folder', 'Renamed folder #' . $id . ' to "' . $name . '"');
      out(['ok' => true]);
      break;
    }

    case 'delete_folder': {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) fail('Missing id');
      $pdo->prepare('DELETE FROM app_notes WHERE folder_id = ?')->execute([$id]);
      $pdo->prepare('DELETE FROM app_note_folders WHERE id = ?')->execute([$id]);
      log_audit('delete', 'note_folder', 'Deleted folder #' . $id . ' and its notes');
      out(['ok' => true]);
      break;
    }
    case 'create_note': {
      $fid = (int)($_POST['folder_id'] ?? 0);
      if (!$fid) fail('Missing folder_id');
      $title = trim((string)($_POST['title'] ?? '')); if ($title === '') $title = 'Untitled note';
      $st = $pdo->prepare('INSERT INTO app_notes (folder_id, title, content, created_by) VALUES (?, ?, ?, ?)');
      $st->execute([$fid, $title, '', $uid]);
      $newId = (int)$pdo->lastInsertId();
      log_audit('create', 'note', 'Created note "' . $title . '" (#' . $newId . ') in folder #' . $fid);
      out(['ok' => true, 'id' => $newId]);
      break;
    }

    case 'get_note': {
      $id = (int)($_GET['id'] ?? 0);
      if (!$id) fail('Missing id');
      $st = $pdo->prepare('SELECT id, folder_id, title, content, created_by, created_at, updated_at FROM app_notes WHERE id = ? LIMIT 1');
      $st->execute([$id]);
      $n = $st->fetch(PDO::FETCH_ASSOC);
      if (!$n) fail('Not found', 404);
      $n['created_by_name'] = creator_name($pdo, $n['created_by'] !== null ? (int)$n['created_by'] : null);
      out(['note' => $n]);
      break;
    }

    case 'save_note': {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) fail('Missing id');
      $title = trim((string)($_POST['title'] ?? '')); if ($title === '') $title = 'Untitled note';
      $content = (string)($_POST['content'] ?? '');
      $st = $pdo->prepare('UPDATE app_notes SET title = ?, content = ? WHERE id = ?');
      $st->execute([$title, $content, $id]);
      log_audit('update', 'note', 'Edited note "' . $title . '" (#' . $id . ')');
      $t = $pdo->prepare('SELECT updated_at FROM app_notes WHERE id = ?'); $t->execute([$id]);
      out(['ok' => true, 'updated_at' => $t->fetchColumn()]);
      break;
    }

    case 'delete_note': {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) fail('Missing id');
      $pdo->prepare('DELETE FROM app_notes WHERE id = ?')->execute([$id]);
      log_audit('delete', 'note', 'Deleted note #' . $id);
      out(['ok' => true]);
      break;
    }

    default:
      fail('Unknown action');
  }
} catch (Throwable $e) {
  fail('Server error: ' . $e->getMessage(), 500);
}
