<?php
// Shared sidebar partial. Single source of truth for the left nav.
// Included via: include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar.php';
// Requires \$user and has_role() to be available (provided by auth.php / require_login()).
if (!function_exists('vsa_sidebar_helpers')) {
  function vsa_sidebar_helpers() {
    static $cur = null;
    if ($cur === null) {
      $cur = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '';
    }
    return $cur;
  }
  function nav_active($href) {
    $cur  = vsa_sidebar_helpers();
    $path = parse_url($href, PHP_URL_PATH) ?: '';
    return ($path !== '' && $path === $cur) ? ' active' : '';
  }
  // Map each section to the path prefixes / files it owns.
  function sec_open($section) {
    $cur = vsa_sidebar_helpers();
    $map = array(
      'main'    => array('/app/profile.php','/app/user/family.php','/app/user/members.php','/app/user/events.php','/app/user/calendar.php','/app/user/rewards.php','/app/user/achievements.php'),
      'officer' => array('/app/user/officer/modify-events.php','/app/user/officer/notes.php','/app/user/officer/permissions.php','/app/user/officer/tasks.php','/app/user/officer/inventory.php','/app/user/officer/audit-log.php'),
      'profile' => array('/app/user/settings.php','/app/user/language.php','/app/user/notifications.php','/app/user/support.php'),
    );
    if (!isset($map[$section])) return '';
    return in_array($cur, $map[$section], true) ? ' open' : '';
  }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="/assets/logo.png" alt="UMKC VSA logo">
      <span>UMKC VSA</span>
    </div>

    <a class="back-site-link" href="/">
      &lt;- umkcvsa.org
    </a>

    <div class="menu-section">
      <button class="section-toggle<?= sec_open('main') ?>" id="mainMenuToggle" type="button">
        <span>Menu</span>
        <span class="arrow">⌄</span>
      </button>

      <div class="section-content<?= sec_open('main') ?>" id="mainMenuContent">
        <a class="menu-link<?= nav_active('/app/profile.php') ?>" href="/app/profile.php">Dashboard</a>
        <a class="menu-link<?= nav_active('/app/user/family.php') ?>" href="/app/user/family.php">Family</a>
        <a class="menu-link<?= nav_active('/app/user/members.php') ?>" href="/app/user/members.php">Members</a>
        <a class="menu-link<?= nav_active('/app/user/events.php') ?>" href="/app/user/events.php">Events</a>
        <a class="menu-link<?= nav_active('/app/user/calendar.php') ?>" href="/app/user/calendar.php">Calendar</a>
        <a class="menu-link<?= nav_active('/app/user/rewards.php') ?>" href="/app/user/rewards.php">Rewards</a>
        <a class="menu-link<?= nav_active('/app/user/achievements.php') ?>" href="/app/user/achievements.php">Achievements</a>
      </div>
    </div>

      <?php if (has_role($user, 'officer') || has_role($user, 'admin')): ?>
      <div class="menu-section">
        <button class="section-toggle<?= sec_open('officer') ?>" id="officerToggle" type="button">
          <span>Officer</span>
          <span class="arrow">▾</span>
        </button>

        <div class="section-content<?= sec_open('officer') ?>" id="officerSubmenu">
          <a class="menu-link<?= nav_active('/app/user/officer/modify-events.php') ?>" href="/app/user/officer/modify-events.php">Event Management</a>
          <a class="menu-link<?= nav_active('/app/user/officer/notes.php') ?>" href="/app/user/officer/notes.php">Notes</a>
          <a class="menu-link<?= nav_active('/app/user/officer/permissions.php') ?>" href="/app/user/officer/permissions.php">Permissions</a>
          <a class="menu-link<?= nav_active('/app/user/officer/tasks.php') ?>" href="/app/user/officer/tasks.php">Task Management</a>
          <a class="menu-link<?= nav_active('/app/user/officer/inventory.php') ?>" href="/app/user/officer/inventory.php">Inventory</a>
          <a class="menu-link<?= nav_active('/app/user/officer/audit-log.php') ?>" href="/app/user/officer/audit-log.php">Audit Log</a>
        </div>
      </div>
      <?php endif; ?>
    <div class="menu-section">
      <button class="section-toggle<?= sec_open('profile') ?>" id="profileToggle" type="button">
        <span>My Profile</span>
        <span class="arrow">⌄</span>
      </button>

      <div class="section-content<?= sec_open('profile') ?>" id="profileSubmenu">
        <a class="menu-link<?= nav_active('/app/user/settings.php') ?>" href="/app/user/settings.php">Settings</a>
        <a class="menu-link<?= nav_active('/app/user/language.php') ?>" href="/app/user/language.php">Language</a>
        <a class="menu-link<?= nav_active('/app/user/notifications.php') ?>" href="/app/user/notifications.php">Notifications</a>
        <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-toggle.php'; ?>
        <a class="menu-link<?= nav_active('/app/user/support.php') ?>" href="/app/user/support.php">Support</a>
      </div>
    </div>
  </aside>
