-- The portal moved to extensionless URLs (/app/rewards instead of
-- /app/rewards.html — GitHub Pages resolves both). page_settings paths
-- follow suit; guard.js normalizes incoming paths the same way.
update public.page_settings set path = regexp_replace(path, '\.html$', '');
