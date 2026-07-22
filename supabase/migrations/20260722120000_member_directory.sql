-- Member Directory support: officers can take an achievement back.
-- Mirrors award_achievement(): removes the award row, subtracts the points
-- it originally granted (never dropping below 0), and writes an audit entry.

create function public.revoke_achievement(award bigint)
returns integer
language plpgsql security definer set search_path = public
as $$
declare
  aw        record;
  new_total int;
  uemail    text;
begin
  if not public.is_officer(auth.uid()) then
    raise exception 'officers only';
  end if;

  select a.id, a.user_id, a.points_awarded, ach.name
    into aw
    from public.achievement_awards a
    join public.achievements ach on ach.id = a.achievement_id
   where a.id = award;
  if not found then
    raise exception 'award % not found', award;
  end if;

  delete from public.achievement_awards where id = aw.id;

  update public.profiles
     set points = greatest(0, points - aw.points_awarded)
   where id = aw.user_id
  returning points into new_total;

  select u.email into uemail from auth.users u where u.id = auth.uid();
  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (
    auth.uid(), uemail, 'achievement_revoke', 'achievements',
    format('Removed "%s" (-%s pts) from %s — new total %s pts',
           aw.name, aw.points_awarded, aw.user_id, new_total)
  );

  return new_total;
end;
$$;

revoke execute on function public.revoke_achievement(bigint) from anon, public;
