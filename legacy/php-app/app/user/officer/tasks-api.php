<?php
declare(strict_types=1);

// ============================================================
// UMKC VSA - Officer Tasks API
// JSON endpoint for the draggable task board.
// Actions: list, create, update, delete, move, assign, unassign
// Every officer may create/edit/delete/move any task.
// ============================================================

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../partials/audit.php';
require_login();
require_officer();

header('Content-Type: application/json');

$user = current_user();
$pdo  = db();

// ---- Ensure tables exist -----------------------------------
function tasks_ensure_tables(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            due_date DATE NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'open',
            priority VARCHAR(16) NOT NULL DEFAULT 'medium',
            pos_x INT NOT NULL DEFAULT 40,
            pos_y INT NOT NULL DEFAULT 40,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_task_assignees (
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            PRIMARY KEY (task_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_task_edges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_id INT NOT NULL,
            to_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_edge (from_id, to_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    // ensure priority column exists on pre-existing installs
    try {
        $pdo->exec("ALTER TABLE app_tasks ADD COLUMN priority VARCHAR(16) NOT NULL DEFAULT 'medium'");
    } catch (\PDOException $e) { /* column already exists */ }
}
tasks_ensure_tables($pdo);

function out($data): void { echo json_encode($data); exit; }
function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ---- Read input --------------------------------------------
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) { $in = $_POST; }
$action = $in['action'] ?? ($_GET['action'] ?? 'list');

// ---- Helpers -----------------------------------------------
function load_tasks(PDO $pdo): array {
    $tasks = $pdo->query(
        "SELECT id, title, description, due_date, status, priority, pos_x, pos_y, created_by
         FROM app_tasks ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // assignees keyed by task
    $rows = $pdo->query(
        "SELECT a.task_id, a.user_id, u.first_name, u.last_name, u.email
         FROM app_task_assignees a
         JOIN app_users u ON u.id = a.user_id
         ORDER BY u.first_name, u.last_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $byTask = [];
    foreach ($rows as $r) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if ($name === '') { $name = $r['email']; }
        $byTask[(int)$r['task_id']][] = [
            'user_id' => (int)$r['user_id'],
            'name'    => $name,
            'email'   => $r['email'],
        ];
    }
    foreach ($tasks as &$t) {
        $t['id']         = (int)$t['id'];
        $t['pos_x']      = (int)$t['pos_x'];
        $t['pos_y']      = (int)$t['pos_y'];
        $t['created_by'] = $t['created_by'] !== null ? (int)$t['created_by'] : null;
        $t['assignees']  = $byTask[$t['id']] ?? [];
    }
    unset($t);
    return $tasks;
}
function load_edges(PDO $pdo): array {
    return $pdo->query("SELECT id, from_id, to_id FROM app_task_edges ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

/** Tasks visible on an officer's board: ones they created or are assigned to. */
function tasks_for_officer(array $all, int $uid): array {
    return array_values(array_filter($all, function($t) use ($uid) {
        foreach (($t['assignees'] ?? []) as $a) {
            if ((int)($a['user_id'] ?? 0) === $uid) { return true; }
        }
        return false;
    }));
}

function officer_list(PDO $pdo): array {
    // Officers = users whose role column contains 'officer' (also include admins).
    $rows = $pdo->query(
        "SELECT id, first_name, last_name, email, role FROM app_users ORDER BY first_name, last_name"
    )->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $roles = strtolower((string)$r['role']);
        if (strpos($roles, 'officer') === false && strpos($roles, 'admin') === false) { continue; }
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if ($name === '') { $name = $r['email']; }
        $out[] = ['user_id' => (int)$r['id'], 'name' => $name, 'email' => $r['email']];
    }
    return $out;
}

// ---- Dispatch ----------------------------------------------
try {
    switch ($action) {
        case 'list':
            $all_tasks = load_tasks($pdo);
            out(['ok' => true, 'tasks' => $all_tasks, 'allTasks' => $all_tasks, 'edges' => load_edges($pdo), 'officers' => officer_list($pdo)]);
            break;

        case 'create': {
            $title = trim((string)($in['title'] ?? ''));
            if ($title === '') { fail('Title is required.'); }
            $desc = trim((string)($in['description'] ?? ''));
            $due  = trim((string)($in['due_date'] ?? ''));
            $due  = ($due === '') ? null : $due;
            $status = trim((string)($in['status'] ?? 'open'));
            $priority = strtolower(trim((string)($in['priority'] ?? 'medium')));
            if (!in_array($priority, ['low','medium','high'], true)) { $priority = 'medium'; }
            $px = (int)($in['pos_x'] ?? 40);
            $py = (int)($in['pos_y'] ?? 40);
            $stmt = $pdo->prepare(
                "INSERT INTO app_tasks (title, description, due_date, status, priority, pos_x, pos_y, created_by)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([$title, $desc, $due, $status, $priority, $px, $py, (int)$user['id']]);
            $id = (int)$pdo->lastInsertId();
            // Auto-assign the creator so the task appears on their board immediately.
            $pdo->prepare('INSERT INTO app_task_assignees (task_id, user_id) VALUES (?, ?)')
                ->execute([$id, (int)$user['id']]);
            log_audit('create', 'task', 'Created task: ' . $title);
            out(['ok' => true, 'id' => $id, 'tasks' => tasks_for_officer(load_tasks($pdo), (int)$user['id']), 'allTasks' => load_tasks($pdo)]);
            break;
        }

        case 'update': {
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) { fail('Invalid task id.'); }
            $title = trim((string)($in['title'] ?? ''));
            if ($title === '') { fail('Title is required.'); }
            $desc = trim((string)($in['description'] ?? ''));
            $due  = trim((string)($in['due_date'] ?? ''));
            $due  = ($due === '') ? null : $due;
            $status = trim((string)($in['status'] ?? 'open'));
            $priority = strtolower(trim((string)($in['priority'] ?? 'medium')));
            if (!in_array($priority, ['low','medium','high'], true)) { $priority = 'medium'; }
            $stmt = $pdo->prepare(
                "UPDATE app_tasks SET title=?, description=?, due_date=?, status=?, priority=? WHERE id=?"
            );
            $stmt->execute([$title, $desc, $due, $status, $priority, $id]);
            log_audit('update', 'task', 'Updated task: ' . $title . ' (#' . $id . ')');
            out(['ok' => true, 'tasks' => tasks_for_officer(load_tasks($pdo), (int)$user['id']), 'allTasks' => load_tasks($pdo)]);
            break;
        }

        case 'move': {
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) { fail('Invalid task id.'); }
            $px = (int)($in['pos_x'] ?? 0);
            $py = (int)($in['pos_y'] ?? 0);
            $stmt = $pdo->prepare("UPDATE app_tasks SET pos_x=?, pos_y=? WHERE id=?");
            $stmt->execute([$px, $py, $id]);
            out(['ok' => true]);
            break;
        }
        case 'add_edge': {
            $from = (int)($in['from_id'] ?? 0);
            $to   = (int)($in['to_id'] ?? 0);
            if ($from <= 0 || $to <= 0) { fail('Invalid edge endpoints.'); }
            if ($from === $to) { fail('Cannot link a task to itself.'); }
            $chk = $pdo->prepare("SELECT COUNT(*) FROM app_tasks WHERE id IN (?, ?)");
            $chk->execute([$from, $to]);
            if ((int)$chk->fetchColumn() < 2) { fail('Both tasks must exist.'); }
            $stmt = $pdo->prepare("INSERT IGNORE INTO app_task_edges (from_id, to_id) VALUES (?, ?)");
            $stmt->execute([$from, $to]);
            out(['ok' => true, 'edges' => load_edges($pdo)]);
            break;
        }

        case 'remove_edge': {
            $eid = (int)($in['id'] ?? 0);
            if ($eid > 0) {
                $stmt = $pdo->prepare("DELETE FROM app_task_edges WHERE id=?");
                $stmt->execute([$eid]);
            } else {
                $from = (int)($in['from_id'] ?? 0);
                $to   = (int)($in['to_id'] ?? 0);
                if ($from <= 0 || $to <= 0) { fail('Invalid edge.'); }
                $stmt = $pdo->prepare("DELETE FROM app_task_edges WHERE from_id=? AND to_id=?");
                $stmt->execute([$from, $to]);
            }
            out(['ok' => true, 'edges' => load_edges($pdo)]);
            break;
        }

        case 'delete': {
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) { fail('Invalid task id.'); }
            $row = $pdo->prepare("SELECT title FROM app_tasks WHERE id=?");
            $row->execute([$id]);
            $title = (string)($row->fetchColumn() ?: ('#' . $id));
            $pdo->prepare("DELETE FROM app_task_assignees WHERE task_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM app_tasks WHERE id=?")->execute([$id]);
            log_audit('delete', 'task', 'Deleted task: ' . $title . ' (#' . $id . ')');
            out(['ok' => true, 'tasks' => tasks_for_officer(load_tasks($pdo), (int)$user['id']), 'allTasks' => load_tasks($pdo)]);
            break;
        }

        case 'assign': {
            $id  = (int)($in['id'] ?? 0);
            $uid = (int)($in['user_id'] ?? 0);
            if ($id <= 0 || $uid <= 0) { fail('Invalid task or user.'); }
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO app_task_assignees (task_id, user_id) VALUES (?,?)"
            );
            $stmt->execute([$id, $uid]);
            out(['ok' => true, 'tasks' => tasks_for_officer(load_tasks($pdo), (int)$user['id']), 'allTasks' => load_tasks($pdo)]);
            break;
        }

        case 'unassign': {
            $id  = (int)($in['id'] ?? 0);
            $uid = (int)($in['user_id'] ?? 0);
            if ($id <= 0 || $uid <= 0) { fail('Invalid task or user.'); }
            $stmt = $pdo->prepare(
                "DELETE FROM app_task_assignees WHERE task_id=? AND user_id=?"
            );
            $stmt->execute([$id, $uid]);
            out(['ok' => true, 'tasks' => tasks_for_officer(load_tasks($pdo), (int)$user['id']), 'allTasks' => load_tasks($pdo)]);
            break;
        }

        default:
            fail('Unknown action.');
    }
} catch (Throwable $e) {
    fail('Server error.', 500);
}
