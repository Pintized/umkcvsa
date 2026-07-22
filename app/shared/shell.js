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
  coin: SVG('<circle cx="12" cy="12" r="8.2"/><path d="M12 7.4v9.2M14.8 9.4c-.5-1-1.5-1.6-2.8-1.6-1.6 0-2.8.9-2.8 2.1s1.2 1.9 2.8 2.1c1.6.2 2.8.9 2.8 2.1s-1.2 2.1-2.8 2.1c-1.3 0-2.3-.6-2.8-1.6"/>'),
  note: SVG('<rect x="5" y="3.6" width="14" height="16.8" rx="2"/><path d="M9 8.4h6M9 12h6M9 15.6h3.6"/>'),
  scope: SVG('<circle cx="11" cy="11" r="6.2"/><path d="M15.6 15.6 20.4 20.4"/><path d="M8.6 11h4.8M11 8.6v4.8"/>'),
  camera: SVG('<path d="M4 7.6h3l1.6-2.4h6.8L17 7.6h3a1.4 1.4 0 0 1 1.4 1.4v9a1.4 1.4 0 0 1-1.4 1.4H4A1.4 1.4 0 0 1 2.6 18V9A1.4 1.4 0 0 1 4 7.6Z"/><circle cx="12" cy="13.4" r="3.6"/>'),
  crown: SVG('<path d="M4 8.4 7.6 11 12 5.6 16.4 11 20 8.4l-1.6 9H5.6L4 8.4Z"/><path d="M5.6 20.4h12.8"/>'),
  megaphone: SVG('<path d="M3.6 10v4a1.4 1.4 0 0 0 1.4 1.4h2L18.4 20V4L7 8.6H5A1.4 1.4 0 0 0 3.6 10Z"/><path d="M8.4 15.8l.8 4.2h2.6l-.8-4.4"/>'),
  tag: SVG('<path d="M3.6 11.4V4.6a1 1 0 0 1 1-1h6.8a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.8l-5.8 5.8a2 2 0 0 1-2.8 0l-7.2-7.2a2 2 0 0 1-.6-1.4Z"/><circle cx="8.4" cy="8.4" r="1.5" fill="currentColor" stroke="none"/>'),
  shield: SVG('<path d="M12 3.6 18.8 6v5.2c0 4.4-2.9 7.3-6.8 9.2-3.9-1.9-6.8-4.8-6.8-9.2V6L12 3.6Z"/><path d="m9.2 11.6 2 2 3.6-3.8"/>'),
  gear: SVG('<circle cx="12" cy="12" r="3.2"/><path d="M12 2.8v2.6M12 18.6v2.6M21.2 12h-2.6M5.4 12H2.8M18.5 5.5l-1.8 1.8M7.3 16.7l-1.8 1.8M18.5 18.5l-1.8-1.8M7.3 7.3 5.5 5.5"/>'),
  mail: SVG('<rect x="3.2" y="5.4" width="17.6" height="13.2" rx="1.8"/><path d="m4.4 7 7.6 5.6L19.6 7"/>'),
};

const MEMBER_LINKS = [
  { href: '/app/',                  label: 'Dashboard',    icon: 'dashboard' },
  { href: '/app/calendar.html',     label: 'Calendar',     icon: 'calendar' },
  { href: '/app/achievements.html', label: 'Achievements', icon: 'trophy' },
  { href: '/app/rewards.html',      label: 'Rewards',      icon: 'gift' },
  { href: '/app/members.html',      label: 'Members',      icon: 'users' },
];

// personal configuration pages, separate from day-to-day functions
const SETTINGS_LINKS = [
  { href: '/app/profile.html',      label: 'My Profile',   icon: 'user' },
  { href: '/app/settings.html',     label: 'Settings',     icon: 'gear' },
];

