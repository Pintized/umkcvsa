-- ============================================================
-- Personal reminders on the member calendar.
--
-- Strictly private: each row is visible only to the account that
-- created it. Unlike rsvps (which officers may read), there is no
-- officer escape hatch here — a member's own reminders are not club
-- business.
--
-- Deliberately NOT covered by the audit_row trigger: audit_log stores
-- a full jsonb snapshot of every audited row and officers can read
-- audit_log, which would leak reminder contents straight past this
-- table's RLS.
-- ============================================================

create table public.personal_events (
  id         bigint generated always as identity primary key,
  user_id    uuid not null references public.profiles (id) on delete cascade,
  title      text not null,
  event_date date not null,
  start_time time,
  note       text,
  color      text not null default 'gold'
             check (color in ('gold', 'red', 'teal', 'violet', 'green', 'blue')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index personal_events_user_date_idx on public.personal_events (user_id, event_date);

alter table public.personal_events enable row level security;

-- One policy covering select/insert/update/delete: you, and only you.
create policy "personal_events: owner only"
  on public.personal_events for all to authenticated
  using (user_id = auth.uid())
  with check (user_id = auth.uid());
