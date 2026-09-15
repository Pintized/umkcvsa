-- ============================================================
-- Usage report behind Admin > Storage.
--
-- Neither number is reachable from the browser on its own: the storage
-- schema isn't exposed to PostgREST, and pg_database_size needs privileges
-- no client role has. One security-definer function returns both.
--
-- Officer-gated rather than admin-gated. The page itself is admin-only, but
-- the officers uploading to the gallery are the people who need to know how
-- much room is left, so the data stays available to them.
--
-- No audit_row trigger here — it's a read, and audit_log only tracks writes.
-- ============================================================

create or replace function public.storage_report()
returns jsonb
language plpgsql
stable
security definer
set search_path = public, storage, pg_catalog
as $$
declare
  report jsonb;
begin
  if not public.is_officer(auth.uid()) then
    raise exception 'storage_report: officers only' using errcode = '42501';
  end if;

  select jsonb_build_object(
    'generated_at', now(),
    'db_bytes',     pg_database_size(current_database()),
    'buckets',      coalesce(jsonb_agg(b order by b ->> 'bucket'), '[]'::jsonb)
  )
  into report
  from (
    -- left join so a bucket with nothing in it still reports as 0 rather
    -- than vanishing from the breakdown
    select jsonb_build_object(
             'bucket', bk.id,
             'public', bk.public,
             'files',  count(o.id),
             -- metadata is null on the placeholder rows storage writes for
             -- folders, so sum defensively instead of assuming a size key
             'bytes',  coalesce(sum((o.metadata ->> 'size')::bigint), 0)
           ) as b
    from storage.buckets bk
    left join storage.objects o on o.bucket_id = bk.id
    group by bk.id, bk.public
  ) s;

  return report;
end;
$$;

revoke execute on function public.storage_report() from public, anon;
grant execute on function public.storage_report() to authenticated;
