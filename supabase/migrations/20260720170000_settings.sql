-- ============================================================
-- Member settings:
--   * language preference (en/vi/es/zh) — drives portal chrome
--   * privacy: hide from the member directory / hide points
--   * notification preferences (jsonb switchboard; delivery later)
--   * school email + verification timestamp (self-verifiable only
--     when the login email IS the school address; officers can set
--     it manually; anything else waits for the email pipeline)
--   * delete_my_account() — full self-service deletion
-- ============================================================

alter table public.profiles
  add column language text not null default 'en' check (language in ('en', 'vi', 'es', 'zh')),
  add column hide_directory boolean not null default false,
  add column hide_points boolean not null default false,
  add column notif_prefs jsonb not null default '{}'::jsonb,
  add column school_email text,
  add column school_email_verified_at timestamptz;

-- users may only mark their school email verified when their login
-- email is that same, already-confirmed school address
create function public.protect_school_verification()
returns trigger
language plpgsql security definer set search_path = public
as $$
begin
  if new.school_email_verified_at is distinct from old.school_email_verified_at
     and auth.uid() is not null
     and not public.is_officer(auth.uid()) then
    if new.school_email_verified_at is not null and not exists (
      select 1 from auth.users u
      where u.id = auth.uid()
        and lower(u.email) = lower(coalesce(new.school_email, ''))
        and u.email ~* '@(umkc\.edu|mail\.umkc\.edu|umsystem\.edu)$'
    ) then
      raise exception 'school email verification is managed by the system';
    end if;
  end if;
  return new;
end;
$$;

create trigger protect_school_verification
  before update on public.profiles
  for each row execute function public.protect_school_verification();

-- full account deletion; FK cascades take the profile, roles,
-- rsvps, attendance, etc. with it
create function public.delete_my_account()
returns void
language plpgsql security definer set search_path = public
as $$
begin
  if auth.uid() is null then
    raise exception 'not signed in';
  end if;
  delete from auth.users where id = auth.uid();
end;
$$;

revoke execute on function public.delete_my_account() from anon, public;
