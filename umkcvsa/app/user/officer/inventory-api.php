<?php declare(strict_types=1);
// ============================================================
// UMKC VSA - Officer Inventory API
// JSON endpoint for inventory tracking (CRUD + quick adjust).
// Actions: list, create, update, delete, adjust.
// Any officer may create/edit/delete/adjust any item.
// All mutations are recorded via log_audit().
// ============================================================

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../partials/audit.php';
require_login();
require_officer();

header('Content-Type: application/json');

$user = current_user();
$pdo  = db();

// ---- Ensure table exists -----------------------------------
function inventory_ensure_table(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(128) NOT NULL DEFAULT '',
            quantity INT NOT NULL DEFAULT 0,
            unit VARCHAR(64) NOT NULL DEFAULT '',
            location VARCHAR(255) NOT NULL DEFAULT '',
            low_stock_threshold INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}
inventory_ensure_table($pdo);

function out($data): void { echo json_encode($data); exit; }
function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function load_inventory(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT id, name, category, quantity, unit, location, low_stock_threshold, notes, created_by, created_at, updated_at
         FROM app_inventory ORDER BY name ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    return array_map(function($r){
        $r['id']                  = (int)$r['id'];
        $r['quantity']            = (int)$r['quantity'];
        $r['low_stock_threshold'] = (int)$r['low_stock_threshold'];
        return $r;
    }, $rows);
}

// ---- Parse request -----------------------------------------
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) { $in = $_POST; }
$action = $in['action'] ?? ($_GET['action'] ?? 'list');

if ($action === 'list') {
    out(['ok' => true, 'items' => load_inventory($pdo)]);
}

if ($action === 'create') {
    $name = trim((string)($in['name'] ?? ''));
    $category = trim((string)($in['category'] ?? ''));
    $location = trim((string)($in['location'] ?? ''));
    if ($name === '' || $category === '' || $location === '') {
        fail('Name, category and location are required.');
    }
    $qty  = (int)($in['quantity'] ?? 0);
    $unit = trim((string)($in['unit'] ?? ''));
    $thr  = (int)($in['low_stock_threshold'] ?? 0);
    $notes = trim((string)($in['notes'] ?? ''));
    $stmt = $pdo->prepare(
        "INSERT INTO app_inventory (name, category, quantity, unit, location, low_stock_threshold, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $category, $qty, $unit, $location, $thr, $notes, (int)$user['id']]);
    $id = (int)$pdo->lastInsertId();
    log_audit('inventory.create', 'inventory#'.$id, 'Added "'.$name.'" (qty '.$qty.') in '.$location);
    out(['ok' => true, 'id' => $id, 'items' => load_inventory($pdo)]);
}

if ($action === 'update') {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) { fail('Missing id.'); }
    $name = trim((string)($in['name'] ?? ''));
    $category = trim((string)($in['category'] ?? ''));
    $location = trim((string)($in['location'] ?? ''));
    if ($name === '' || $category === '' || $location === '') {
        fail('Name, category and location are required.');
    }
    $qty  = (int)($in['quantity'] ?? 0);
    $unit = trim((string)($in['unit'] ?? ''));
    $thr  = (int)($in['low_stock_threshold'] ?? 0);
    $notes = trim((string)($in['notes'] ?? ''));
    $stmt = $pdo->prepare(
        "UPDATE app_inventory SET name=?, category=?, quantity=?, unit=?, location=?, low_stock_threshold=?, notes=? WHERE id=?"
    );
    $stmt->execute([$name, $category, $qty, $unit, $location, $thr, $notes, $id]);
    log_audit('inventory.update', 'inventory#'.$id, 'Updated "'.$name.'"');
    out(['ok' => true, 'items' => load_inventory($pdo)]);
}

if ($action === 'adjust') {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) { fail('Missing id.'); }
    $delta = (int)($in['delta'] ?? 0);
    $row = $pdo->prepare("SELECT name, quantity FROM app_inventory WHERE id=?");
    $row->execute([$id]);
    $item = $row->fetch(PDO::FETCH_ASSOC);
    if (!$item) { fail('Item not found.', 404); }
    $newQty = max(0, (int)$item['quantity'] + $delta);
    $stmt = $pdo->prepare("UPDATE app_inventory SET quantity=? WHERE id=?");
    $stmt->execute([$newQty, $id]);
    log_audit('inventory.adjust', 'inventory#'.$id, 'Qty '.$item['name'].': '.$item['quantity'].' -> '.$newQty);
    out(['ok' => true, 'items' => load_inventory($pdo)]);
}

if ($action === 'delete') {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) { fail('Missing id.'); }
    $row = $pdo->prepare("SELECT name FROM app_inventory WHERE id=?");
    $row->execute([$id]);
    $item = $row->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("DELETE FROM app_inventory WHERE id=?");
    $stmt->execute([$id]);
    log_audit('inventory.delete', 'inventory#'.$id, 'Removed "'.($item['name'] ?? ('#'.$id)).'"');
    out(['ok' => true, 'items' => load_inventory($pdo)]);
}

fail('Unknown action.');
