-- Audit upgrade: capture actor name + full row data for every officer CRUD,
-- and extend trigger coverage to all officer-managed tables (finance, inbox,
-- roles, site settings, relation tables).

alter table public.audit_log
  add column user_name text,
  add column data jsonb;

create or replace function public.audit_row()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare
  rec      jsonb;
  slim_new jsonb;
  slim_old jsonb;
  payload  jsonb;
  label    text;
  uemail   text;
  uname    text;
  actor    uuid;
begin
  -- Heavy/noisy fields excluded from snapshots
  slim_new := to_jsonb(new) - 'html_body' - 'raw';
  slim_old := to_jsonb(old) - 'html_body' - 'raw';

  -- Skip pure bookkeeping updates on the inbox (read-marking) — they would
  -- drown the log every time an officer opens a message.
  if tg_op = 'UPDATE' and tg_table_name = 'inbox_emails'
     and (slim_new - 'read_at') = (slim_old - 'read_at') then
    return new;
  end if;

  if tg_op = 'DELETE' then
    rec := slim_old;
  else
    rec := slim_new;
  end if;

  label := coalesce(rec ->> 'name', rec ->> 'title', rec ->> 'subject', rec ->> 'id');

  -- Edge functions run as service role (no auth.uid()); fall back to the
  -- row's recorded author so sends from the portal still credit the officer.
  actor := coalesce(
    auth.uid(),
    nullif(rec ->> 'sent_by', '')::uuid,
    nullif(rec ->> 'created_by', '')::uuid
  );
  if actor is not null then
    select u.email into uemail from auth.users u where u.id = actor;
    select p.full_name into uname from public.profiles p where p.id = actor;
  end if;

  payload := case tg_op
    when 'UPDATE' then jsonb_build_object('old', slim_old, 'new', slim_new)
    when 'DELETE' then jsonb_build_object('old', slim_old)
    else jsonb_build_object('new', slim_new)
  end;

  insert into public.audit_log (user_id, user_email, user_name, action, entity, details, data)
  values (actor, uemail, uname, lower(tg_op), tg_table_name, left(label, 500), payload);

  if tg_op = 'DELETE' then
    return old;
  end if;
  return new;
end;
$$;

-- Ensure every officer-managed table is covered (idempotent re-attach)
do $$
declare t text;
begin
  foreach t in array array[
    'events', 'tasks', 'inventory', 'notes', 'note_folders',
    'documents', 'document_folders', 'rewards', 'achievements', 'orders',
    'announcements', 'eboard_members', 'store_products', 'sponsors',
    'inbox_emails', 'user_roles', 'site_settings',
    'event_attendance', 'achievement_awards',
    'task_assignees', 'task_edges', 'note_attendees', 'inventory_log',
    'finance_budgets', 'finance_dues', 'finance_fundraisers',
    'finance_planner_items', 'finance_planner_receipts',
    'finance_reimbursements', 'finance_requests',
    'finance_sponsorships', 'finance_transactions'
  ]
  loop
    execute format('drop trigger if exists audit_row on public.%I', t);
    execute format(
      'create trigger audit_row after insert or update or delete on public.%I
       for each row execute function public.audit_row()', t);
  end loop;
end;
$$;
