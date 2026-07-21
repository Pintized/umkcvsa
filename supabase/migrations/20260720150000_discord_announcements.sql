-- Daily Discord event announcements.
-- pg_cron invokes the discord-announce edge function once a day at 15:00 UTC
-- (10 AM CDT / 9 AM CST); the function posts today's and tomorrow's events to
-- the announcements channel and does nothing when there are none.
-- The Authorization header uses the project's anon key, which is public by
-- design (it ships in the website frontend); the function only reads events
-- and posts to a fixed Discord channel.

create extension if not exists pg_cron;
create extension if not exists pg_net;

do $$
begin
  if exists (select 1 from cron.job where jobname = 'discord-event-announcements') then
    perform cron.unschedule('discord-event-announcements');
  end if;
end $$;

select cron.schedule(
  'discord-event-announcements',
  '0 15 * * *',
  $job$
  select net.http_post(
    url     := 'https://wrlpsetbkeyoyamkopgf.supabase.co/functions/v1/discord-announce',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6IndybHBzZXRia2V5b3lhbWtvcGdmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQyNTIwMDUsImV4cCI6MjA5OTgyODAwNX0.XOFn-PWtHD8IlMoamtaTRMo7RAAUkrqyTNoNl7o3qg8'
    ),
    body    := '{}'::jsonb
  );
  $job$
);
