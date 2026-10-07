-- ============================================================
-- Interns get the Notes page — and only the Notes page.
--
-- `intern` has existed in the user_role enum since the first migration
-- but was never referenced by a single policy, so it granted nothing.
-- This gives it full access to notes (read, write, delete, attendees,
-- images), matching what officers can do there, and nothing else.
--
-- Deliberately NOT done by adding 'intern' to public.is_officer(): that
-- function backs 115 policies, so one word there would hand interns
-- Finance, the Inbox and the member directory too.
-- ============================================================

create or replace function public.can_edit_notes(uid uuid)
returns boolean
language sql stable security definer set search_path = public
as $$
  select exists (
    select 1 from public.user_roles ur
    where ur.user_id = uid and ur.role in ('officer', 'admin', 'intern')
  );
$$;

revoke execute on function public.can_edit_notes(uuid) from public, anon;
grant execute on function public.can_edit_notes(uuid) to authenticated;

-- ---------- the three notes tables ----------
-- ALTER rather than DROP + CREATE so there is never an instant where the
-- table sits with RLS on and no policy, which would deny everyone.

alter policy "notes: officers all" on public.notes
  using (public.can_edit_notes(auth.uid()))
  with check (public.can_edit_notes(auth.uid()));
alter policy "notes: officers all" on public.notes
  rename to "notes: officers and interns all";

alter policy "note_folders: officers all" on public.note_folders
  using (public.can_edit_notes(auth.uid()))
  with check (public.can_edit_notes(auth.uid()));
alter policy "note_folders: officers all" on public.note_folders
  rename to "note_folders: officers and interns all";

alter policy "note_attendees: officers all" on public.note_attendees
  using (public.can_edit_notes(auth.uid()))
  with check (public.can_edit_notes(auth.uid()));
alter policy "note_attendees: officers all" on public.note_attendees
  rename to "note_attendees: officers and interns all";

-- ---------- images pasted into a note ----------
-- read is already public; only the write side is gated.

-- These keep their "officers" names: storage.objects belongs to the storage
-- role, so a migration may change a policy's expression there but not rename
-- it (ALTER POLICY ... RENAME wants table ownership). The names are stale;
-- the grants below are what actually apply.
alter policy "note-images: officers insert" on storage.objects
  with check (bucket_id = 'note-images' and public.can_edit_notes(auth.uid()));

alter policy "note-images: officers update" on storage.objects
  using (bucket_id = 'note-images' and public.can_edit_notes(auth.uid()))
  with check (bucket_id = 'note-images' and public.can_edit_notes(auth.uid()));

alter policy "note-images: officers delete" on storage.objects
  using (bucket_id = 'note-images' and public.can_edit_notes(auth.uid()));

-- ---------- the attendee picker needs a roster ----------
-- "Officers & interns present at this meeting" is built by reading
-- user_roles, but that policy is read-own-or-officer, so an intern would
-- have seen a roster containing only themselves.
--
-- Widened by the narrowest amount that makes the picker work: an intern
-- may read rows whose role is officer or intern. Who holds admin, and who
-- is alumni, stays invisible to them — ordinary members can't see any
-- role assignments at all and that doesn't change.
alter policy "user_roles: read own, officers read all" on public.user_roles
  using (
    user_id = auth.uid()
    or public.is_officer(auth.uid())
    or (public.can_edit_notes(auth.uid()) and role in ('officer', 'intern'))
  );
