-- ============================================================
-- Realtime sync for the officer task board: broadcast changes on
-- the three board tables so open boards update instantly instead
-- of polling. RLS still applies to what each subscriber receives.
-- ============================================================

do $$
declare t text;
begin
  foreach t in array array['tasks', 'task_edges', 'task_assignees']
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
