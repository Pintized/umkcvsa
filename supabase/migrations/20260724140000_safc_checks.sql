-- Checkboxes on the SAFC Deadlines 26-27 table: officers tick off
-- cycles they've handled. Shared state (everyone sees the same
-- checks), keyed by the submit-by date.

create table public.safc_deadline_checks (
  deadline   date primary key,
  checked_by uuid references public.profiles (id) on delete set null,
  checked_at timestamptz not null default now()
);

alter table public.safc_deadline_checks enable row level security;
create policy "officers manage safc checks" on public.safc_deadline_checks
  for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

grant all on public.safc_deadline_checks to authenticated;
grant all on public.safc_deadline_checks to service_role;
