-- ============================================================
-- Sponsors: shown on the homepage, managed from Admin > Home Page
-- ============================================================

create table public.sponsors (
  id         bigint generated always as identity primary key,
  name       text not null,
  logo_path  text,
  link_url   text,
  sort       integer not null default 0,
  active     boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

alter table public.sponsors enable row level security;

create policy "sponsors: public read"
  on public.sponsors for select using (true);
create policy "sponsors: admins insert"
  on public.sponsors for insert to authenticated
  with check (public.has_role(auth.uid(), 'admin'));
create policy "sponsors: admins update"
  on public.sponsors for update to authenticated
  using (public.has_role(auth.uid(), 'admin'))
  with check (public.has_role(auth.uid(), 'admin'));
create policy "sponsors: admins delete"
  on public.sponsors for delete to authenticated
  using (public.has_role(auth.uid(), 'admin'));

create trigger set_updated_at before update on public.sponsors
  for each row execute function public.set_updated_at();
create trigger audit_row after insert or update or delete on public.sponsors
  for each row execute function public.audit_row();
