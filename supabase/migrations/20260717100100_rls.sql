-- ============================================================
-- UMKC VSA - Row Level Security
-- Replaces legacy/php-app auth.php: require_login() -> `to
-- authenticated`, require_officer() -> is_officer(auth.uid()).
-- ============================================================

alter table public.profiles           enable row level security;
alter table public.user_roles         enable row level security;
alter table public.events             enable row level security;
alter table public.rsvps              enable row level security;
alter table public.tasks              enable row level security;
alter table public.task_assignees     enable row level security;
alter table public.task_edges         enable row level security;
alter table public.note_folders       enable row level security;
alter table public.notes              enable row level security;
alter table public.document_folders   enable row level security;
alter table public.documents          enable row level security;
alter table public.inventory          enable row level security;
alter table public.rewards            enable row level security;
alter table public.achievements       enable row level security;
alter table public.achievement_awards enable row level security;
alter table public.orders             enable row level security;
alter table public.order_items        enable row level security;
alter table public.cart_items         enable row level security;
alter table public.audit_log          enable row level security;

-- ---------- profiles ----------
-- Members directory is visible to all signed-in users.
create policy "profiles: authenticated read"
  on public.profiles for select to authenticated
  using (true);

create policy "profiles: update own"
  on public.profiles for update to authenticated
  using (id = auth.uid())
  with check (id = auth.uid());

create policy "profiles: officers update any"
  on public.profiles for update to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

-- ---------- user_roles ----------
create policy "user_roles: read own, officers read all"
  on public.user_roles for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

create policy "user_roles: admins insert"
  on public.user_roles for insert to authenticated
  with check (public.has_role(auth.uid(), 'admin'));

create policy "user_roles: admins delete"
  on public.user_roles for delete to authenticated
  using (public.has_role(auth.uid(), 'admin'));

-- ---------- events ----------
-- Public site may list events without a login.
create policy "events: public read"
  on public.events for select
  using (true);

create policy "events: officers write"
  on public.events for insert to authenticated
  with check (public.is_officer(auth.uid()));

create policy "events: officers update"
  on public.events for update to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "events: officers delete"
  on public.events for delete to authenticated
  using (public.is_officer(auth.uid()));

-- ---------- rsvps ----------
create policy "rsvps: read own, officers read all"
  on public.rsvps for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

create policy "rsvps: insert own"
  on public.rsvps for insert to authenticated
  with check (user_id = auth.uid());

create policy "rsvps: update own"
  on public.rsvps for update to authenticated
  using (user_id = auth.uid())
  with check (user_id = auth.uid());

create policy "rsvps: delete own"
  on public.rsvps for delete to authenticated
  using (user_id = auth.uid());

-- ---------- officer-only workspaces ----------
create policy "tasks: officers all"
  on public.tasks for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "task_assignees: officers all"
  on public.task_assignees for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "task_edges: officers all"
  on public.task_edges for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "note_folders: officers all"
  on public.note_folders for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "notes: officers all"
  on public.notes for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "document_folders: officers all"
  on public.document_folders for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "documents: officers all"
  on public.documents for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "inventory: officers all"
  on public.inventory for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

-- ---------- points economy ----------
create policy "rewards: authenticated read"
  on public.rewards for select to authenticated
  using (true);

create policy "rewards: officers write"
  on public.rewards for insert to authenticated
  with check (public.is_officer(auth.uid()));

create policy "rewards: officers update"
  on public.rewards for update to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "rewards: officers delete"
  on public.rewards for delete to authenticated
  using (public.is_officer(auth.uid()));

create policy "achievements: authenticated read"
  on public.achievements for select to authenticated
  using (true);

create policy "achievements: officers write"
  on public.achievements for insert to authenticated
  with check (public.is_officer(auth.uid()));

create policy "achievements: officers update"
  on public.achievements for update to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "achievements: officers delete"
  on public.achievements for delete to authenticated
  using (public.is_officer(auth.uid()));

-- Awards are read-only from the client; they are created only by
-- the award_achievement() RPC (security definer).
create policy "achievement_awards: read own, officers read all"
  on public.achievement_awards for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

-- ---------- store ----------
create policy "orders: read own, officers read all"
  on public.orders for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

create policy "orders: insert own pending"
  on public.orders for insert to authenticated
  with check (user_id = auth.uid() and payment_status = 'pending');

create policy "orders: officers update"
  on public.orders for update to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "order_items: read via own order, officers read all"
  on public.order_items for select to authenticated
  using (
    public.is_officer(auth.uid())
    or exists (
      select 1 from public.orders o
      where o.id = order_id and o.user_id = auth.uid()
    )
  );

create policy "order_items: insert into own pending order"
  on public.order_items for insert to authenticated
  with check (
    exists (
      select 1 from public.orders o
      where o.id = order_id
        and o.user_id = auth.uid()
        and o.payment_status = 'pending'
    )
  );

create policy "cart_items: own all"
  on public.cart_items for all to authenticated
  using (user_id = auth.uid())
  with check (user_id = auth.uid());

-- ---------- audit log ----------
-- No insert/update/delete policies: rows are written exclusively by
-- security-definer trigger functions.
create policy "audit_log: officers read"
  on public.audit_log for select to authenticated
  using (public.is_officer(auth.uid()));
