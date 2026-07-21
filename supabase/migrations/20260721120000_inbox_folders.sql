-- Inbox folders: Inbox / Starred / Sent / Drafts / Scheduled / Spam / Trash.
-- Trash auto-purges after 30 days; scheduled mail is dispatched by pg_cron
-- via the inbox-scheduled edge function.

alter table public.inbox_emails
  add column folder text not null default 'inbox'
    check (folder in ('inbox', 'sent', 'draft', 'scheduled', 'spam', 'trash')),
  add column starred boolean not null default false,
  add column trashed_at timestamptz,
  add column scheduled_at timestamptz;

update public.inbox_emails set folder = 'sent' where direction = 'out';
update public.inbox_emails set folder = 'trash', trashed_at = now() where archived;
alter table public.inbox_emails drop column archived;

create index inbox_emails_folder_idx on public.inbox_emails (folder, received_at desc);

-- Officers may create drafts directly from the portal...
create policy "officers insert drafts" on public.inbox_emails
  for insert with check (
    public.is_officer(auth.uid())
    and folder = 'draft'
    and direction = 'out'
    and sent_by = auth.uid()
  );

-- ...and hard-delete drafts or trashed mail ("delete forever").
create policy "officers delete drafts and trash" on public.inbox_emails
  for delete using (
    public.is_officer(auth.uid()) and folder in ('draft', 'trash')
  );

grant insert, delete on public.inbox_emails to authenticated;

-- Purge trash older than 30 days, daily at 08:30 UTC
do $$
begin
  if exists (select 1 from cron.job where jobname = 'inbox-purge-trash') then
    perform cron.unschedule('inbox-purge-trash');
  end if;
end $$;

select cron.schedule(
  'inbox-purge-trash',
  '30 8 * * *',
  $job$ delete from public.inbox_emails where folder = 'trash' and trashed_at < now() - interval '30 days'; $job$
);

-- Dispatch due scheduled mail every 5 minutes (only calls out when something is due)
do $$
begin
  if exists (select 1 from cron.job where jobname = 'inbox-send-scheduled') then
    perform cron.unschedule('inbox-send-scheduled');
  end if;
end $$;

select cron.schedule(
  'inbox-send-scheduled',
  '*/5 * * * *',
  $job$
  select net.http_post(
    url     := 'https://wrlpsetbkeyoyamkopgf.supabase.co/functions/v1/inbox-scheduled',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6IndybHBzZXRia2V5b3lhbWtvcGdmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQyNTIwMDUsImV4cCI6MjA5OTgyODAwNX0.XOFn-PWtHD8IlMoamtaTRMo7RAAUkrqyTNoNl7o3qg8'
    ),
    body    := '{}'::jsonb
  )
  where exists (
    select 1 from public.inbox_emails
    where folder = 'scheduled' and scheduled_at <= now()
  );
  $job$
);
