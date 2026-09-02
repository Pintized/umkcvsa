-- ============================================================
-- Cached Instagram feed for the public News page.
--
-- Instagram's Basic Display API was shut down on 4 Dec 2024, so the only
-- supported route is the Graph API with a long-lived token tied to a
-- Business/Creator account. The token is refreshed by the sync function
-- itself — they expire after 60 days and the feed would otherwise die
-- silently two months after setup.
--
-- The feed is CACHED, not fetched per page view: the Graph API is rate
-- limited, a live call would put the token within reach of the browser,
-- and the page would break whenever Instagram does.
-- ============================================================

create table public.instagram_posts (
  ig_id      text primary key,
  caption    text,
  permalink  text not null,
  media_type text,
  image_path text,              -- mirrored into site-images; IG's CDN URLs expire
  posted_at  timestamptz,
  fetched_at timestamptz not null default now()
);
create index instagram_posts_posted_idx on public.instagram_posts (posted_at desc);

alter table public.instagram_posts enable row level security;

-- The News page is public, so anon needs to read. Writes come only from the
-- sync function via the service role, which bypasses RLS — hence no insert,
-- update or delete policy for anyone.
create policy "instagram_posts: public read"
  on public.instagram_posts for select using (true);

-- ============================================================
-- Third-party tokens
--
-- RLS is enabled and NO policy is defined, so every client role — anon and
-- authenticated, admin included — is denied. Only the service role can read
-- or write it. An access token must never be reachable from the browser,
-- which is also why this can't live in the public-read site_settings table.
-- ============================================================

create table public.integration_tokens (
  provider     text primary key,
  access_token text not null,
  expires_at   timestamptz,
  updated_at   timestamptz not null default now()
);

alter table public.integration_tokens enable row level security;

grant select on public.instagram_posts to anon, authenticated;

-- Refresh hourly. The function no-ops quickly when no token is configured,
-- so this is safe to schedule before Instagram is set up.
create extension if not exists pg_cron;
create extension if not exists pg_net;

do $$
begin
  if exists (select 1 from cron.job where jobname = 'instagram-sync') then
    perform cron.unschedule('instagram-sync');
  end if;
end $$;

select cron.schedule(
  'instagram-sync',
  '7 * * * *',
  $job$
  select net.http_post(
    url     := 'https://wrlpsetbkeyoyamkopgf.supabase.co/functions/v1/instagram-sync',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6IndybHBzZXRia2V5b3lhbWtvcGdmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQyNTIwMDUsImV4cCI6MjA5OTgyODAwNX0.XOFn-PWtHD8IlMoamtaTRMo7RAAUkrqyTNoNl7o3qg8'
    ),
    body    := '{}'::jsonb
  );
  $job$
);
