-- Audit log becomes admin-only (was officer-readable), matching its move
-- into the Admin section of the portal.

drop policy "audit_log: officers read" on public.audit_log;

create policy "audit_log: admins read"
  on public.audit_log for select to authenticated
  using (public.has_role(auth.uid(), 'admin'));
