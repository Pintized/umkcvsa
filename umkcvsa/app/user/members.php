<?php
// ============================================================
// UMKC VSA - Members Page
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$user = require_login();

/*
  This page assumes you have a users table with columns like:

  id
  first_name
  last_name
  full_name
  email
  role
  email_verified
  created_at

  If your verification column has a different name, update the helper function below.
*/

// Try to find your PDO database connection.
// This makes the page more flexible depending on how db.php/auth.php is written.
$pdo = null;

if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $pdo = $pdo;
} elseif (function_exists('db')) {
    $possiblePdo = db();

    if ($possiblePdo instanceof PDO) {
        $pdo = $possiblePdo;
    }
} elseif (function_exists('get_db')) {
    $possiblePdo = get_db();

    if ($possiblePdo instanceof PDO) {
        $pdo = $possiblePdo;
    }
}

$allUsers = [];

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("
            SELECT *
            FROM users
            ORDER BY first_name ASC, last_name ASC, full_name ASC, created_at ASC
        ");

        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $allUsers = [];
    }
}

function display_name(array $member): string
{
    $first = trim((string)($member['first_name'] ?? ''));
    $last = trim((string)($member['last_name'] ?? ''));
    $full = trim((string)($member['full_name'] ?? ''));

    $name = trim($first . ' ' . $last);

    if ($name !== '') {
        return $name;
    }

    if ($full !== '') {
        return $full;
    }

    return 'Unnamed Member';
}

function is_executive_role(array $member): bool
{
    $role = strtolower(trim((string)($member['role'] ?? '')));

    $executiveRoles = [
        'admin',
        'officer',
        'intern',
        'president',
        'vice president',
        'vp',
        'treasurer',
        'secretary',
        'public relations',
        'pr',
        'historian',
        'event coordinator',
        'social chair',
        'family chair',
        'executive board',
        'eboard',
        'e-board'
    ];

    return in_array($role, $executiveRoles, true);
}

function is_verified(array $member): bool
{
    /*
      Adjust this if your database uses a different column name.

      Supported examples:
      email_verified = 1
      verified = 1
      is_verified = 1
      status = verified
    */

    if (isset($member['email_verified'])) {
        return (int)$member['email_verified'] === 1;
    }

    if (isset($member['verified'])) {
        return (int)$member['verified'] === 1;
    }

    if (isset($member['is_verified'])) {
        return (int)$member['is_verified'] === 1;
    }

    if (isset($member['status'])) {
        return strtolower((string)$member['status']) === 'verified';
    }

    return false;
}

function is_umkc_verified(array $member): bool
{
    $email = strtolower(trim((string)($member['email'] ?? '')));

    /*
      If you later add a column like umkc_verified, this will use it.
      Otherwise, it checks whether the verified user's email ends with @umkc.edu.
    */

    if (isset($member['umkc_verified'])) {
        return (int)$member['umkc_verified'] === 1;
    }

    return is_verified($member) && str_ends_with($email, '@umkc.edu');
}

$executiveBoard = [];
$regularMembers = [];