// portal chrome translations (sidebar, sections, log out).
// Page content is translated progressively; chrome switches now.
const I18N = {
  vi: {
    Member: 'Thành viên', Officer: 'Ban cán sự', Admin: 'Quản trị', Settings: 'Cài đặt',
    Dashboard: 'Trang chính', Calendar: 'Lịch', Achievements: 'Thành tích', Rewards: 'Phần thưởng',
    Members: 'Thành viên', 'My Profile': 'Hồ sơ của tôi', Events: 'Sự kiện', Tasks: 'Nhiệm vụ',
    Inventory: 'Kho đồ', Finance: 'Tài chính', Notes: 'Ghi chú', 'Audit Log': 'Nhật ký hoạt động', Inbox: 'Hộp thư', 'Member Directory': 'Danh bạ thành viên', 'Engagement Settings': 'Cài đặt tương tác',
    Roles: 'Vai trò', 'Home Page': 'Trang chủ', 'E-Board': 'Ban chấp hành', Store: 'Cửa hàng',
    Gallery: 'Thư viện ảnh', 'Log out': 'Đăng xuất', 'Main site': 'Trang chính',
  },
  es: {
    Member: 'Miembro', Officer: 'Oficiales', Admin: 'Administración', Settings: 'Configuración',
    Dashboard: 'Panel', Calendar: 'Calendario', Achievements: 'Logros', Rewards: 'Recompensas',
    Members: 'Miembros', 'My Profile': 'Mi perfil', Events: 'Eventos', Tasks: 'Tareas',
    Inventory: 'Inventario', Finance: 'Finanzas', Notes: 'Notas', 'Audit Log': 'Registro de actividad', Inbox: 'Buzón', 'Member Directory': 'Directorio de miembros', 'Engagement Settings': 'Ajustes de participación',
    Roles: 'Roles', 'Home Page': 'Página de inicio', 'E-Board': 'Directiva', Store: 'Tienda',
    Gallery: 'Galería', 'Log out': 'Cerrar sesión', 'Main site': 'Sitio principal',
  },
  zh: {
    Member: '成员', Officer: '干部', Admin: '管理', Settings: '设置',
    Dashboard: '主页', Calendar: '日历', Achievements: '成就', Rewards: '奖励',
    Members: '成员', 'My Profile': '我的资料', Events: '活动', Tasks: '任务',
    Inventory: '库存', Finance: '财务', Notes: '笔记', 'Audit Log': '审计日志', Inbox: '收件箱', 'Member Directory': '成员名录', 'Engagement Settings': '互动设置',
    Roles: '角色', 'Home Page': '首页', 'E-Board': '执行委员会', Store: '商店',
    Gallery: '相册', 'Log out': '退出登录', 'Main site': '主网站',
  },
};
const tr = (lang, s) => (I18N[lang] && I18N[lang][s]) || s;

const OFFICER_LINKS = [
  { href: '/app/officer/events.html',    label: 'Events',    icon: 'pin' },
  { href: '/app/officer/tasks.html',     label: 'Tasks',     icon: 'board' },
  { href: '/app/officer/inventory.html', label: 'Inventory', icon: 'box' },
  { href: '/app/officer/finance.html',   label: 'Finance',   icon: 'coin' },
  { href: '/app/officer/notes.html',     label: 'Notes',     icon: 'note' },
  { href: '/app/officer/inbox.html',     label: 'Inbox',     icon: 'mail' },
  { href: '/app/officer/directory.html', label: 'Member Directory', icon: 'users' },
  { href: '/app/officer/engagement.html', label: 'Engagement Settings', icon: 'trophy' },
];

// site content managers — admins only
const ADMIN_LINKS = [
  { href: '/app/admin/home.html',      label: 'Home Page', icon: 'megaphone' },
  { href: '/app/admin/eboard.html',    label: 'E-Board',  icon: 'crown' },
  { href: '/app/admin/store.html',     label: 'Store',    icon: 'tag' },
  { href: '/app/officer/gallery.html', label: 'Gallery',  icon: 'camera' },
  { href: '/app/officer/roles.html',   label: 'Roles',    icon: 'shield' },
  { href: '/app/officer/audit.html',   label: 'Audit Log', icon: 'scope' },
];

function navLink({ href, label, icon, soon }, officerLink = false) {
  const active = location.pathname === href
    || (href === '/app/' && location.pathname === '/app/index.html');
  const cls = [active ? 'active' : '', soon ? 'disabled' : '', officerLink ? 'officer-link' : '']
    .filter(Boolean).join(' ');
  return `<a href="${href}" title="${label}" class="${cls}"
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
  const lang = ctx.profile?.language || 'en';
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
          <div class="nav-label">${tr(lang, 'Member')}</div>
          ${MEMBER_LINKS.map(l => navLink({ ...l, label: tr(lang, l.label) })).join('')}
          ${officer ? `<div class="nav-label">${tr(lang, 'Officer')}</div>${OFFICER_LINKS.map(l => navLink({ ...l, label: tr(lang, l.label) }, true)).join('')}` : ''}
          ${ctx.roles.includes('admin') ? `<div class="nav-label">${tr(lang, 'Admin')}</div>${ADMIN_LINKS.map(l => navLink({ ...l, label: tr(lang, l.label) }, true)).join('')}` : ''}
          <div class="nav-label">${tr(lang, 'Settings')}</div>
          ${SETTINGS_LINKS.map(l => navLink({ ...l, label: tr(lang, l.label) })).join('')}
        </nav>
        <div class="foot">Vietnamese Student Association<br>at UMKC</div>
      </aside>
      <button class="side-toggle" id="side-toggle" aria-label="Collapse sidebar" title="Collapse sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 6 9 12l5.5 6"/></svg>
      </button>
      <div class="main">
        <header class="topbar">
          <div class="page-title">${pageTitle}</div>
          <div class="actions">
            <a class="btn ghost" href="/" style="text-decoration:none">${tr(lang, 'Main site')}</a>
            <div class="userchip">
              <img src="${avatar}" alt="">
              <span class="name">${displayName}</span>
            </div>
            <button class="btn ghost" id="logout-btn">${tr(lang, 'Log out')}</button>
          </div>
        </header>
        <main class="content" id="page-content"></main>
      </div>
    </div>
  `);

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
