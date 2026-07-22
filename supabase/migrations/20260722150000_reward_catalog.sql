-- Reward catalog upgrades: category (clothing/food/drinks/accessory),
-- clothing sizes, and limited stock. Stock is held at request time and
-- released if the request is cancelled, so two members can't both claim
-- the last shirt. Clothing redemptions must carry a size.

alter table public.rewards
  add column category text check (category in ('clothing', 'food', 'drinks', 'accessory')),
  add column sizes text[],
  add column stock integer check (stock >= 0);

alter table public.reward_redemptions
  add column size text;

-- ---------- member request: size + stock aware ----------
drop function public.request_reward(bigint);
create function public.request_reward(reward bigint, size text default null)
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

  -- lock the reward row so concurrent requests can't oversell the stock
  select id, name, point_cost, category, sizes, stock into rw
    from public.rewards
   where id = reward and active
     for update;
  if not found then
    raise exception 'reward % not found or inactive', reward;
  end if;

  if rw.category = 'clothing' then
    if size is null or not (size = any (coalesce(rw.sizes, array['XS','S','M','L','XL','XXL']))) then
      raise exception 'pick a size for this item';
    end if;
  else
    size := null;
  end if;

  if rw.stock is not null then
    if rw.stock < 1 then
      raise exception 'sold out: "%" has no stock left', rw.name;
    end if;
    update public.rewards set stock = stock - 1 where id = rw.id;
  end if;

  select points into cur
    from public.profiles where id = auth.uid() for update;
  pend := public.pending_spend(auth.uid());
  if cur - pend < rw.point_cost then
    raise exception 'insufficient balance: % VND spendable (% balance − % pending)',
      cur - pend, cur, pend;
  end if;

  insert into public.reward_redemptions (reward_id, reward_name, user_id, points_spent, status, size)
  values (rw.id, rw.name, auth.uid(), rw.point_cost, 'pending', size)
  returning id into rid;

  return rid;
end;
$$;
revoke execute on function public.request_reward(bigint, text) from anon, public;

-- ---------- cancel: put held stock back ----------
create or replace function public.cancel_redemption(redemption bigint)
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

  if rd.reward_id is not null then
    update public.rewards
       set stock = stock + 1
     where id = rd.reward_id and stock is not null;
  end if;

  delete from public.reward_redemptions where id = rd.id;
end;
$$;

-- ---------- officer instant redeem: size + stock aware ----------
drop function public.redeem_reward(uuid, bigint);
create function public.redeem_reward(target uuid, reward bigint, size text default null)
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

  select id, name, point_cost, category, sizes, stock into rw
    from public.rewards
   where id = reward and active
     for update;
  if not found then
    raise exception 'reward % not found or inactive', reward;
  end if;

  if rw.category = 'clothing' then
    if size is null or not (size = any (coalesce(rw.sizes, array['XS','S','M','L','XL','XXL']))) then
      raise exception 'pick a size for this item';
    end if;
  else
    size := null;
  end if;

  if rw.stock is not null then
    if rw.stock < 1 then
      raise exception 'sold out: "%" has no stock left', rw.name;
    end if;
    update public.rewards set stock = stock - 1 where id = rw.id;
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
    (reward_id, reward_name, user_id, points_spent, redeemed_by, status, completed_at, size)
  values (rw.id, rw.name, target, rw.point_cost, auth.uid(), 'completed', now(), size);

  select u.email into uemail from auth.users u where u.id = auth.uid();
  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (
    auth.uid(), uemail, 'reward_redeem', 'rewards',
    format('Redeemed "%s"%s (-%s VND) for %s — new balance %s VND',
           rw.name, coalesce(' (' || size || ')', ''), rw.point_cost, target, new_total)
  );

  return new_total;
end;
$$;
revoke execute on function public.redeem_reward(uuid, bigint, text) from anon, public;
