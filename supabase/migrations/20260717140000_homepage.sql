-- ============================================================
-- Homepage backend:
--   announcements  -> admin-managed news cards
--   site_settings  -> key/value (eboard teaser video URL, etc.)
--   site_stats()   -> safe public counters (profiles are RLS-locked,
--                     so anon needs a definer function for counts)
-- ============================================================

create table public.announcements (
  id         bigint generated always as identity primary key,
  title      text not null,
  body       text,
  link_url   text,
  link_label text,
  sort       integer not null default 0,
  active     boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.site_settings (
  key        text primary key,
  value      text,
  updated_at timestamptz not null default now()
);

alter table public.announcements enable row level security;
alter table public.site_settings enable row level security;

create policy "announcements: public read"
  on public.announcements for select using (true);
create policy "announcements: admins insert"
  on public.announcements for insert to authenticated
  with check (public.has_role(auth.uid(), 'admin'));
create policy "announcements: admins update"
  on public.announcements for update to authenticated
  using (public.has_role(auth.uid(), 'admin'))
  with check (public.has_role(auth.uid(), 'admin'));
create policy "announcements: admins delete"
  on public.announcements for delete to authenticated
  using (public.has_role(auth.uid(), 'admin'));

create policy "settings: public read"
  on public.site_settings for select using (true);
create policy "settings: admins upsert"
  on public.site_settings for insert to authenticated
  with check (public.has_role(auth.uid(), 'admin'));
create policy "settings: admins update"
  on public.site_settings for update to authenticated
  using (public.has_role(auth.uid(), 'admin'))
  with check (public.has_role(auth.uid(), 'admin'));

create trigger set_updated_at before update on public.announcements
  for each row execute function public.set_updated_at();
create trigger set_updated_at before update on public.site_settings
  for each row execute function public.set_updated_at();

create trigger audit_row after insert or update or delete on public.announcements
  for each row execute function public.audit_row();

-- public counters without exposing member rows
create function public.site_stats()
returns json
language sql stable security definer set search_path = public
as $$
  select json_build_object(
    'members', (select count(*) from public.profiles),
    'events',  (select count(*) from public.events)
  );
$$;

insert into public.site_settings (key, value) values
  ('eboard_video_url', ''),
  ('eboard_video_title', 'Meet Your New E-Board · 2026–27');
