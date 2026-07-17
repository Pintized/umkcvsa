-- ============================================================
-- UMKC VSA - Storage buckets
--   avatars   : public-read, users manage files under their own
--               folder (avatars/<uid>/...)
--   documents : officer-only (internal club files)
-- ============================================================

insert into storage.buckets (id, name, public)
values ('avatars', 'avatars', true)
on conflict (id) do nothing;

insert into storage.buckets (id, name, public)
values ('documents', 'documents', false)
on conflict (id) do nothing;

create policy "avatars: public read"
  on storage.objects for select
  using (bucket_id = 'avatars');

create policy "avatars: users insert own folder"
  on storage.objects for insert to authenticated
  with check (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "avatars: users update own folder"
  on storage.objects for update to authenticated
  using (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  )
  with check (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "avatars: users delete own folder"
  on storage.objects for delete to authenticated
  using (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "documents bucket: officers all"
  on storage.objects for all to authenticated
  using (bucket_id = 'documents' and public.is_officer(auth.uid()))
  with check (bucket_id = 'documents' and public.is_officer(auth.uid()));
