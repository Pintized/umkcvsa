-- ============================================================
-- Profile aliases + locked legal names:
--   * alias — optional display nickname, unique case-insensitively
--   * first/last/full name can no longer be changed by the member
--     themselves (typos get fixed by an officer, who can update any
--     profile via the existing officers-update-any policy)
-- ============================================================

alter table public.profiles add column alias text;

create unique index profiles_alias_unique
  on public.profiles (lower(alias))
  where alias is not null and alias <> '';

create function public.protect_profile_names()
returns trigger
language plpgsql security definer set search_path = public
as $$
begin
  if (new.first_name is distinct from old.first_name
      or new.last_name is distinct from old.last_name
      or new.full_name is distinct from old.full_name)
     and auth.uid() is not null
     and not public.is_officer(auth.uid()) then
    raise exception 'names can only be changed by an officer';
  end if;
  return new;
end;
$$;

create trigger protect_profile_names
  before update on public.profiles
  for each row execute function public.protect_profile_names();
