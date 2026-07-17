<?php
// =====================================================================
// UMKC VSA - Officer File Explorer
// Windows-style nested folders with back/forward navigation and rich
// text documents stored in MySQL.
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/../../auth.php';
require_login();
require_officer();

$user = current_user();
$panel = isset($_GET['panel']);
$notice = '';
$error = '';
$userEmail = (string)($user['email'] ?? $user['user_email'] ?? 'unknown');

function fx_csrf(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['fx_csrf'])) $_SESSION['fx_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['fx_csrf'];
}
function fx_verify_csrf(): void {
    if (!hash_equals(fx_csrf(), (string)($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
}
function fx_name(string $value, int $max = 180): string {
    $value = trim((string)preg_replace('/\s+/u', ' ', $value));
    $value = str_replace(['/', '\\', "\0"], '', $value);
    return mb_substr($value, 0, $max);
}

function fx_icon(string $name, string $class = ''): string {
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    $icons = [
        'arrow-left' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M15.25 4.75 8 12l7.25 7.25" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'arrow-right' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M8.75 4.75 16 12l-7.25 7.25" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'refresh' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M19 11a7 7 0 1 0 1.6 4.46" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M19 4.5v5h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'home' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4.75v-5.75h-4.5V21H5a1 1 0 0 1-1-1z" fill="currentColor" opacity=".18"/><path d="M4 10.5 12 4l8 6.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 9.75V20a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V9.75" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.75 21v-5.75h4.5V21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'folder' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 8.25a2 2 0 0 1 2-2H10l1.6 1.65c.38.39.9.6 1.44.6H18.5a2 2 0 0 1 2 2v1.05H3.5z" fill="currentColor" opacity=".18"/><path d="M3.5 9.4a2 2 0 0 1 2-2H10l1.6 1.52c.38.36.88.56 1.4.56H18.5a2 2 0 0 1 2 2v4.95a2.25 2.25 0 0 1-2.25 2.25H5.75A2.25 2.25 0 0 1 3.5 16.43z" fill="currentColor" opacity=".1"/><path d="M3.5 9.4a2 2 0 0 1 2-2H10l1.6 1.52c.38.36.88.56 1.4.56H18.5a2 2 0 0 1 2 2v4.95a2.25 2.25 0 0 1-2.25 2.25H5.75A2.25 2.25 0 0 1 3.5 16.43z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M3.5 11.2H20.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
        'folder-open' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 9.2a2 2 0 0 1 2-2H10l1.6 1.55c.38.37.89.58 1.42.58H18.5a2 2 0 0 1 1.96 1.62l.02.1" fill="currentColor" opacity=".16"/><path d="M5.2 10.85h15.15a1.2 1.2 0 0 1 1.16 1.52l-1.44 5.1a2.1 2.1 0 0 1-2.02 1.53H5.85a2.1 2.1 0 0 1-2.03-2.62l.93-3.35a2.85 2.85 0 0 1 2.75-2.18z" fill="currentColor" opacity=".1"/><path d="M3.5 9.2a2 2 0 0 1 2-2H10l1.6 1.55c.38.37.89.58 1.42.58H18.5a2 2 0 0 1 1.96 1.62" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.2 10.85h15.15a1.2 1.2 0 0 1 1.16 1.52l-1.44 5.1a2.1 2.1 0 0 1-2.02 1.53H5.85a2.1 2.1 0 0 1-2.03-2.62l.93-3.35a2.85 2.85 0 0 1 2.75-2.18z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
        'document' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.75h7.9L19 7.85V19.5A1.75 1.75 0 0 1 17.25 21H7A1.75 1.75 0 0 1 5.25 19.25V5.5A1.75 1.75 0 0 1 7 3.75z" fill="currentColor" opacity=".12"/><path d="M14.9 3.75V7.2a1 1 0 0 0 1 1H19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 3.75h7.9L19 7.85V19.5A1.75 1.75 0 0 1 17.25 21H7A1.75 1.75 0 0 1 5.25 19.25V5.5A1.75 1.75 0 0 1 7 3.75z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8.5 11h7M8.5 14.25h7M8.5 17.5h4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'plus-folder' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 9.4a2 2 0 0 1 2-2H10l1.6 1.52c.38.36.88.56 1.4.56H18.5a2 2 0 0 1 2 2v4.95a2.25 2.25 0 0 1-2.25 2.25H5.75A2.25 2.25 0 0 1 3.5 16.43z" fill="currentColor" opacity=".1"/><path d="M3.5 9.4a2 2 0 0 1 2-2H10l1.6 1.52c.38.36.88.56 1.4.56H18.5a2 2 0 0 1 2 2v4.95a2.25 2.25 0 0 1-2.25 2.25H5.75A2.25 2.25 0 0 1 3.5 16.43z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 12.2v4.3M9.85 14.35h4.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'plus-document' => '<svg'.$classAttr.' viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.75h7.9L19 7.85V19.5A1.75 1.75 0 0 1 17.25 21H7A1.75 1.75 0 0 1 5.25 19.25V5.5A1.75 1.75 0 0 1 7 3.75z" fill="currentColor" opacity=".1"/><path d="M14.9 3.75V7.2a1 1 0 0 0 1 1H19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 3.75h7.9L19 7.85V19.5A1.75 1.75 0 0 1 17.25 21H7A1.75 1.75 0 0 1 5.25 19.25V5.5A1.75 1.75 0 0 1 7 3.75z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 11.6v5M9.5 14.1h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    ];
    return $icons[$name] ?? '';
}
function fx_redirect(array $params = []): never {
    if (isset($_GET['panel'])) $params['panel'] = 1;
    header('Location: ' . basename(__FILE__) . ($params ? '?' . http_build_query($params) : ''));
    exit;
}
function fx_clean_html(string $html): string {
    $html = mb_substr($html, 0, 750000);
    $allowed = ['p','br','div','h1','h2','h3','blockquote','pre','strong','b','em','i','u','s','ul','ol','li','a','span','hr','table','thead','tbody','tr','th','td'];
    $old = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8" ?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $root = $dom->getElementById('root');
    if (!$root) return '';
    $walk = function(DOMNode $node) use (&$walk, $allowed): void {
        for ($child = $node->firstChild; $child;) {
            $next = $child->nextSibling;
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }
                $attrs = $tag === 'a' ? ['href','title','target','rel'] : [];
                for ($i = $child->attributes->length - 1; $i >= 0; $i--) {
                    $a = $child->attributes->item($i);
                    if ($a && !in_array(strtolower($a->name), $attrs, true)) $child->removeAttributeNode($a);
                }
                if ($tag === 'a') {
                    $href = trim($child->getAttribute('href'));
                    if ($href !== '' && !preg_match('~^(https?://|mailto:|tel:|/)~i', $href)) $child->removeAttribute('href');
                    if ($child->getAttribute('target') === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
                    else { $child->removeAttribute('target'); $child->removeAttribute('rel'); }
                }
                $walk($child);
            } elseif (!($child instanceof DOMText)) {
                $node->removeChild($child);
            }
            $child = $next;
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $child) $out .= $dom->saveHTML($child);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    return trim($out);
}
function fx_column_exists(string $table, string $column): bool {
    $stmt = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}
function fx_ensure_tables(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS app_document_folders (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED NULL,
        name VARCHAR(120) NOT NULL,
        created_by VARCHAR(190) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_folder_parent (parent_id),
        CONSTRAINT fk_folder_parent FOREIGN KEY (parent_id) REFERENCES app_document_folders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!fx_column_exists('app_document_folders', 'parent_id')) {
        db()->exec("ALTER TABLE app_document_folders ADD COLUMN parent_id INT UNSIGNED NULL AFTER id");
        db()->exec("ALTER TABLE app_document_folders ADD KEY idx_folder_parent (parent_id)");
    }
    // Remove the old global unique index if it exists; nested folders may reuse names in different parents.
    try { db()->exec("ALTER TABLE app_document_folders DROP INDEX uq_document_folder_name"); } catch (Throwable $e) {}

    db()->exec("CREATE TABLE IF NOT EXISTS app_documents (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        folder_id INT UNSIGNED NULL,
        title VARCHAR(180) NOT NULL,
        content_html MEDIUMTEXT NOT NULL,
        created_by VARCHAR(190) NOT NULL,
        updated_by VARCHAR(190) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_documents_folder_updated (folder_id, updated_at),
        CONSTRAINT fk_documents_folder FOREIGN KEY (folder_id) REFERENCES app_document_folders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function fx_folder_exists(?int $id): bool {
    if ($id === null || $id === 0) return true;
    $s = db()->prepare('SELECT COUNT(*) FROM app_document_folders WHERE id = ?');
    $s->execute([$id]);
    return (bool)$s->fetchColumn();
}
function fx_is_descendant(int $candidateParent, int $folderId): bool {
    $seen = [];
    $current = $candidateParent;
    while ($current > 0 && !isset($seen[$current])) {
        if ($current === $folderId) return true;
        $seen[$current] = true;
        $s = db()->prepare('SELECT parent_id FROM app_document_folders WHERE id = ?');
        $s->execute([$current]);
        $next = $s->fetchColumn();
        $current = $next === false || $next === null ? 0 : (int)$next;
    }
    return false;
}
function fx_breadcrumbs(?int $folderId): array {
    $crumbs = [];
    $seen = [];
    $current = $folderId ?? 0;
    while ($current > 0 && !isset($seen[$current])) {
        $seen[$current] = true;
        $s = db()->prepare('SELECT id, parent_id, name FROM app_document_folders WHERE id = ?');
        $s->execute([$current]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) break;
        array_unshift($crumbs, $row);
        $current = $row['parent_id'] === null ? 0 : (int)$row['parent_id'];
    }
    return $crumbs;
}

fx_ensure_tables();
$csrf = fx_csrf();
$currentFolderId = isset($_GET['folder']) && (int)$_GET['folder'] > 0 ? (int)$_GET['folder'] : null;
if (!fx_folder_exists($currentFolderId)) $currentFolderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        fx_verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        $returnFolder = (int)($_POST['return_folder'] ?? 0);
        $returnParams = $returnFolder > 0 ? ['folder' => $returnFolder] : [];

        if ($action === 'create_folder') {
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $name = fx_name((string)($_POST['name'] ?? ''), 120);
            if ($name === '') throw new RuntimeException('Folder name is required.');
            if (!fx_folder_exists($parentId ?: null)) throw new RuntimeException('The current folder no longer exists.');
            $dup = db()->prepare('SELECT COUNT(*) FROM app_document_folders WHERE parent_id <=> ? AND name = ?');
            $dup->execute([$parentId ?: null, $name]);
            if ($dup->fetchColumn()) throw new RuntimeException('A folder with that name already exists here.');
            $s = db()->prepare('INSERT INTO app_document_folders (parent_id, name, created_by) VALUES (?, ?, ?)');
            $s->execute([$parentId ?: null, $name, $userEmail]);
            fx_redirect(($parentId ? ['folder' => $parentId] : []) + ['notice' => 'folder-created']);
        }
        if ($action === 'rename_folder') {
            $id = (int)($_POST['folder_id'] ?? 0);
            $name = fx_name((string)($_POST['name'] ?? ''), 120);
            if ($id < 1 || $name === '') throw new RuntimeException('A valid folder name is required.');
            $s = db()->prepare('UPDATE app_document_folders SET name = ? WHERE id = ?');
            $s->execute([$name, $id]);
            fx_redirect($returnParams + ['notice' => 'folder-renamed']);
        }
        if ($action === 'delete_folder') {
            $id = (int)($_POST['folder_id'] ?? 0);
            if ($id < 1) throw new RuntimeException('Invalid folder.');
            $s = db()->prepare('DELETE FROM app_document_folders WHERE id = ?');
            $s->execute([$id]);
            fx_redirect($returnParams + ['notice' => 'folder-deleted']);
        }
        if ($action === 'move_folder') {
            $id = (int)($_POST['folder_id'] ?? 0);
            $newParent = (int)($_POST['new_parent_id'] ?? 0);
            if ($id < 1 || !fx_folder_exists($newParent ?: null)) throw new RuntimeException('Invalid folder destination.');
            if ($newParent === $id || ($newParent > 0 && fx_is_descendant($newParent, $id))) throw new RuntimeException('A folder cannot be moved inside itself.');
            $s = db()->prepare('UPDATE app_document_folders SET parent_id = ? WHERE id = ?');
            $s->execute([$newParent ?: null, $id]);
            fx_redirect($returnParams + ['notice' => 'folder-moved']);
        }
        if ($action === 'create_document') {
            $folderId = (int)($_POST['folder_id'] ?? 0);
            $title = fx_name((string)($_POST['title'] ?? 'Untitled document')) ?: 'Untitled document';
            if ($folderId < 1 || !fx_folder_exists($folderId)) throw new RuntimeException('Open a folder before creating a document.');
            $s = db()->prepare('INSERT INTO app_documents (folder_id,title,content_html,created_by,updated_by) VALUES (?,?,?,?,?)');
            $s->execute([$folderId, $title, '<p><br></p>', $userEmail, $userEmail]);
            fx_redirect(['folder' => $folderId, 'doc' => (int)db()->lastInsertId(), 'notice' => 'document-created']);
        }
        if ($action === 'save_document') {
            $id = (int)($_POST['document_id'] ?? 0);
            $folderId = (int)($_POST['folder_id'] ?? 0);
            $title = fx_name((string)($_POST['title'] ?? ''));
            if ($id < 1 || $folderId < 1 || $title === '') throw new RuntimeException('Document title is required.');
            $s = db()->prepare('UPDATE app_documents SET title=?, content_html=?, updated_by=? WHERE id=? AND folder_id=?');
            $s->execute([$title, fx_clean_html((string)($_POST['content_html'] ?? '')), $userEmail, $id, $folderId]);
            fx_redirect(['folder' => $folderId, 'doc' => $id, 'notice' => 'document-saved']);
        }
        if ($action === 'rename_document') {
            $id = (int)($_POST['document_id'] ?? 0);
            $title = fx_name((string)($_POST['title'] ?? ''));
            if ($id < 1 || $title === '') throw new RuntimeException('Document title is required.');
            $s = db()->prepare('UPDATE app_documents SET title=?, updated_by=? WHERE id=?');
            $s->execute([$title, $userEmail, $id]);
            fx_redirect($returnParams + ['notice' => 'document-renamed']);
        }
        if ($action === 'move_document') {
            $id = (int)($_POST['document_id'] ?? 0);
            $newFolder = (int)($_POST['new_folder_id'] ?? 0);
            if ($id < 1 || $newFolder < 1 || !fx_folder_exists($newFolder)) throw new RuntimeException('Choose a valid destination folder.');
            $s = db()->prepare('UPDATE app_documents SET folder_id=?, updated_by=? WHERE id=?');
            $s->execute([$newFolder, $userEmail, $id]);
            fx_redirect($returnParams + ['notice' => 'document-moved']);
        }
        if ($action === 'delete_document') {
            $id = (int)($_POST['document_id'] ?? 0);
            $s = db()->prepare('DELETE FROM app_documents WHERE id=?');
            $s->execute([$id]);
            fx_redirect($returnParams + ['notice' => 'document-deleted']);
        }
        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'The database could not complete that action.' : $e->getMessage();
    }
}

$noticeMap = [
 'folder-created'=>'Folder created.','folder-renamed'=>'Folder renamed.','folder-deleted'=>'Folder and everything inside it deleted.',
 'folder-moved'=>'Folder moved.','document-created'=>'Document created.','document-saved'=>'Document saved.',
 'document-renamed'=>'Document renamed.','document-moved'=>'Document moved.','document-deleted'=>'Document deleted.'
];
if (isset($_GET['notice'])) $notice = $noticeMap[(string)$_GET['notice']] ?? '';

$folderSql = $currentFolderId === null
    ? 'SELECT id,parent_id,name,updated_at FROM app_document_folders WHERE parent_id IS NULL ORDER BY name'
    : 'SELECT id,parent_id,name,updated_at FROM app_document_folders WHERE parent_id = ? ORDER BY name';
$folderStmt = db()->prepare($folderSql);
$folderStmt->execute($currentFolderId === null ? [] : [$currentFolderId]);
$childFolders = $folderStmt->fetchAll(PDO::FETCH_ASSOC);

$documents = [];
if ($currentFolderId !== null) {
    $s = db()->prepare('SELECT id,folder_id,title,updated_at,updated_by FROM app_documents WHERE folder_id=? ORDER BY title');
    $s->execute([$currentFolderId]);
    $documents = $s->fetchAll(PDO::FETCH_ASSOC);
}
$document = null;
$docId = (int)($_GET['doc'] ?? 0);
if ($docId > 0 && $currentFolderId !== null) {
    $s = db()->prepare('SELECT * FROM app_documents WHERE id=? AND folder_id=?');
    $s->execute([$docId, $currentFolderId]);
    $document = $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
$breadcrumbs = fx_breadcrumbs($currentFolderId);
$allFolders = db()->query('SELECT id,parent_id,name FROM app_document_folders ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$currentName = $breadcrumbs ? (string)end($breadcrumbs)['name'] : 'Officer Files';
$queryBase = $panel ? '&panel=1' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($currentName) ?> | UMKC VSA</title>
<script>(function(){try{if(localStorage.getItem('vsa-theme')==='dark')document.documentElement.classList.add('dark-mode')}catch(e){}})();</script>
<style>
:root{--navy:#16314d;--red:#c8202f;--bg:#eef2f7;--card:#fff;--text:#1f2937;--muted:#64748b;--line:#dce3eb;--hover:#edf4fb;--soft:#f7f9fc;--shadow:0 10px 30px rgba(17,35,55,.08)}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}.wrap{max-width:1500px;margin:24px auto;padding:0 18px}.wrap.notes-wrap{max-width:1500px}.explorer{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;min-height:720px}.topbar{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid var(--line);background:var(--soft)}.navbtn,.toolbtn{border:1px solid transparent;background:transparent;color:var(--text);border-radius:7px;cursor:pointer;height:36px;padding:0 11px;font-size:14px;display:inline-flex;align-items:center;justify-content:center;gap:8px}.navbtn svg,.toolbtn svg,.address svg,.side-link svg,.icon svg,.empty svg{display:block}.topbar-icon,.side-icon,.btn-icon{width:18px;height:18px;flex:0 0 auto}.chev-icon{width:14px;height:14px;display:block}.address-root,.address-current,.side-link{display:flex;align-items:center;gap:8px}.address-current{color:var(--text)}.navbtn:hover,.toolbtn:hover{background:var(--hover);border-color:var(--line)}.navbtn:disabled{opacity:.35;cursor:default}.address{display:flex;align-items:center;gap:4px;flex:1;min-width:0;height:38px;padding:0 10px;background:var(--card);border:1px solid var(--line);border-radius:8px;overflow:auto;white-space:nowrap}.address a{color:var(--text);text-decoration:none;padding:5px 7px;border-radius:5px}.address a:hover{background:var(--hover)}.chev{color:var(--muted)}.search{width:min(280px,25vw);height:38px;border:1px solid var(--line);border-radius:8px;padding:0 12px;background:var(--card);color:var(--text)}.commandbar{display:flex;align-items:center;gap:6px;padding:8px 12px;border-bottom:1px solid var(--line);flex-wrap:wrap}.commandbar .primary{background:var(--navy);color:#fff;border-color:var(--navy)}.separator{width:1px;height:24px;background:var(--line);margin:0 4px}.status{margin-left:auto;color:var(--muted);font-size:13px}.notice,.error{margin:12px 14px 0;padding:10px 13px;border-radius:8px;font-size:14px}.notice{background:#e7f7ec;color:#176b36}.error{background:#fdeaea;color:#a51d2d}.workspace{display:grid;grid-template-columns:220px 1fr;min-height:630px}.fx-sidebar{border-right:1px solid var(--line);padding:14px 10px;background:var(--soft)}.side-title{padding:7px 10px;font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.08em;text-transform:uppercase}.side-link{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:7px;color:var(--text);text-decoration:none;font-size:14px}.side-link:hover,.side-link.active{background:var(--hover)}.main{min-width:0;padding:12px}.file-list{width:100%;border-collapse:collapse;table-layout:fixed}.file-list th{height:34px;text-align:left;padding:0 10px;color:var(--muted);font-size:12px;font-weight:600;border-bottom:1px solid var(--line)}.file-list td{height:48px;padding:0 10px;border-bottom:1px solid #edf0f4;font-size:14px}.file-row{cursor:default}.file-row:hover{background:var(--hover)}.file-row.selected{background:#dfeeff}.namecell{display:flex;align-items:center;gap:10px;min-width:0}.namecell a{display:flex;align-items:center;gap:10px;color:var(--text);text-decoration:none;min-width:0;flex:1}.filename{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.icon{width:27px;height:27px;display:grid;place-items:center;flex:0 0 auto}.folder-icon{color:#d79b1f}.folder-open-icon{color:#c8202f}.doc-icon{color:#274f86}.empty-icon{width:58px;height:58px;color:#c8202f;margin:0 auto 10px}.btn-primary-icon{color:currentColor}.btn-secondary-icon{color:var(--navy)} html.dark-mode .btn-secondary-icon{color:#c6d8eb}.row-actions{display:flex;justify-content:flex-end;gap:4px}.mini{border:0;background:transparent;border-radius:6px;padding:6px 8px;cursor:pointer;color:var(--muted)}.mini:hover{background:var(--card);color:var(--text)}.empty{padding:70px 20px;text-align:center;color:var(--muted)}.empty .big{font-size:54px;margin-bottom:10px}.editor-wrap{padding:0;background:#e8ebef;min-height:630px}.editor-head{display:flex;align-items:center;gap:10px;padding:9px 12px;background:var(--card);border-bottom:1px solid var(--line)}.editor-title{flex:1;border:0;background:transparent;font-size:18px;font-weight:600;color:var(--text);outline:none}.editor-tools{display:flex;gap:4px;flex-wrap:wrap;padding:8px 12px;background:var(--card);border-bottom:1px solid var(--line)}.editor-tools button,.editor-tools select{height:32px;border:1px solid var(--line);background:var(--card);color:var(--text);border-radius:5px;padding:0 9px;cursor:pointer}.paper{width:min(850px,calc(100% - 40px));min-height:980px;margin:24px auto;background:#fff;color:#171717;box-shadow:0 4px 18px rgba(0,0,0,.15);padding:75px 80px;outline:none;line-height:1.6}.editor-foot{position:sticky;bottom:0;display:flex;justify-content:space-between;padding:7px 12px;background:var(--card);border-top:1px solid var(--line);color:var(--muted);font-size:12px}.modal{display:none;position:fixed;inset:0;background:rgba(8,18,30,.45);z-index:1000;align-items:center;justify-content:center;padding:20px}.modal.open{display:flex}.dialog{width:min(460px,100%);background:var(--card);border-radius:12px;box-shadow:0 25px 70px rgba(0,0,0,.25);padding:20px}.dialog h2{margin:0 0 15px;font-size:20px}.dialog label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin:12px 0 6px}.dialog input,.dialog select{width:100%;height:42px;border:1px solid var(--line);border-radius:7px;padding:0 11px;background:var(--card);color:var(--text)}.dialog-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}.danger{background:#b4232f!important;color:#fff!important}.hidden{display:none!important}body.panel-mode{background:transparent}body.panel-mode .wrap{max-width:none;margin:0;padding:12px}
html.dark-mode{--bg:#0f1a26;--card:#16222f;--text:#e6edf3;--muted:#9fb0c0;--line:#2a3a49;--hover:#203344;--soft:#131f2b;--shadow:none}html.dark-mode .file-list td{border-bottom-color:#223241}html.dark-mode .file-row.selected{background:#27445f}html.dark-mode .editor-wrap{background:#0e1720}html.dark-mode .paper{background:var(--card);color:var(--text)}
@media(max-width:800px){.workspace{grid-template-columns:1fr}.fx-sidebar{display:none}.search{width:150px}.file-list th:nth-child(2),.file-list td:nth-child(2){display:none}.paper{width:calc(100% - 16px);margin:8px auto;padding:40px 28px}.status{display:none}}
</style>
</head>
<body<?= $panel ? ' class="panel-mode"' : '' ?>>
<?php $officerActive='documents'; include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-chrome.php'; ?>
<div class="wrap notes-wrap"><section class="explorer">
  <div class="topbar">
    <button class="navbtn" type="button" onclick="history.back()" title="Back"><?= fx_icon('arrow-left','topbar-icon') ?></button>
    <button class="navbtn" type="button" onclick="history.forward()" title="Forward"><?= fx_icon('arrow-right','topbar-icon') ?></button>
    <button class="navbtn" type="button" onclick="location.reload()" title="Refresh"><?= fx_icon('refresh','topbar-icon') ?></button>
    <div class="address">
      <a class="address-root" href="<?= e(basename(__FILE__).($panel?'?panel=1':'')) ?>"><?= fx_icon('home','topbar-icon') ?><span>Officer Files</span></a>
      <?php foreach($breadcrumbs as $crumb): ?><span class="chev"><?= fx_icon('arrow-right','chev-icon') ?></span><a href="?folder=<?= (int)$crumb['id'] ?><?= $queryBase ?>"><?= e($crumb['name']) ?></a><?php endforeach; ?>
      <?php if($document): ?><span class="chev"><?= fx_icon('arrow-right','chev-icon') ?></span><span><?= e($document['title']) ?></span><?php endif; ?>
    </div>
    <?php if(!$document): ?><input class="search" id="fileSearch" type="search" placeholder="Search this folder"><?php endif; ?>
  </div>

  <?php if(!$document): ?>
  <div class="commandbar">
    <button class="toolbtn primary" type="button" data-open="newFolder"><?= fx_icon('plus-folder','btn-icon btn-primary-icon') ?><span>New folder</span></button>
    <?php if($currentFolderId !== null): ?><button class="toolbtn" type="button" data-open="newDocument"><?= fx_icon('plus-document','btn-icon btn-secondary-icon') ?><span>New document</span></button><?php endif; ?>
    <span class="separator"></span>
    <button class="toolbtn" type="button" onclick="location.reload()"><?= fx_icon('refresh','btn-icon btn-secondary-icon') ?><span>Refresh</span></button>
    <span class="status"><?= count($childFolders) ?> folder<?= count($childFolders)===1?'':'s' ?><?= $currentFolderId!==null ? ' · '.count($documents).' document'.(count($documents)===1?'':'s') : '' ?></span>
  </div>
  <?php endif; ?>

  <?php if($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
  <?php if($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

  <div class="workspace">
    <aside class="fx-sidebar">
      <div class="side-title">Navigation</div>
      <a class="side-link<?= $currentFolderId===null?' active':'' ?>" href="<?= e(basename(__FILE__).($panel?'?panel=1':'')) ?>"><?= fx_icon('home','side-icon') ?><span>Officer Files</span></a>
      <?php foreach(array_slice($breadcrumbs,0,-1) as $crumb): ?><a class="side-link" href="?folder=<?= (int)$crumb['id'] ?><?= $queryBase ?>"><?= fx_icon('folder','side-icon folder-icon') ?><span><?= e($crumb['name']) ?></span></a><?php endforeach; ?>
      <?php if($currentFolderId!==null): ?><a class="side-link active" href="?folder=<?= $currentFolderId ?><?= $queryBase ?>"><?= fx_icon('folder-open','side-icon folder-open-icon') ?><span><?= e($currentName) ?></span></a><?php endif; ?>
    </aside>

    <main class="main<?= $document?' editor-wrap':'' ?>">
    <?php if($document): ?>
      <form id="editorForm" method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_document">
        <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>"><input type="hidden" name="folder_id" value="<?= (int)$document['folder_id'] ?>">
        <input type="hidden" name="content_html" id="contentHtml">
        <div class="editor-head"><a class="navbtn" href="?folder=<?= (int)$document['folder_id'] ?><?= $queryBase ?>"><?= fx_icon('arrow-left','topbar-icon') ?><span>Files</span></a><input class="editor-title" name="title" value="<?= e($document['title']) ?>" required><button class="toolbtn primary" type="submit">Save</button></div>
        <div class="editor-tools">
          <select onchange="formatBlock(this.value);this.selectedIndex=0"><option value="">Style</option><option value="p">Normal</option><option value="h1">Heading 1</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option></select>
          <button type="button" onclick="cmd('bold')"><b>B</b></button><button type="button" onclick="cmd('italic')"><i>I</i></button><button type="button" onclick="cmd('underline')"><u>U</u></button>
          <button type="button" onclick="cmd('insertUnorderedList')">• List</button><button type="button" onclick="cmd('insertOrderedList')">1. List</button>
          <button type="button" onclick="cmd('justifyLeft')">Left</button><button type="button" onclick="cmd('justifyCenter')">Center</button><button type="button" onclick="cmd('justifyRight')">Right</button>
          <button type="button" onclick="addLink()">Link</button><button type="button" onclick="cmd('undo')">Undo</button><button type="button" onclick="cmd('redo')">Redo</button>
        </div>
        <div class="paper" id="editor" contenteditable="true"><?= $document['content_html'] ?></div>
        <div class="editor-foot"><span>Last edited by <?= e($document['updated_by']) ?></span><span id="wordCount">0 words</span></div>
      </form>
    <?php else: ?>
      <?php if(!$childFolders && !$documents): ?><div class="empty"><div class="big"><?= fx_icon('folder-open','empty-icon') ?></div><strong>This folder is empty</strong><p>Create a folder or document to get started.</p></div><?php else: ?>
      <table class="file-list"><thead><tr><th style="width:55%">Name</th><th>Last modified</th><th style="width:145px;text-align:right">Actions</th></tr></thead><tbody>
        <?php foreach($childFolders as $f): ?>
        <tr class="file-row" data-name="<?= e(mb_strtolower($f['name'])) ?>"><td><div class="namecell"><a href="?folder=<?= (int)$f['id'] ?><?= $queryBase ?>"><span class="icon folder-icon"><?= fx_icon('folder','folder-icon') ?></span><span class="filename"><?= e($f['name']) ?></span></a></div></td><td><?= e($f['updated_at']) ?></td><td><div class="row-actions"><button class="mini" type="button" data-rename-folder='<?= e(json_encode(['id'=>(int)$f['id'],'name'=>$f['name']], JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Rename</button><button class="mini" type="button" data-delete-folder="<?= (int)$f['id'] ?>">Delete</button></div></td></tr>
        <?php endforeach; ?>
        <?php foreach($documents as $d): ?>
        <tr class="file-row" data-name="<?= e(mb_strtolower($d['title'])) ?>"><td><div class="namecell"><a href="?folder=<?= (int)$d['folder_id'] ?>&doc=<?= (int)$d['id'] ?><?= $queryBase ?>"><span class="icon doc-icon"><?= fx_icon('document','doc-icon') ?></span><span class="filename"><?= e($d['title']) ?></span></a></div></td><td><?= e($d['updated_at']) ?></td><td><div class="row-actions"><button class="mini" type="button" data-rename-doc='<?= e(json_encode(['id'=>(int)$d['id'],'title'=>$d['title']], JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Rename</button><button class="mini" type="button" data-delete-doc="<?= (int)$d['id'] ?>">Delete</button></div></td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
    <?php endif; ?>
    </main>
  </div>
</section></div>

<div class="modal" id="newFolder"><form class="dialog" method="post"><h2>New folder</h2><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_folder"><input type="hidden" name="parent_id" value="<?= (int)($currentFolderId??0) ?>"><label>Folder name</label><input name="name" maxlength="120" autofocus required><div class="dialog-actions"><button type="button" class="toolbtn" data-close>Cancel</button><button class="toolbtn primary" type="submit">Create</button></div></form></div>
<div class="modal" id="newDocument"><form class="dialog" method="post"><h2>New document</h2><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_document"><input type="hidden" name="folder_id" value="<?= (int)($currentFolderId??0) ?>"><label>Document name</label><input name="title" maxlength="180" value="Untitled document" required><div class="dialog-actions"><button type="button" class="toolbtn" data-close>Cancel</button><button class="toolbtn primary" type="submit">Create</button></div></form></div>
<div class="modal" id="renameFolder"><form class="dialog" method="post"><h2>Rename folder</h2><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="rename_folder"><input type="hidden" name="return_folder" value="<?= (int)($currentFolderId??0) ?>"><input type="hidden" name="folder_id" id="rfId"><label>New name</label><input name="name" id="rfName" required><div class="dialog-actions"><button type="button" class="toolbtn" data-close>Cancel</button><button class="toolbtn primary" type="submit">Rename</button></div></form></div>
<div class="modal" id="renameDoc"><form class="dialog" method="post"><h2>Rename document</h2><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="rename_document"><input type="hidden" name="return_folder" value="<?= (int)($currentFolderId??0) ?>"><input type="hidden" name="document_id" id="rdId"><label>New name</label><input name="title" id="rdName" required><div class="dialog-actions"><button type="button" class="toolbtn" data-close>Cancel</button><button class="toolbtn primary" type="submit">Rename</button></div></form></div>
<div class="modal" id="deleteFolder"><form class="dialog" method="post"><h2>Delete folder?</h2><p class="muted">This permanently deletes the folder, every subfolder, and all documents inside it.</p><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_folder"><input type="hidden" name="return_folder" value="<?= (int)($currentFolderId??0) ?>"><input type="hidden" name="folder_id" id="dfId"><div class="dialog-actions"><button type="button" class="toolbtn" data-close>Cancel</button><button class="toolbtn danger" type="submit">Delete</button></div></form></div>
<div class="modal" id="deleteDoc"><form class="dialog" method="post"><h2>Delete document?</h2><p class="muted">This action cannot be undone.</p><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="return_folder" value="<?= (int)($currentFolderId??0) ?>"><input type="hidden" name="document_id" id="ddId"><div class="dialog-actions"><button type="button" class="toolbtn" data-close>Cancel</button><button class="toolbtn danger" type="submit">Delete</button></div></form></div>

<script>
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
function openModal(id){const m=document.getElementById(id);if(m){m.classList.add('open');setTimeout(()=>m.querySelector('input:not([type=hidden])')?.focus(),20)}}
function closeModal(m){m.classList.remove('open')}
$$('[data-open]').forEach(b=>b.onclick=()=>openModal(b.dataset.open));
$$('[data-close]').forEach(b=>b.onclick=()=>closeModal(b.closest('.modal')));
$$('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m)}));
$$('[data-rename-folder]').forEach(b=>b.onclick=()=>{const d=JSON.parse(b.dataset.renameFolder);$('#rfId').value=d.id;$('#rfName').value=d.name;openModal('renameFolder')});
$$('[data-rename-doc]').forEach(b=>b.onclick=()=>{const d=JSON.parse(b.dataset.renameDoc);$('#rdId').value=d.id;$('#rdName').value=d.title;openModal('renameDoc')});
$$('[data-delete-folder]').forEach(b=>b.onclick=()=>{$('#dfId').value=b.dataset.deleteFolder;openModal('deleteFolder')});
$$('[data-delete-doc]').forEach(b=>b.onclick=()=>{$('#ddId').value=b.dataset.deleteDoc;openModal('deleteDoc')});
const search=$('#fileSearch');if(search)search.addEventListener('input',()=>{const q=search.value.trim().toLowerCase();$$('.file-row').forEach(r=>r.hidden=q&&!r.dataset.name.includes(q))});
function cmd(c,v=null){document.execCommand(c,false,v);$('#editor')?.focus()}
function formatBlock(v){if(v)cmd('formatBlock','<'+v+'>')}
function addLink(){const u=prompt('Enter the link URL:');if(u)cmd('createLink',u)}
const editor=$('#editor'), form=$('#editorForm'), wc=$('#wordCount');
function words(){if(!editor||!wc)return;const t=editor.innerText.trim();wc.textContent=(t?t.split(/\s+/).length:0)+' words'}
if(editor){editor.addEventListener('input',words);words()}
if(form)form.addEventListener('submit',()=>{$('#contentHtml').value=editor.innerHTML});
</script>
</body></html>
