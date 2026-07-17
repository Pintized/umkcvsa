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
  const [profileRes, rolesRes] = await Promise.all([
    supabase.from('profiles').select('*').eq('id', session.user.id).single(),
    supabase.from('user_roles').select('role').eq('user_id', session.user.id),
  ]);
  const roles = (rolesRes.data || []).map(r => r.role);
  return { session, user: session.user, profile: profileRes.data, roles };
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
