-- ============================================================
-- Support tickets + in-app notifications.
--
-- Members open a ticket from /app/support; officers answer it from
-- /app/officer/tickets as a threaded conversation. Each side is told
-- about the other's replies through the topbar bell.
--
-- Boundary: a ticket is visible to the member who opened it and to
-- officers — support is club business, unlike personal_events which is
-- private to its owner. Notifications are stricter: owner-only, and no
-- client may insert them at all (see below).
-- ============================================================

create table public.tickets (
  id         bigint generated always as identity primary key,
  user_id    uuid not null references public.profiles (id) on delete cascade,
  subject    text not null,
  category   text not null default 'question'
             check (category in ('account', 'events', 'rewards', 'bug', 'question', 'other')),
  status     text not null default 'open'
             check (status in ('open', 'in_progress', 'resolved', 'closed')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index tickets_user_idx   on public.tickets (user_id, updated_at desc);
create index tickets_status_idx on public.tickets (status, updated_at desc);

create table public.ticket_messages (
  id         bigint generated always as identity primary key,
  ticket_id  bigint not null references public.tickets (id) on delete cascade,
  user_id    uuid not null references public.profiles (id) on delete set null,
  body       text not null,
  created_at timestamptz not null default now()
);
create index ticket_messages_ticket_idx on public.ticket_messages (ticket_id, created_at);

create table public.notifications (
  id         bigint generated always as identity primary key,
  user_id    uuid not null references public.profiles (id) on delete cascade,
  kind       text not null default 'ticket',
  title      text not null,
  body       text,
  link       text,
  read_at    timestamptz,
  created_at timestamptz not null default now()
);
create index notifications_user_idx on public.notifications (user_id, created_at desc);

alter table public.tickets         enable row level security;
alter table public.ticket_messages enable row level security;
alter table public.notifications   enable row level security;

-- ---------- tickets ----------
create policy "tickets: read own or officer"
  on public.tickets for select to authenticated
  using (user_id = auth.uid() or public.is_officer(auth.uid()));

create policy "tickets: member opens own"
  on public.tickets for insert to authenticated
  with check (user_id = auth.uid());

-- only officers change status; members can't reopen or close their own
create policy "tickets: officers update"
  on public.tickets for update to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

-- ---------- ticket messages ----------
create policy "ticket_messages: read own thread or officer"
  on public.ticket_messages for select to authenticated
  using (
    public.is_officer(auth.uid())
    or exists (select 1 from public.tickets t
               where t.id = ticket_id and t.user_id = auth.uid())
  );

-- you may only post as yourself, and only onto your own ticket unless
-- you're an officer
create policy "ticket_messages: post to own thread or officer"
  on public.ticket_messages for insert to authenticated
  with check (
    user_id = auth.uid()
    and (
      public.is_officer(auth.uid())
      or exists (select 1 from public.tickets t
                 where t.id = ticket_id and t.user_id = auth.uid())
    )
  );

-- ---------- notifications ----------
-- Owner-only, and deliberately NO insert policy: rows are written solely
-- by the security-definer triggers below, so nobody can forge a
-- notification for another account.
create policy "notifications: read own"
  on public.notifications for select to authenticated
  using (user_id = auth.uid());

create policy "notifications: mark own read"
  on public.notifications for update to authenticated
  using (user_id = auth.uid())
  with check (user_id = auth.uid());

-- ============================================================
-- Notification fan-out
-- ============================================================

-- security definer so it can write rows addressed to other accounts,
-- which the owner-only policy above would otherwise block
create or replace function public.notify_ticket_message()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare
  t          public.tickets%rowtype;
  author     text;
  is_staff   boolean;
  preview    text;
begin
  select * into t from public.tickets where id = new.ticket_id;
  if not found then return new; end if;

  select coalesce(full_name, 'A member') into author
  from public.profiles where id = new.user_id;

  is_staff := new.user_id <> t.user_id;
  preview  := left(regexp_replace(new.body, '\s+', ' ', 'g'), 140);

  if is_staff then
    -- an officer answered: tell the member who opened it
    insert into public.notifications (user_id, kind, title, body, link)
    values (t.user_id, 'ticket',
            'Reply to "' || t.subject || '"', preview, '/app/support');
  else
    -- the member wrote: tell every officer so it doesn't sit unseen
    insert into public.notifications (user_id, kind, title, body, link)
    select distinct ur.user_id, 'ticket',
           author || ' replied on "' || t.subject || '"', preview,
           '/app/officer/tickets'
    from public.user_roles ur
    where ur.role in ('officer', 'admin')
      and ur.user_id <> new.user_id;
  end if;

  update public.tickets set updated_at = now() where id = t.id;
  return new;
end;
$$;

drop trigger if exists notify_on_ticket_message on public.ticket_messages;
create trigger notify_on_ticket_message after insert on public.ticket_messages
  for each row execute function public.notify_ticket_message();

-- new ticket -> alert the officers
create or replace function public.notify_new_ticket()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare author text;
begin
  select coalesce(full_name, 'A member') into author
  from public.profiles where id = new.user_id;

  insert into public.notifications (user_id, kind, title, body, link)
  select distinct ur.user_id, 'ticket',
         'New ticket from ' || author, new.subject, '/app/officer/tickets'
  from public.user_roles ur
  where ur.role in ('officer', 'admin') and ur.user_id <> new.user_id;
  return new;
end;
$$;

drop trigger if exists notify_on_new_ticket on public.tickets;
create trigger notify_on_new_ticket after insert on public.tickets
  for each row execute function public.notify_new_ticket();

-- status change -> tell the member who opened it
create or replace function public.notify_ticket_status()
returns trigger
language plpgsql security definer set search_path = public
as $$
begin
  if new.status is distinct from old.status then
    insert into public.notifications (user_id, kind, title, body, link)
    values (new.user_id, 'ticket',
            'Your ticket is now ' || replace(new.status, '_', ' '),
            new.subject, '/app/support');
  end if;
  return new;
end;
$$;

drop trigger if exists notify_on_ticket_status on public.tickets;
create trigger notify_on_ticket_status after update on public.tickets
  for each row execute function public.notify_ticket_status();

-- ============================================================
-- Realtime + grants + audit
-- ============================================================

-- the bell listens for its own rows (matches 20260719120000_tasks_realtime)
do $$
declare t text;
begin
  foreach t in array array['notifications', 'tickets', 'ticket_messages']
  loop
    if not exists (
      select 1 from pg_publication_tables
      where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = t
    ) then
      execute format('alter publication supabase_realtime add table public.%I', t);
    end if;
  end loop;
end;
$$;

-- new tables are not auto-exposed to API roles (see 20260721110000_inbox)
grant select, insert, update on public.tickets         to authenticated;
grant select, insert         on public.ticket_messages to authenticated;
grant select, update         on public.notifications   to authenticated;

-- Audit the ticket row (status changes are worth a trail) but NOT
-- notifications: audit_log keeps a full row snapshot and officers can read
-- it, which is the leak 20260829120000_personal_events documents.
drop trigger if exists audit_row on public.tickets;
create trigger audit_row after insert or update or delete on public.tickets
  for each row execute function public.audit_row();
