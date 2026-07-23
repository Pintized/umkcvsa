-- Teachable knowledge for the Discord VSA Bot. Admins curate via the
-- portal; a designated Discord role can add facts with /teach. The
-- edge function reads with the service role, so RLS here only governs
-- the portal UI (admin-only).

create table public.bot_knowledge (
  id         bigint generated always as identity primary key,
  fact       text not null check (char_length(fact) between 3 and 500),
  source     text not null default 'portal' check (source in ('portal', 'discord')),
  taught_by  text,  -- portal: profile name; discord: username
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now()
);

alter table public.bot_knowledge enable row level security;

create policy "admins read bot knowledge" on public.bot_knowledge
  for select using (public.has_role(auth.uid(), 'admin'));
create policy "admins add bot knowledge" on public.bot_knowledge
  for insert with check (public.has_role(auth.uid(), 'admin'));
create policy "admins edit bot knowledge" on public.bot_knowledge
  for update using (public.has_role(auth.uid(), 'admin'));
create policy "admins remove bot knowledge" on public.bot_knowledge
  for delete using (public.has_role(auth.uid(), 'admin'));
