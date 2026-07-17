-- ============================================================
-- Public gallery bucket: officers upload via the portal,
-- the public site displays. Public-read, officer-write.
-- ============================================================

insert into storage.buckets (id, name, public)
values ('gallery', 'gallery', true)
on conflict (id) do nothing;

create policy "gallery: public read"
  on storage.objects for select
  using (bucket_id = 'gallery');

create policy "gallery: officers insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'gallery' and public.is_officer(auth.uid()));

create policy "gallery: officers update"
  on storage.objects for update to authenticated
  using (bucket_id = 'gallery' and public.is_officer(auth.uid()))
  with check (bucket_id = 'gallery' and public.is_officer(auth.uid()));

create policy "gallery: officers delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'gallery' and public.is_officer(auth.uid()));