foreach ($allUsers as $member) {
    if (is_executive_role($member)) {
        $executiveBoard[] = $member;
    } else {
        $regularMembers[] = $member;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Members | UMKC VSA</title>
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
    html.dark-mode .member-card { background: #16222f; border-color: #28394a; }
    html.dark-mode .member-list { background: #16222f; }
    html.dark-mode .page-header h1,
    html.dark-mode h1, html.dark-mode h2, html.dark-mode h3 { color: var(--text); }
    html.dark-mode .member-name { color: var(--text); }

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

  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar-styles.php'; ?><?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar-styles.php'; ?>  /* =========================
     Members Content
  ========================= */

  .wrap {
    max-width: 1150px;
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

  .members-layout {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    align-items: start;
  }

  .member-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 18px 50px rgba(22, 49, 77, .12);
    overflow: hidden;
    animation: rise .6s ease both;
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

  .card-heading {
    background: var(--navy);
    color: var(--white);
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .card-heading h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.45rem;
    color: var(--white);
  }

  .count-pill {
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .35);
    color: var(--white);
    font-size: .8rem;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 999px;
    white-space: nowrap;
  }

  .member-list {
    max-height: 520px;
    overflow-y: auto;
    padding: 14px;
    background: var(--white);
  }

  .member-list::-webkit-scrollbar {
    width: 10px;
  }

  .member-list::-webkit-scrollbar-track {
    background: #eef3f8;
    border-radius: 999px;
  }

  .member-list::-webkit-scrollbar-thumb {
    background: rgba(22, 49, 77, .45);
    border-radius: 999px;
  }

  .member-list::-webkit-scrollbar-thumb:hover {
    background: rgba(22, 49, 77, .7);
  }

  .member-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 12px;
    border-bottom: 1px solid #e6edf4;
    transition: .2s ease;
  }

  .member-row:last-child {
    border-bottom: none;
  }

  .member-row:hover {
    background: #f7fafc;
    border-radius: 10px;
  }

  .member-info {
    min-width: 0;
    text-align: left;
  }

  .member-name {
    color: var(--navy);
    font-weight: 800;
    font-size: 1rem;
    line-height: 1.2;
  }

  .member-role {
    color: var(--muted);
    font-size: .86rem;
    font-weight: 700;
    margin-top: 3px;
    text-transform: capitalize;
  }

  .member-tags {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: wrap;
    flex-shrink: 0;
  }

  .tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .72rem;
    font-weight: 900;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: .045em;
    padding: 6px 8px;
    border-radius: 999px;
    white-space: nowrap;
  }

  .tag.verified {
    color: var(--white);
    background: var(--red);
  }

  .tag.umkc {
    color: var(--navy);
    background: #e3eef9;
    border: 1px solid #c7d9ea;
  }

  .empty-state {
    padding: 28px 24px;
    color: var(--muted);
    text-align: center;
    font-size: .95rem;
  }

  .error-note {
    background: #fff5f6;
    color: var(--red);
    border-left: 4px solid var(--red);
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-weight: 700;
  }

  /* =========================
     Responsive
  ========================= */

  @media (max-width: 850px) {
    .members-layout {
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

    .card-heading {
      padding: 16px 18px;
    }

    .member-row {
      align-items: flex-start;
      flex-direction: column;
      gap: 8px;
    }

    .member-tags {
      justify-content: flex-start;
    }
  }
  </style>
</head>

<body>

  

  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar Menu -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar.php'; ?>

  <!-- Top Bar -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar.php'; ?>

  <!-- Members Content -->
  <main class="wrap">
    <div class="page-header">
      <h1>Members</h1>
      <p>View current UMKC VSA officers, interns, and verified members.</p>
    </div>

    <?php if (!($pdo instanceof PDO)): ?>
      <div class="error-note">
        Database connection could not be found. Check that your auth.php or db.php exposes a PDO connection.
      </div>
    <?php endif; ?>

    <div class="members-layout">

    <!-- Members List -->
    <section class="member-card">
        <div class="card-heading">
        <h2>Members</h2>
        <span class="count-pill"><?= count($regularMembers) ?> total</span>
        </div>

        <?php if (!empty($regularMembers)): ?>
        <div class="member-list">
            <?php foreach ($regularMembers as $member): ?>
            <div class="member-row">
                <div class="member-info">
                <div class="member-name"><?= e(display_name($member)) ?></div>

                <?php if (!empty($member['role'])): ?>
                    <div class="member-role"><?= e((string)$member['role']) ?></div>
                <?php endif; ?>
                </div>

                <div class="member-tags">
                <?php if (is_verified($member)): ?>
                    <span class="tag verified">Verified</span>
                <?php endif; ?>

                <?php if (is_umkc_verified($member)): ?>
                    <span class="tag umkc">UMKC</span>
                <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            No regular members have been added yet.
        </div>
        <?php endif; ?>
    </section>

    <!-- Executive Board List -->
    <section class="member-card">
        <div class="card-heading">
        <h2>Executive Board</h2>
        <span class="count-pill"><?= count($executiveBoard) ?> total</span>
        </div>

        <?php if (!empty($executiveBoard)): ?>
        <div class="member-list">
            <?php foreach ($executiveBoard as $member): ?>
            <div class="member-row">
                <div class="member-info">
                <div class="member-name"><?= e(display_name($member)) ?></div>

                <?php if (!empty($member['role'])): ?>
                    <div class="member-role"><?= e((string)$member['role']) ?></div>
                <?php endif; ?>
                </div>

                <div class="member-tags">
                <?php if (is_verified($member)): ?>
                    <span class="tag verified">Verified</span>
                <?php endif; ?>

                <?php if (is_umkc_verified($member)): ?>
                    <span class="tag umkc">UMKC</span>
                <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            No officers or interns have been added yet.
        </div>
        <?php endif; ?>
    </section>

    </div>
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
  </script>

  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-script.php'; ?>
</body>
</html>