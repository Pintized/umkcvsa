-- ============================================================
-- Uploaded artwork for achievements and rewards.
--
-- Both were emoji-only: achievements carried an `icon` text column and
-- rewards had a hardcoded gift glyph in the markup. A reward is often a
-- real product (a t-shirt, a drink) and a photo sells it far better than
-- a pictogram.
--
-- The emoji stays as the fallback, so nothing looks blank until artwork
-- has been uploaded for every row.
-- ============================================================

alter table public.achievements add column image_path text;
alter table public.rewards      add column image_path text;

-- rewards had no glyph column at all — give it the same emoji fallback
-- achievements already has, so officers can set one without an upload
alter table public.rewards      add column icon text;

-- Public read so the images render for signed-out visitors too (the
-- public site may show rewards later); officers manage the contents.
-- Mirrors the inventory-images bucket from 20260719100000.
insert into storage.buckets (id, name, public)
values ('badge-images', 'badge-images', true)
on conflict (id) do nothing;

create policy "badge-images: public read"
  on storage.objects for select
  using (bucket_id = 'badge-images');

create policy "badge-images: officers insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'badge-images' and public.is_officer(auth.uid()));

create policy "badge-images: officers update"
  on storage.objects for update to authenticated
  using (bucket_id = 'badge-images' and public.is_officer(auth.uid()))
  with check (bucket_id = 'badge-images' and public.is_officer(auth.uid()));

create policy "badge-images: officers delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'badge-images' and public.is_officer(auth.uid()));
