// Session guards — the client-side counterpart of the old auth.php.
// Real enforcement is Postgres RLS; these just route users to the
// right page and hand pages their profile/roles context.
import { supabase } from './supabase.js';

export async function requireLogin() {
  const { data: { session } } = await supabase.auth.getSession();
  if (!session) {
    location.replace('/app/login');
    return new Promise(() => {}); // halt caller while redirecting
  }
  recordLogin(session);
  joinPresence(session);
  const [profileRes, rolesRes, pagesRes] = await Promise.all([
    supabase.from('profiles').select('*').eq('id', session.user.id).single(),
    supabase.from('user_roles').select('role').eq('user_id', session.user.id),
    supabase.from('page_settings').select('path, enabled'),
  ]);
  const roles = (rolesRes.data || []).map(r => r.role);
  const pages = Object.fromEntries((pagesRes.data || []).map(p => [p.path, p.enabled]));
  // disabled pages are admin-only until re-enabled (Admin > Pages).
  // paths are stored extensionless; normalize so old .html bookmarks
  // can't slip past
  const canonPath = location.pathname.replace(/\.html$/, '');
  if (pages[canonPath] === false && !roles.includes('admin')) {
    location.replace('/app/');
    return new Promise(() => {});
  }
  return { session, user: session.user, profile: profileRes.data, roles, pages };
}

// Every signed-in page joins the shared presence channel, so "online"
// means the portal is open somewhere. Deliberately no opt-out.
let presenceCh = null;
function joinPresence(session) {
  if (presenceCh) return;
  try {
    presenceCh = supabase.channel('online-members', {
      config: { presence: { key: session.user.id } },
    });
    // listeners must attach before subscribe(); pages consume the
    // roster via window.__vsaOnline + the 'vsa:presence' event
    window.__vsaOnline = new Set();
    presenceCh.on('presence', { event: 'sync' }, () => {
      window.__vsaOnline = new Set(Object.keys(presenceCh.presenceState()));
      window.dispatchEvent(new CustomEvent('vsa:presence'));
    });
    presenceCh.subscribe((s) => {
      if (s === 'SUBSCRIBED') presenceCh.track({ at: new Date().toISOString() });
    });
  } catch (_) {}
}

function deviceLabel() {
  const ua = navigator.userAgent;
  const browser = /Edg\//.test(ua) ? 'Edge' : /Firefox\//.test(ua) ? 'Firefox'
    : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : 'Browser';
  const os = /Windows/.test(ua) ? 'Windows' : /Mac OS/.test(ua) ? 'macOS'
    : /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iOS' : /Linux/.test(ua) ? 'Linux' : '';
  return os ? `${browser} on ${os}` : browser;
}

// Powers Settings → Security. Runs once per actual sign-in (keyed on
// last_sign_in_at), never blocks page load, and failures stay silent —
// login history is a nicety, not a gate.
function recordLogin(session) {
  try {
    const seenKey = 'vsa-login-seen';
    const last = session.user.last_sign_in_at || '';
    if (localStorage.getItem(seenKey) === last) return;
    let dev = localStorage.getItem('vsa-device-id');
    if (!dev) { dev = crypto.randomUUID(); localStorage.setItem('vsa-device-id', dev); }
    (async () => {
      let geo = {};
      try {
        const g = await (await fetch('https://ipwho.is/')).json();
        if (g && g.success !== false) geo = g;
      } catch (_) {}
      // device upsert first (idempotent), activity insert second, and
      // mark seen only after both land — an interrupted or failed
      // attempt retries in full on the next page load (worst case: a
      // duplicate activity row if two tabs race, which is harmless)
      const { error: devErr } = await supabase.from('trusted_devices').upsert({
        user_id: session.user.id, device_id: dev, label: deviceLabel(), last_seen: new Date().toISOString(),
      }, { onConflict: 'user_id,device_id' });
      const { error } = await supabase.from('login_activity').insert({
        user_id: session.user.id, device_id: dev, user_agent: navigator.userAgent,
        ip: geo.ip || null, city: geo.city || null, region: geo.region || null, country: geo.country_code || geo.country || null,
      });
      if (!error && !devErr) localStorage.setItem(seenKey, last);
    })();
  } catch (_) {}
}

export function isOfficer(roles) {
  return roles.includes('officer') || roles.includes('admin');
}

// Officer pages that roles below officer may also open, and which roles.
// One map, read by both the page guard and the sidebar, so a link can
// never show up without access behind it — or access exist with no link.
// The matching database grant lives in the migration of the same name;
// this only decides what gets rendered and routed.
export const OFFICER_PAGE_ROLES = {
  '/app/officer/notes': ['intern'],
};

export function canOpenOfficerPage(href, roles) {
  if (isOfficer(roles)) return true;
  return (OFFICER_PAGE_ROLES[href] || []).some((r) => roles.includes(r));
}

export async function requireOfficer() {
  const ctx = await requireLogin();
  if (!isOfficer(ctx.roles)) {
    location.replace('/app/');
    return new Promise(() => {});
  }
  return ctx;
}

// For an officer page that some lower role can reach too. Defaults to the
// current path, so a page just calls requireOfficerPage() and the map above
// decides. Falls back to officer-only for any path not in the map.
export async function requireOfficerPage(href) {
  const path = href || location.pathname.replace(/\.html$/, '');
  const ctx = await requireLogin();
  if (!canOpenOfficerPage(path, ctx.roles)) {
    location.replace('/app/');
    return new Promise(() => {});
  }
  return ctx;
}

export async function requireAdmin() {
  const ctx = await requireLogin();
  if (!ctx.roles.includes('admin')) {
    location.replace('/app/');
    return new Promise(() => {});
  }
  return ctx;
}

export async function logout() {
  await supabase.auth.signOut();
  location.replace('/app/login');
}
