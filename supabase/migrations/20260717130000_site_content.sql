-- ============================================================
-- Site content managed from the portal Admin section:
--   eboard_members  -> public /eboard/ page
--   store_products  -> public /store/ page
-- Public read (anon renders the site), admin-only writes.
-- site-images bucket for member/product photos (separate from
-- the gallery bucket so they don't appear in the public gallery).
-- ============================================================

create table public.eboard_members (
  id         bigint generated always as identity primary key,
  full_name  text not null,
  role_title text not null,
  bio        text,
  photo_path text,
  is_lead    boolean not null default false,
  sort       integer not null default 0,
  active     boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.store_products (
  id          bigint generated always as identity primary key,
  name        text not null,
  description text,
  price       numeric(10, 2) not null default 0,
  badge       text,
  photo_path  text,
  sort        integer not null default 0,
  active      boolean not null default true,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

alter table public.eboard_members enable row level security;
alter table public.store_products enable row level security;

create policy "eboard: public read"
  on public.eboard_members for select using (true);
create policy "eboard: admins insert"
  on public.eboard_members for insert to authenticated
  with check (public.has_role(auth.uid(), 'admin'));
create policy "eboard: admins update"
  on public.eboard_members for update to authenticated
  using (public.has_role(auth.uid(), 'admin'))
  with check (public.has_role(auth.uid(), 'admin'));
create policy "eboard: admins delete"
  on public.eboard_members for delete to authenticated
  using (public.has_role(auth.uid(), 'admin'));

create policy "products: public read"
  on public.store_products for select using (true);
create policy "products: admins insert"
  on public.store_products for insert to authenticated
  with check (public.has_role(auth.uid(), 'admin'));
create policy "products: admins update"
  on public.store_products for update to authenticated
  using (public.has_role(auth.uid(), 'admin'))
  with check (public.has_role(auth.uid(), 'admin'));
create policy "products: admins delete"
  on public.store_products for delete to authenticated
  using (public.has_role(auth.uid(), 'admin'));

create trigger set_updated_at before update on public.eboard_members
  for each row execute function public.set_updated_at();
create trigger set_updated_at before update on public.store_products
  for each row execute function public.set_updated_at();

create trigger audit_row after insert or update or delete on public.eboard_members
  for each row execute function public.audit_row();
create trigger audit_row after insert or update or delete on public.store_products
  for each row execute function public.audit_row();

-- photos bucket
insert into storage.buckets (id, name, public)
values ('site-images', 'site-images', true)
on conflict (id) do nothing;

create policy "site-images: public read"
  on storage.objects for select
  using (bucket_id = 'site-images');
create policy "site-images: admins insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'site-images' and public.has_role(auth.uid(), 'admin'));
create policy "site-images: admins update"
  on storage.objects for update to authenticated
  using (bucket_id = 'site-images' and public.has_role(auth.uid(), 'admin'))
  with check (bucket_id = 'site-images' and public.has_role(auth.uid(), 'admin'));
create policy "site-images: admins delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'site-images' and public.has_role(auth.uid(), 'admin'));

-- seeds matching the current public pages
insert into public.eboard_members (full_name, role_title, bio, is_lead, sort) values
  ('Kalvin Tran', 'Co-President', 'Bio coming soon.', true, 0),
  ('Katie Nguyen', 'Co-President', 'Bio coming soon.', true, 1),
  ('First Last', 'Vice-President', null, false, 2),
  ('First Last', 'Treasurer', null, false, 3),
  ('First Last', 'Secretary', null, false, 4),
  ('First Last', 'Marketing Coordinator', null, false, 5),
  ('First Last', 'EXA', null, false, 6),
  ('First Last', 'EXA', null, false, 7);

insert into public.store_products (name, description, price, badge, sort) values
  ('Classic Logo Tee', 'Soft cotton tee featuring the UMKC VSA emblem. Available in multiple sizes.', 20, 'New', 0),
  ('Embroidered Hoodie', 'Cozy fleece hoodie with embroidered logo. Perfect for chilly campus days.', 40, null, 1),
  ('Tote Bag', 'Durable canvas tote for books, groceries, or event giveaways.', 15, 'Popular', 2),
  ('Sticker Pack', 'A set of vinyl stickers celebrating Vietnamese culture and VSA pride.', 8, null, 3),
  ('Beanie', 'Warm knit beanie with a stitched logo patch. One size fits most.', 18, null, 4),
  ('Water Bottle', 'Insulated stainless steel bottle to keep you hydrated on the go.', 22, 'Limited', 5);
