// Session guards — the client-side counterpart of the old auth.php.
// Real enforcement is Postgres RLS; these just route users to the
// right page and hand pages their profile/roles context.
import { supabase } from './supabase.js';

export async function requireLogin() {
  const { data: { session } } = await supabase.auth.getSession();
  if (!session) {
    location.replace('/app/login.html');
    return new Promise(() => {}); // halt caller while redirecting
  }
  recordLogin(session);
  const [profileRes, rolesRes] = await Promise.all([
    supabase.from('profiles').select('*').eq('id', session.user.id).single(),
    supabase.from('user_roles').select('role').eq('user_id', session.user.id),
  ]);
  const roles = (rolesRes.data || []).map(r => r.role);
  return { session, user: session.user, profile: profileRes.data, roles };
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
      const { error } = await supabase.from('login_activity').insert({
        user_id: session.user.id, device_id: dev, user_agent: navigator.userAgent,
        ip: geo.ip || null, city: geo.city || null, region: geo.region || null, country: geo.country_code || geo.country || null,
      });
      // mark seen only once the row is in — an interrupted or failed
      // attempt retries on the next page load (worst case: a duplicate
      // row if two tabs race, which is harmless)
      if (!error) localStorage.setItem(seenKey, last);
      await supabase.from('trusted_devices').upsert({
        user_id: session.user.id, device_id: dev, label: deviceLabel(), last_seen: new Date().toISOString(),
      }, { onConflict: 'user_id,device_id' });
    })();
  } catch (_) {}
}

export function isOfficer(roles) {
  return roles.includes('officer') || roles.includes('admin');
}

export async function requireOfficer() {
  const ctx = await requireLogin();
  if (!isOfficer(ctx.roles)) {
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
  location.replace('/app/login.html');
}
