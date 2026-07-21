-- Officer inbox: emails received at @umkcvsa.org (via Resend inbound
-- webhooks) and replies sent from the portal. Inserts happen only through
-- edge functions using the service role; officers read and update state
-- (read/archived) from the portal under RLS.

create table public.inbox_emails (
  id          bigint generated always as identity primary key,
  message_id  text unique,                     -- provider id (dedupes webhook retries)
  direction   text not null default 'in' check (direction in ('in', 'out')),
  from_addr   text not null,
  from_name   text,
  to_addr     text,
  subject     text,
  text_body   text,
  html_body   text,
  raw         jsonb,                           -- full webhook payload, in case parsing misses fields
  received_at timestamptz not null default now(),
  read_at     timestamptz,
  archived    boolean not null default false,
  replied_to  bigint references public.inbox_emails (id) on delete set null,
  sent_by     uuid references public.profiles (id) on delete set null
);

create index inbox_emails_received_idx on public.inbox_emails (received_at desc);

alter table public.inbox_emails enable row level security;

create policy "officers read inbox" on public.inbox_emails
  for select using (public.is_officer(auth.uid()));

create policy "officers update inbox" on public.inbox_emails
  for update using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

-- Explicit grants (new tables are not auto-exposed to API roles)
grant select, update on public.inbox_emails to authenticated;
grant all on public.inbox_emails to service_role;

-- Backfill grant for the moderation table (same auto-expose caveat)
grant all on public.discord_warnings to service_role;
