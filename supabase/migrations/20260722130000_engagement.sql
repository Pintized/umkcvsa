-- Engagement Settings: reward redemptions. An officer marks a reward as
-- claimed for a member; the member's points drop by the reward's cost and
-- the row records who marked it and when. Writes go only through the
-- redeem_reward() RPC — like achievement awards, never straight from the
-- client.

create table public.reward_redemptions (
  id           bigint generated always as identity primary key,
  reward_id    bigint references public.rewards (id) on delete set null,
  reward_name  text not null,          -- snapshot; history survives reward deletion
  user_id      uuid not null references public.profiles (id) on delete cascade,
  points_spent integer not null,
  redeemed_by  uuid references public.profiles (id) on delete set null,
  created_at   timestamptz not null default now()
);
create index reward_redemptions_user_idx on public.reward_redemptions (user_id);

alter table public.reward_redemptions enable row level security;

create policy "reward_redemptions: read own, officers read all"
  on public.reward_redemptions for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

grant select on public.reward_redemptions to authenticated;
grant all on public.reward_redemptions to service_role;

drop trigger if exists audit_row on public.reward_redemptions;
create trigger audit_row after insert or update or delete on public.reward_redemptions
  for each row execute function public.audit_row();

create function public.redeem_reward(target uuid, reward bigint)
returns integer
language plpgsql security definer set search_path = public
as $$
declare
  rw        record;
  cur_pts   int;
  new_total int;
  uemail    text;
begin
  if not public.is_officer(auth.uid()) then
    raise exception 'officers only';
  end if;

  select id, name, point_cost into rw
    from public.rewards
   where id = reward and active;
  if not found then
    raise exception 'reward % not found or inactive', reward;
  end if;

  -- lock the profile row so two officers can't double-spend the same points
  select points into cur_pts
    from public.profiles where id = target for update;
  if not found then
    raise exception 'user % not found', target;
  end if;
  if cur_pts < rw.point_cost then
    raise exception 'not enough points: member has %, reward costs %', cur_pts, rw.point_cost;
  end if;

  update public.profiles
     set points = points - rw.point_cost
   where id = target
  returning points into new_total;

  insert into public.reward_redemptions (reward_id, reward_name, user_id, points_spent, redeemed_by)
  values (rw.id, rw.name, target, rw.point_cost, auth.uid());

  select u.email into uemail from auth.users u where u.id = auth.uid();
  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (
    auth.uid(), uemail, 'reward_redeem', 'rewards',
    format('Redeemed "%s" (-%s pts) for %s — new total %s pts',
           rw.name, rw.point_cost, target, new_total)
  );

  return new_total;
end;
$$;

revoke execute on function public.redeem_reward(uuid, bigint) from anon, public;
