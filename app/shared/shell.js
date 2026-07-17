// Renders the app chrome (sidebar + topbar) for signed-in pages and
// wires up theme toggle + logout. Replaces the legacy PHP partials
// (sidebar.php, topbar.php, theme-toggle.php).
import { isOfficer, logout } from './guard.js';

// Custom line-icon set (stroke inherits link color; gold dot accents)
const SVG = (inner) =>
  `<svg class="nav-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${inner}</svg>`;

const ICONS = {
  dashboard: SVG('<path d="M4 11.2 12 4l8 7.2"/><path d="M6 9.8V20h12V9.8"/><path d="M10 20v-5h4v5"/>'),
  calendar: SVG('<rect x="3.8" y="5" width="16.4" height="15" rx="2"/><path d="M3.8 9.6h16.4M8.2 3v3.4M15.8 3v3.4"/><circle cx="12" cy="14.5" r="1.6" fill="currentColor" stroke="none"/>'),
  trophy: SVG('<path d="M7.5 4.5h9v5a4.5 4.5 0 0 1-9 0v-5Z"/><path d="M7.5 6H4.6v1.2A3.2 3.2 0 0 0 7.8 10.4M16.5 6h2.9v1.2a3.2 3.2 0 0 1-3.2 3.2"/><path d="M12 14v3.6M8.6 20h6.8"/>'),
  gift: SVG('<rect x="4.2" y="11" width="15.6" height="9" rx="1.4"/><rect x="3.2" y="7.4" width="17.6" height="3.6" rx="1"/><path d="M12 7.4V20"/><path d="M12 7.4c-1.2-3.8-5.8-3.4-4.9-.6.5 1.4 3 .9 4.9.6ZM12 7.4c1.2-3.8 5.8-3.4 4.9-.6-.5 1.4-3 .9-4.9.6Z"/>'),
  users: SVG('<circle cx="9" cy="8.6" r="3.4"/><path d="M3.4 19.4c0-3 2.5-5 5.6-5s5.6 2 5.6 5"/><circle cx="16.9" cy="9.4" r="2.6"/><path d="M16.4 14.6c2.6.4 4.2 2.1 4.2 4.6"/>'),
  user: SVG('<circle cx="12" cy="8.2" r="3.9"/><path d="M5.2 20c0-3.6 3-6 6.8-6s6.8 2.4 6.8 6"/>'),
  pin: SVG('<path d="M12 21S5.6 15.4 5.6 10.4a6.4 6.4 0 0 1 12.8 0C18.4 15.4 12 21 12 21Z"/><circle cx="12" cy="10.4" r="2.4"/>'),
  board: SVG('<rect x="3.6" y="3.6" width="7.2" height="7.2" rx="1.5"/><rect x="13.2" y="3.6" width="7.2" height="7.2" rx="1.5"/><rect x="3.6" y="13.2" width="7.2" height="7.2" rx="1.5"/><rect x="13.2" y="13.2" width="7.2" height="7.2" rx="1.5" stroke-dasharray="2.5 2.5"/>'),
  box: SVG('<path d="M3.8 8 12 3.6 20.2 8v8L12 20.4 3.8 16V8Z"/><path d="M3.8 8 12 12.4 20.2 8"/><path d="M12 12.4v8"/>'),
  note: SVG('<rect x="5" y="3.6" width="14" height="16.8" rx="2"/><path d="M9 8.4h6M9 12h6M9 15.6h3.6"/>'),
  scope: SVG('<circle cx="11" cy="11" r="6.2"/><path d="M15.6 15.6 20.4 20.4"/><path d="M8.6 11h4.8M11 8.6v4.8"/>'),
  shield: SVG('<path d="M12 3.6 18.8 6v5.2c0 4.4-2.9 7.3-6.8 9.2-3.9-1.9-6.8-4.8-6.8-9.2V6L12 3.6Z"/><path d="m9.2 11.6 2 2 3.6-3.8"/>'),
};

const MEMBER_LINKS = [
  { href: '/app/',                  label: 'Dashboard',    icon: 'dashboard' },
  { href: '/app/calendar.html',     label: 'Calendar',     icon: 'calendar' },
  { href: '/app/achievements.html', label: 'Achievements', icon: 'trophy' },
  { href: '/app/rewards.html',      label: 'Rewards',      icon: 'gift' },
  { href: '/app/members.html',      label: 'Members',      icon: 'users' },
  { href: '/app/profile.html',      label: 'My Profile',   icon: 'user' },
];

const OFFICER_LINKS = [
  { href: '/app/officer/events.html',    label: 'Events',    icon: 'pin' },
  { href: '/app/officer/tasks.html',     label: 'Tasks',     icon: 'board' },
  { href: '/app/officer/inventory.html', label: 'Inventory', icon: 'box' },
  { href: '/app/officer/notes.html',     label: 'Notes',     icon: 'note' },
  { href: '/app/officer/audit.html',     label: 'Audit Log', icon: 'scope' },
  { href: '/app/officer/roles.html',     label: 'Roles',     icon: 'shield' },
];

function navLink({ href, label, icon, soon }) {
  const active = location.pathname === href
    || (href === '/app/' && location.pathname === '/app/index.html');
  return `<a href="${href}" title="${label}" class="${active ? 'active' : ''}${soon ? ' disabled' : ''}"
    >${ICONS[icon] || ''} <span class="nav-lb">${label}${soon ? ' <small>(soon)</small>' : ''}</span></a>`;
}

// Count-up animation for stat numerals as pages inject them
function animateStats(root) {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  root.querySelectorAll('.stat .num').forEach(el => {
    if (el.dataset.counted) return;
    const target = parseInt(el.textContent.trim(), 10);
    if (!Number.isFinite(target) || String(target) !== el.textContent.trim() || target === 0) {
      el.dataset.counted = '1';
      return;
    }
    el.dataset.counted = '1';
    const t0 = performance.now(), dur = 750;
    const tick = (now) => {
      const p = Math.min(1, (now - t0) / dur);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });
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
          <span class="brand-name">UMKC VSA</span>
        </a>
        <nav>
          <div class="nav-label">Member</div>
          ${MEMBER_LINKS.map(navLink).join('')}
          ${officer ? `<div class="nav-label">Officer</div>${OFFICER_LINKS.map(navLink).join('')}` : ''}
        </nav>
        <div class="foot">Vietnamese Student Association<br>at UMKC</div>
        <button class="side-toggle" id="side-toggle" aria-label="Collapse sidebar" title="Collapse sidebar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 6 9 12l5.5 6"/></svg>
        </button>
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

  // collapsible sidebar (state persists; class applied pre-paint = no flash)
  const shellEl = document.querySelector('.shell');
  try {
    if (localStorage.getItem('vsa-sidebar') === 'collapsed') shellEl.classList.add('side-collapsed');
  } catch (e) {}
  document.getElementById('side-toggle').addEventListener('click', () => {
    const collapsed = shellEl.classList.toggle('side-collapsed');
    try { localStorage.setItem('vsa-sidebar', collapsed ? 'collapsed' : 'open'); } catch (e) {}
    document.getElementById('side-toggle').setAttribute('aria-label',
      collapsed ? 'Expand sidebar' : 'Collapse sidebar');
  });

  const content = document.getElementById('page-content');
  new MutationObserver(() => animateStats(content))
    .observe(content, { childList: true, subtree: true });
  return content;
}
