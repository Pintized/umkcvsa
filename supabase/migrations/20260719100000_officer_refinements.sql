-- ============================================================
-- Officer tool refinements:
--   * event_attendance — who actually showed up (check-in at the
--     door); foundation for points-for-attendance later
--   * inventory_log — append-only quantity history (who/when/why)
--   * inventory item photos (inventory-images bucket)
-- ============================================================

-- ---------- event attendance ----------
create table public.event_attendance (
  id            bigint generated always as identity primary key,
  event_id      bigint not null references public.events (id) on delete cascade,
  user_id       uuid not null references public.profiles (id) on delete cascade,
  checked_in_by uuid references public.profiles (id) on delete set null,
  created_at    timestamptz not null default now(),
  unique (event_id, user_id)
);
create index event_attendance_event_idx on public.event_attendance (event_id);

alter table public.event_attendance enable row level security;

create policy "event_attendance: read own, officers read all"
  on public.event_attendance for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

create policy "event_attendance: officers insert"
  on public.event_attendance for insert to authenticated
  with check (public.is_officer(auth.uid()) and checked_in_by = auth.uid());

create policy "event_attendance: officers delete"
  on public.event_attendance for delete to authenticated
  using (public.is_officer(auth.uid()));

-- No audit trigger: a 50-person check-in would flood the audit log,
-- and the table itself records who/when via checked_in_by/created_at.

-- ---------- inventory quantity history ----------
create table public.inventory_log (
  id           bigint generated always as identity primary key,
  item_id      bigint not null references public.inventory (id) on delete cascade,
  user_id      uuid references public.profiles (id) on delete set null,
  delta        integer not null,
  new_quantity integer not null,
  reason       text,
  created_at   timestamptz not null default now()
);
create index inventory_log_item_idx on public.inventory_log (item_id, created_at desc);

alter table public.inventory_log enable row level security;

create policy "inventory_log: officers read"
  on public.inventory_log for select to authenticated
  using (public.is_officer(auth.uid()));

-- Append-only: no update/delete policies.
create policy "inventory_log: officers insert own"
  on public.inventory_log for insert to authenticated
  with check (public.is_officer(auth.uid()) and user_id = auth.uid());

-- ---------- inventory item photos ----------
alter table public.inventory add column photo_path text;

insert into storage.buckets (id, name, public)
values ('inventory-images', 'inventory-images', true)
on conflict (id) do nothing;

create policy "inventory-images: public read"
  on storage.objects for select
  using (bucket_id = 'inventory-images');

create policy "inventory-images: officers insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'inventory-images' and public.is_officer(auth.uid()));

create policy "inventory-images: officers update"
  on storage.objects for update to authenticated
  using (bucket_id = 'inventory-images' and public.is_officer(auth.uid()))
  with check (bucket_id = 'inventory-images' and public.is_officer(auth.uid()));

create policy "inventory-images: officers delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'inventory-images' and public.is_officer(auth.uid()));
