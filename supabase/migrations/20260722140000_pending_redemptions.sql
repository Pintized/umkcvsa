-- Member self-service redemptions. A member requests a reward from the
-- Rewards page; the redemption sits in 'pending' (no VND deducted yet,
-- but pending requests count against what they can spend). An officer
-- completes it when the item is handed over — that's when the VND comes
-- off. Members may cancel their own pending request within 4 hours;
-- officers can cancel any pending request at any time.

alter table public.reward_redemptions
  add column status text not null default 'completed'
    check (status in ('pending', 'completed')),
  add column completed_at timestamptz;

update public.reward_redemptions set completed_at = created_at;

-- spendable = balance minus everything sitting in pending requests
create function public.pending_spend(uid uuid)
returns integer
language sql stable security definer set search_path = public
as $$
  select coalesce(sum(points_spent), 0)::int
    from public.reward_redemptions
   where user_id = uid and status = 'pending';
$$;
revoke execute on function public.pending_spend(uuid) from anon, public;

-- ---------- member: request a reward ----------
create function public.request_reward(reward bigint)
returns bigint
language plpgsql security definer set search_path = public
as $$
declare
  rw   record;
  cur  int;
  pend int;
  rid  bigint;
begin
  if auth.uid() is null then
    raise exception 'not signed in';
  end if;

  select id, name, point_cost into rw
    from public.rewards
   where id = reward and active;
  if not found then
    raise exception 'reward % not found or inactive', reward;
  end if;

  -- lock the profile row so simultaneous requests can't both pass the check
  select points into cur
    from public.profiles where id = auth.uid() for update;
  pend := public.pending_spend(auth.uid());
  if cur - pend < rw.point_cost then
    raise exception 'insufficient balance: % VND spendable (% balance − % pending)',
      cur - pend, cur, pend;
  end if;

  insert into public.reward_redemptions (reward_id, reward_name, user_id, points_spent, status)
  values (rw.id, rw.name, auth.uid(), rw.point_cost, 'pending')
  returning id into rid;

  return rid;
end;
$$;
revoke execute on function public.request_reward(bigint) from anon, public;

-- ---------- member (≤4h) or officer: cancel a pending request ----------
create function public.cancel_redemption(redemption bigint)
returns void
language plpgsql security definer set search_path = public
as $$
declare
  rd record;
begin
  select * into rd from public.reward_redemptions where id = redemption;
  if not found then
    raise exception 'redemption % not found', redemption;
  end if;
  if rd.status <> 'pending' then
    raise exception 'this redemption was already completed';
  end if;

  if rd.user_id = auth.uid() then
    if rd.created_at < now() - interval '4 hours'
       and not public.is_officer(auth.uid()) then
      raise exception 'the 4-hour cancellation window has passed — ask an officer to cancel it';
    end if;
  elsif not public.is_officer(auth.uid()) then
    raise exception 'you can only cancel your own redemptions';
  end if;

  delete from public.reward_redemptions where id = rd.id;
end;
$$;
revoke execute on function public.cancel_redemption(bigint) from anon, public;

-- ---------- officer: complete a pending request (item handed over) ----------
create function public.complete_redemption(redemption bigint)
returns integer
language plpgsql security definer set search_path = public
as $$
declare
  rd        record;
  cur       int;
  new_total int;
  uemail    text;
begin
  if not public.is_officer(auth.uid()) then
    raise exception 'officers only';
  end if;

  select * into rd from public.reward_redemptions where id = redemption for update;
  if not found then
    raise exception 'redemption % not found', redemption;
  end if;
  if rd.status <> 'pending' then
    raise exception 'this redemption was already completed';
  end if;

  select points into cur
    from public.profiles where id = rd.user_id for update;
  if cur < rd.points_spent then
    raise exception 'member has insufficient VND: balance %, needed %', cur, rd.points_spent;
  end if;

  update public.profiles
     set points = points - rd.points_spent
   where id = rd.user_id
  returning points into new_total;

  update public.reward_redemptions
     set status = 'completed', completed_at = now(), redeemed_by = auth.uid()
   where id = rd.id;

  select u.email into uemail from auth.users u where u.id = auth.uid();
  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (
    auth.uid(), uemail, 'reward_redeem', 'rewards',
    format('Completed redemption of "%s" (-%s VND) for %s — new balance %s VND',
           rd.reward_name, rd.points_spent, rd.user_id, new_total)
  );

  return new_total;
end;
$$;
revoke execute on function public.complete_redemption(bigint) from anon, public;

-- ---------- officer instant redeem now respects pending requests ----------
create or replace function public.redeem_reward(target uuid, reward bigint)
returns integer
language plpgsql security definer set search_path = public
as $$
declare
  rw        record;
  cur_pts   int;
  pend      int;
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

  select points into cur_pts
    from public.profiles where id = target for update;
  if not found then
    raise exception 'user % not found', target;
  end if;
  pend := public.pending_spend(target);
  if cur_pts - pend < rw.point_cost then
    raise exception 'not enough spendable VND: balance %, pending %, reward costs %',
      cur_pts, pend, rw.point_cost;
  end if;

  update public.profiles
     set points = points - rw.point_cost
   where id = target
  returning points into new_total;

  insert into public.reward_redemptions
    (reward_id, reward_name, user_id, points_spent, redeemed_by, status, completed_at)
  values (rw.id, rw.name, target, rw.point_cost, auth.uid(), 'completed', now());

  select u.email into uemail from auth.users u where u.id = auth.uid();
  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (
    auth.uid(), uemail, 'reward_redeem', 'rewards',
    format('Redeemed "%s" (-%s VND) for %s — new balance %s VND',
           rw.name, rw.point_cost, target, new_total)
  );

  return new_total;
end;
$$;
