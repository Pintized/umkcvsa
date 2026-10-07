-- ============================================================
-- Hourly backup of the Notes page into a private bucket.
--
-- Deleting a note is permanent, and since interns were given write access
-- (20261007120000) more people can do it. The edge function dumps every
-- note, folder and attendee row to one JSON file; this schedules it and
-- creates the bucket it writes to.
--
-- The bucket is PRIVATE. Notes hold officer meeting minutes, so unlike
-- note-images there is no public read here — and no insert/update/delete
-- policy at all, which leaves writing to the service role the function
-- runs as.
-- ============================================================

insert into storage.buckets (id, name, public)
values ('note-snapshots', 'note-snapshots', false)
on conflict (id) do nothing;

-- Officers only, read only. Interns are deliberately excluded even though
-- they can read live notes: a snapshot also contains notes that have since
-- been deleted, and recovering those is an officer decision.
create policy "note-snapshots: officers read"
  on storage.objects for select to authenticated
  using (bucket_id = 'note-snapshots' and public.is_officer(auth.uid()));

-- ---------- schedule ----------
-- Hourly on the half hour, away from instagram-sync at :07. The function
-- hashes its payload and skips writing when nothing changed, so an idle
-- week costs 168 no-op invocations and zero files.
do $$
begin
  if exists (select 1 from cron.job where jobname = 'notes-snapshot') then
    perform cron.unschedule('notes-snapshot');
  end if;
end $$;

select cron.schedule(
  'notes-snapshot',
  '30 * * * *',
  $job$
  select net.http_post(
    url     := 'https://wrlpsetbkeyoyamkopgf.supabase.co/functions/v1/notes-snapshot',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6IndybHBzZXRia2V5b3lhbWtvcGdmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQyNTIwMDUsImV4cCI6MjA5OTgyODAwNX0.XOFn-PWtHD8IlMoamtaTRMo7RAAUkrqyTNoNl7o3qg8'
    ),
    body    := '{}'::jsonb
  );
  $job$
);
