-- Alias at signup: capture the chosen alias from signup metadata (falling
-- back to none if it was taken in the meantime), and expose an
-- availability check the signup form can call before an account exists.

create or replace function public.handle_new_user()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare
  fn text := coalesce(new.raw_user_meta_data ->> 'first_name', '');
  ln text := coalesce(new.raw_user_meta_data ->> 'last_name', '');
  al text := nullif(trim(coalesce(new.raw_user_meta_data ->> 'alias', '')), '');
begin
  -- an alias grabbed between the form check and account creation shouldn't
  -- block signup — just drop it (the member can pick another in Settings)
  if al is not null and exists (
    select 1 from public.profiles where lower(alias) = lower(al)
  ) then
    al := null;
  end if;

  insert into public.profiles (id, first_name, last_name, full_name, alias)
  values (
    new.id, fn, ln,
    coalesce(nullif(trim(fn || ' ' || ln), ''), split_part(new.email, '@', 1)),
    al
  );
  insert into public.user_roles (user_id, role) values (new.id, 'member');
  return new;
end;
$$;

-- Availability check for the signup form (anon users can't read profiles,
-- so this returns only a boolean and nothing else).
create function public.alias_available(candidate text)
returns boolean
language sql stable security definer set search_path = public
as $$
  select coalesce(trim(candidate), '') = ''
      or not exists (
        select 1 from public.profiles
        where lower(alias) = lower(trim(candidate))
      );
$$;

grant execute on function public.alias_available(text) to anon, authenticated;
