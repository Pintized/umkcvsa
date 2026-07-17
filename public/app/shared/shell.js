// Renders the app chrome (sidebar + topbar) for signed-in pages and
// wires up theme toggle + logout. Replaces the legacy PHP partials
// (sidebar.php, topbar.php, theme-toggle.php).
import { isOfficer, logout } from './guard.js';

const MEMBER_LINKS = [
  { href: '/app/',                  label: 'Dashboard',    icon: '🏠' },
  { href: '/app/calendar.html',     label: 'Calendar',     icon: '📅' },
  { href: '/app/achievements.html', label: 'Achievements', icon: '🏆' },
  { href: '/app/rewards.html',      label: 'Rewards',      icon: '🎁' },
  { href: '/app/members.html',      label: 'Members',      icon: '👥' },
  { href: '/app/profile.html',      label: 'My Profile',   icon: '👤' },
];

const OFFICER_LINKS = [
  { href: '/app/officer/events.html',    label: 'Events',    icon: '📌' },
  { href: '/app/officer/tasks.html',     label: 'Tasks',     icon: '🗂️' },
  { href: '/app/officer/inventory.html', label: 'Inventory', icon: '📦' },
  { href: '/app/officer/notes.html',     label: 'Notes',     icon: '📝' },
  { href: '/app/officer/audit.html',     label: 'Audit Log', icon: '🔍' },
  { href: '/app/officer/roles.html',     label: 'Roles',     icon: '🛡️' },
];

function navLink({ href, label, icon, soon }) {
  const active = location.pathname === href
    || (href === '/app/' && location.pathname === '/app/index.html');
  return `<a href="${href}" class="${active ? 'active' : ''}${soon ? ' disabled' : ''}"
    >${icon} ${label}${soon ? ' <small>(soon)</small>' : ''}</a>`;
}

export function renderShell(ctx, pageTitle) {
  const officer = isOfficer(ctx.roles);
  const displayName = ctx.profile?.full_name || ctx.user.email;
  const avatar = ctx.profile?.avatar_path
    ? `https://wrlpsetbkeyoyamkopgf.supabase.co/storage/v1/object/public/avatars/${ctx.profile.avatar_path}`
    : '/assets/img/logo-128.png';

  document.body.insertAdjacentHTML('afterbegin', `
    <div class="shell">
      <aside class="sidebar">
        <a class="brand" href="/app/">
          <img src="/assets/img/logo-128.png" alt="UMKC VSA logo">
          <span>UMKC VSA</span>
        </a>
        <nav>
          <div class="nav-label">Member</div>
          ${MEMBER_LINKS.map(navLink).join('')}
          ${officer ? `<div class="nav-label">Officer</div>${OFFICER_LINKS.map(navLink).join('')}` : ''}
        </nav>
        <div class="foot">Vietnamese Student Association<br>at UMKC</div>
      </aside>
      <div class="main">
        <header class="topbar">
          <div class="page-title">${pageTitle}</div>
          <div class="actions">
            <button class="theme-toggle" id="theme-toggle" title="Toggle dark mode">🌓</button>
            <div class="userchip">
              <img src="${avatar}" alt="">
              <span class="name">${displayName}</span>
            </div>
            <button class="btn ghost" id="logout-btn">Log out</button>
          </div>
        </header>
        <main class="content" id="page-content"></main>
      </div>
    </div>
  `);

  document.getElementById('theme-toggle').addEventListener('click', () => {
    const dark = document.documentElement.classList.toggle('dark-mode');
    try { localStorage.setItem('vsa-theme', dark ? 'dark' : 'light'); } catch (e) {}
  });
  document.getElementById('logout-btn').addEventListener('click', logout);

  return document.getElementById('page-content');
}
