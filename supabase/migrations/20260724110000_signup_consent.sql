-- Signup consent: required ToS/privacy agreement (timestamped) and an
-- optional marketing/reminders email opt-in, both captured from signup
-- metadata. Plus a tokenless unsubscribe RPC for email footers — the
-- link carries the member's uuid, which is unguessable, and the only
-- thing the function can do is turn email off.

alter table public.profiles add column terms_accepted_at timestamptz;
alter table public.profiles add column email_opt_in boolean not null default false;

create or replace function public.handle_new_user()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare
  fn text := coalesce(new.raw_user_meta_data ->> 'first_name', '');
  ln text := coalesce(new.raw_user_meta_data ->> 'last_name', '');
  al text := nullif(trim(coalesce(new.raw_user_meta_data ->> 'alias', '')), '');
  tos boolean := coalesce((new.raw_user_meta_data ->> 'terms_accepted')::boolean, false);
  opt boolean := coalesce((new.raw_user_meta_data ->> 'email_opt_in')::boolean, false);
begin
  -- an alias grabbed between the form check and account creation shouldn't
  -- block signup — just drop it (the member can pick another in Settings)
  if al is not null and exists (
    select 1 from public.profiles where lower(alias) = lower(al)
  ) then
    al := null;
  end if;

  insert into public.profiles (id, first_name, last_name, full_name, alias, terms_accepted_at, email_opt_in)
  values (
    new.id, fn, ln,
    coalesce(nullif(trim(fn || ' ' || ln), ''), split_part(new.email, '@', 1)),
    al,
    case when tos then now() end,
    opt
  );
  insert into public.user_roles (user_id, role) values (new.id, 'member');
  return new;
end;
$$;

create function public.email_unsubscribe(uid uuid)
returns void
language sql security definer set search_path = public
as $$
  update public.profiles set email_opt_in = false where id = uid;
$$;
