-- ============================================================
-- Notes upgrade: meeting-minutes features
--   * starred, meeting_date, tags, icon, color on notes
--   * icon, color on folders
--   * note_attendees join table (officer/intern roster per note)
--   * note-images storage bucket (random filenames; public-read
--     so rich-text <img> tags work without signed URLs — write
--     is officer-only)
-- ============================================================

alter table public.notes
  add column starred boolean not null default false,
  add column meeting_date date,
  add column tags text[] not null default '{}',
  add column icon text,
  add column color text;

alter table public.note_folders
  add column icon text,
  add column color text;

create table public.note_attendees (
  note_id bigint not null references public.notes (id) on delete cascade,
  user_id uuid not null references public.profiles (id) on delete cascade,
  primary key (note_id, user_id)
);

alter table public.note_attendees enable row level security;

create policy "note_attendees: officers all"
  on public.note_attendees for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

insert into storage.buckets (id, name, public)
values ('note-images', 'note-images', true)
on conflict (id) do nothing;

create policy "note-images: public read"
  on storage.objects for select
  using (bucket_id = 'note-images');

create policy "note-images: officers insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'note-images' and public.is_officer(auth.uid()));

create policy "note-images: officers update"
  on storage.objects for update to authenticated
  using (bucket_id = 'note-images' and public.is_officer(auth.uid()))
  with check (bucket_id = 'note-images' and public.is_officer(auth.uid()));

create policy "note-images: officers delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'note-images' and public.is_officer(auth.uid()));
