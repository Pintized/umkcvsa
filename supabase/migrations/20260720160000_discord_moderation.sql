-- Warning counts for Discord auto-moderation (3-strike system).
-- Accessed only by the gateway bot via the service role; RLS enabled with no
-- policies so anon/authenticated clients cannot read or write it.

create table public.discord_warnings (
  discord_user_id text primary key,
  count           int  not null default 0,
  last_offense_at timestamptz
);

alter table public.discord_warnings enable row level security;
