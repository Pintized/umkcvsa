-- Public bucket for images embedded in outgoing inbox emails.
-- Public read is required so recipients' mail clients can load the images;
-- only officers can upload.

insert into storage.buckets (id, name, public)
values ('email-assets', 'email-assets', true)
on conflict (id) do nothing;

create policy "email-assets: public read"
  on storage.objects for select
  using (bucket_id = 'email-assets');

create policy "email-assets: officers insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'email-assets' and public.is_officer(auth.uid()));

create policy "email-assets: officers delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'email-assets' and public.is_officer(auth.uid()));
