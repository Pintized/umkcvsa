-- ============================================================
-- Duties / responsibilities: an ordered checklist hanging off a
-- task. Distinct from task_edges (which link whole tasks into a
-- dependency graph) — these are the small steps inside one task.
-- ============================================================

create table public.task_duties (
  id         bigint generated always as identity primary key,
  task_id    bigint not null references public.tasks (id) on delete cascade,
  title      text not null,
  done       boolean not null default false,
  position   integer not null default 0,
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index task_duties_task_idx on public.task_duties (task_id, position);

alter table public.task_duties enable row level security;

-- Same boundary as the rest of the board: officers only.
create policy "task_duties: officers all"
  on public.task_duties for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

-- Keep open boards in sync (matches 20260719120000_tasks_realtime).
do $$
begin
  if not exists (
    select 1 from pg_publication_tables
    where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'task_duties'
  ) then
    alter publication supabase_realtime add table public.task_duties;
  end if;
end;
$$;

-- Audit coverage, consistent with the other board tables.
drop trigger if exists audit_row on public.task_duties;
create trigger audit_row after insert or update or delete on public.task_duties
  for each row execute function public.audit_row();
