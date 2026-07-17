-- ============================================================
-- UMKC VSA - Seed data
-- The only rows worth carrying over from the legacy database
-- (everything else was June-2026 dev/test noise).
-- ============================================================

insert into public.achievements (name, description, points, active)
values ('First Event Attended', 'Awarded for attending your first UMKC VSA event.', 50, true);

insert into public.rewards (name, description, point_cost, active)
values ('VSA T-Shirt', 'Official UMKC VSA t-shirt in your size.', 150, true);
