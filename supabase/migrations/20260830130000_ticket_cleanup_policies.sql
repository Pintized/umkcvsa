-- ============================================================
-- Two gaps in 20260830120000_support_tickets:
--
--  * no delete policy on tickets, so spam or mistaken tickets could
--    never be removed by anyone;
--  * no delete policy on notifications, so a member could mark one read
--    but never dismiss it, and rows would accumulate forever.
--
-- Officers can delete a ticket (cascades to its messages) and the audit
-- trigger records who did it. Members dismiss only their own
-- notifications.
-- ============================================================

create policy "tickets: officers delete"
  on public.tickets for delete to authenticated
  using (public.is_officer(auth.uid()));

create policy "notifications: dismiss own"
  on public.notifications for delete to authenticated
  using (user_id = auth.uid());

grant delete on public.tickets       to authenticated;
grant delete on public.notifications to authenticated;
