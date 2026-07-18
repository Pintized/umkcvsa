-- ============================================================
-- Event images: officers attach a photo to events; shown on the
-- public homepage and the member calendar.
-- ============================================================

alter table public.events add column photo_path text;

insert into storage.buckets (id, name, public)
values ('event-images', 'event-images', true)
on conflict (id) do nothing;

create policy "event-images: public read"
  on storage.objects for select
  using (bucket_id = 'event-images');

create policy "event-images: officers insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'event-images' and public.is_officer(auth.uid()));

create policy "event-images: officers update"
  on storage.objects for update to authenticated
  using (bucket_id = 'event-images' and public.is_officer(auth.uid()))
  with check (bucket_id = 'event-images' and public.is_officer(auth.uid()));

create policy "event-images: officers delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'event-images' and public.is_officer(auth.uid()));
