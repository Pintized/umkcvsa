-- ============================================================
-- UMKC VSA - Triggers & RPCs
--   * auto-create profile + member role on signup
--   * updated_at maintenance
--   * points protected from self-service edits
--   * audit log written by triggers (replaces partials/audit.php)
--   * grant_points / award_achievement RPCs (officer-only)
-- ============================================================

-- ---------- profile auto-creation on signup ----------
create function public.handle_new_user()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare
  fn text := coalesce(new.raw_user_meta_data ->> 'first_name', '');
  ln text := coalesce(new.raw_user_meta_data ->> 'last_name', '');
begin
  insert into public.profiles (id, first_name, last_name, full_name)
  values (
    new.id, fn, ln,
    coalesce(nullif(trim(fn || ' ' || ln), ''), split_part(new.email, '@', 1))
  );
  insert into public.user_roles (user_id, role) values (new.id, 'member');
  return new;
end;
$$;

create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

-- ---------- updated_at maintenance ----------
create function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

do $$
declare t text;
begin
  foreach t in array array[
    'profiles', 'events', 'tasks', 'note_folders', 'notes',
    'document_folders', 'documents', 'inventory', 'rewards', 'achievements'
  ]
  loop
    execute format(
      'create trigger set_updated_at before update on public.%I
       for each row execute function public.set_updated_at()', t);
  end loop;
end;
$$;

-- ---------- points may only change via officer action ----------
-- (auth.uid() is null for direct server-side/SQL access, which stays allowed)
create function public.protect_points()
returns trigger
language plpgsql security definer set search_path = public
as $$
begin
  if new.points is distinct from old.points
     and auth.uid() is not null
     and not public.is_officer(auth.uid()) then
    raise exception 'points can only be changed by officers';
  end if;
  return new;
end;
$$;

create trigger protect_points
  before update on public.profiles
  for each row execute function public.protect_points();

-- ---------- audit trail ----------
create function public.audit_row()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare
  rec    jsonb;
  label  text;
  uemail text;
begin
  if tg_op = 'DELETE' then
    rec := to_jsonb(old);
  else
    rec := to_jsonb(new);
  end if;
  label := coalesce(rec ->> 'name', rec ->> 'title', rec ->> 'id');
  select u.email into uemail from auth.users u where u.id = auth.uid();

  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (auth.uid(), uemail, lower(tg_op), tg_table_name, left(label, 500));

  if tg_op = 'DELETE' then
    return old;
  end if;
  return new;
end;
$$;

do $$
declare t text;
begin
  foreach t in array array[
    'events', 'tasks', 'inventory', 'notes', 'note_folders',
    'documents', 'document_folders', 'rewards', 'achievements', 'orders'
  ]
  loop
    execute format(
      'create trigger audit_row after insert or update or delete on public.%I
       for each row execute function public.audit_row()', t);
  end loop;
end;
$$;

-- ---------- officer RPCs ----------
create function public.grant_points(target uuid, delta integer, reason text default null)
returns integer
language plpgsql security definer set search_path = public
as $$
declare
  new_total int;
  uemail    text;
begin
  if not public.is_officer(auth.uid()) then
    raise exception 'officers only';
  end if;

  update public.profiles
     set points = greatest(0, points + delta)
   where id = target
  returning points into new_total;

  if not found then
    raise exception 'user % not found', target;
  end if;

  select u.email into uemail from auth.users u where u.id = auth.uid();
  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (
    auth.uid(), uemail, 'grant_points', 'profiles',
    format('Granted %s pts to %s (new total %s)%s',
           delta, target, new_total, coalesce(' — ' || reason, ''))
  );

  return new_total;
end;
$$;

create function public.award_achievement(target uuid, achievement bigint)
returns integer
language plpgsql security definer set search_path = public
as $$
declare
  ach       record;
  new_total int;
  uemail    text;
begin
  if not public.is_officer(auth.uid()) then
    raise exception 'officers only';
  end if;

  select id, name, points into ach
    from public.achievements
   where id = achievement and active;
  if not found then
    raise exception 'achievement % not found or inactive', achievement;
  end if;

  insert into public.achievement_awards (achievement_id, user_id, awarded_by, points_awarded)
  values (ach.id, target, auth.uid(), ach.points);

  update public.profiles
     set points = points + ach.points
   where id = target
  returning points into new_total;

  if not found then
    raise exception 'user % not found', target;
  end if;

  select u.email into uemail from auth.users u where u.id = auth.uid();
  insert into public.audit_log (user_id, user_email, action, entity, details)
  values (
    auth.uid(), uemail, 'achievement_award', 'achievements',
    format('Awarded "%s" (+%s pts) to %s — new total %s pts',
           ach.name, ach.points, target, new_total)
  );

  return new_total;
end;
$$;

-- RPCs are for signed-in users only; officer check runs inside.
revoke execute on function public.grant_points(uuid, integer, text) from anon, public;
revoke execute on function public.award_achievement(uuid, bigint) from anon, public;
