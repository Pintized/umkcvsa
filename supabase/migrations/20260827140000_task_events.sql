-- ============================================================
-- Tie tasks to the event(s) they belong to. Shaped exactly like
-- task_assignees: a plain join table, many events per task.
-- Replaces the task description field in the officer UI.
-- ============================================================

create table public.task_events (
  task_id  bigint not null references public.tasks (id) on delete cascade,
  event_id bigint not null references public.events (id) on delete cascade,
  primary key (task_id, event_id)
);
create index task_events_event_idx on public.task_events (event_id);

alter table public.task_events enable row level security;

create policy "task_events: officers all"
  on public.task_events for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

-- Keep open boards in sync (matches 20260719120000_tasks_realtime).
do $$
begin
  if not exists (
    select 1 from pg_publication_tables
    where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'task_events'
  ) then
    alter publication supabase_realtime add table public.task_events;
  end if;
end;
$$;

-- Audit coverage, consistent with the other board tables.
drop trigger if exists audit_row on public.task_events;
create trigger audit_row after insert or update or delete on public.task_events
  for each row execute function public.audit_row();

-- NOTE: public.tasks.description is intentionally left in place. The officer
-- UI no longer reads or writes it (no task ever used it), but dropping a
-- column is irreversible, so it stays until someone deliberately removes it.
