-- ============================================================
-- LinkedIn on profiles + self-service security:
--   * profiles.linkedin_url — must actually be a LinkedIn link
--     (client normalizes, this check backstops it)
--   * profiles.show_linkedin — publicity toggle for the directory
--   * login_activity — one row per sign-in (ip/geo captured
--     client-side), visible only to the account owner
--   * trusted_devices — per-browser device registry the user can
--     label as trusted / remove
-- ============================================================

alter table public.profiles add column linkedin_url text
  check (linkedin_url is null or linkedin_url ~* '^https://(www\.)?linkedin\.com/');
alter table public.profiles add column show_linkedin boolean not null default false;

create table public.login_activity (
  id         bigint generated always as identity primary key,
  user_id    uuid not null references public.profiles (id) on delete cascade,
  device_id  uuid,
  ip         text,
  city       text,
  region     text,
  country    text,
  user_agent text,
  created_at timestamptz not null default now()
);
create index login_activity_user_idx on public.login_activity (user_id, created_at desc);

alter table public.login_activity enable row level security;
create policy "own activity: read"   on public.login_activity for select using (auth.uid() = user_id);
create policy "own activity: insert" on public.login_activity for insert with check (auth.uid() = user_id);
create policy "own activity: delete" on public.login_activity for delete using (auth.uid() = user_id);

create table public.trusted_devices (
  user_id    uuid not null references public.profiles (id) on delete cascade,
  device_id  uuid not null,
  label      text,
  trusted    boolean not null default false,
  first_seen timestamptz not null default now(),
  last_seen  timestamptz not null default now(),
  primary key (user_id, device_id)
);

alter table public.trusted_devices enable row level security;
create policy "own devices: read"   on public.trusted_devices for select using (auth.uid() = user_id);
create policy "own devices: insert" on public.trusted_devices for insert with check (auth.uid() = user_id);
create policy "own devices: update" on public.trusted_devices for update using (auth.uid() = user_id);
create policy "own devices: delete" on public.trusted_devices for delete using (auth.uid() = user_id);
