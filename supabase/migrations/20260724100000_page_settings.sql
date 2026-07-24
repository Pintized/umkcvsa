-- Per-page visibility switches (Admin > Pages). A missing row means
-- enabled. Everyone signed in can read (the sidebar and route guard
-- need it); only admins flip switches. Admins always see every page
-- regardless of these flags — enforcement lives in guard.js/shell.js.

create table public.page_settings (
  path       text primary key,
  enabled    boolean not null default true,
  updated_at timestamptz not null default now()
);

alter table public.page_settings enable row level security;

create policy "signed-in can read page settings" on public.page_settings
  for select using (auth.uid() is not null);
create policy "admins insert page settings" on public.page_settings
  for insert with check (public.has_role(auth.uid(), 'admin'));
create policy "admins update page settings" on public.page_settings
  for update using (public.has_role(auth.uid(), 'admin'));
