-- Discord ↔ portal account linking. A member generates a short-lived
-- code in Settings, then runs /verify <code> in the Discord server; the
-- bot (service role) matches the code, stores the link, and hands out
-- the member role.

create table public.discord_links (
  user_id     uuid primary key references public.profiles (id) on delete cascade,
  discord_id  text not null unique,
  discord_tag text,
  linked_at   timestamptz not null default now()
);

create table public.discord_verify_codes (
  code       text primary key,
  user_id    uuid not null unique references public.profiles (id) on delete cascade,
  created_at timestamptz not null default now(),
  expires_at timestamptz not null
);

alter table public.discord_links        enable row level security;
alter table public.discord_verify_codes enable row level security;

create policy "discord_links: read own, officers read all"
  on public.discord_links for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

create policy "discord_links: unlink own"
  on public.discord_links for delete to authenticated
  using (user_id = auth.uid());

-- codes are only touched via the RPC below and the bot's service role
grant select, delete on public.discord_links to authenticated;
grant all on public.discord_links, public.discord_verify_codes to service_role;

-- generate (or refresh) my verification code — valid 15 minutes
create function public.discord_verify_code()
returns text
language plpgsql security definer set search_path = public
as $$
declare
  chars constant text := 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; -- no 0/O/1/I/L
  c text;
  i int;
begin
  if auth.uid() is null then
    raise exception 'not signed in';
  end if;

  loop
    c := '';
    for i in 1..6 loop
      c := c || substr(chars, 1 + floor(random() * length(chars))::int, 1);
    end loop;
    begin
      insert into public.discord_verify_codes (code, user_id, expires_at)
      values (c, auth.uid(), now() + interval '15 minutes')
      on conflict (user_id) do update
        set code = excluded.code,
            created_at = now(),
            expires_at = excluded.expires_at;
      exit;
    exception when unique_violation then
      -- code collision with another user's active code — roll again
    end;
  end loop;

  return c;
end;
$$;
revoke execute on function public.discord_verify_code() from anon, public;
