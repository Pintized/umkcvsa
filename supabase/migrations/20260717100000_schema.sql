-- ============================================================
-- UMKC VSA - Core schema
-- Rebuilt from the legacy MySQL dump (legacy/dreamhost-dump/),
-- adapted to Postgres + Supabase Auth conventions:
--   * app_users -> profiles keyed by auth.users(id) uuid
--   * MySQL SET role column -> user_roles join table
--   * rsvps now FK events(id) instead of name+date strings
--   * app_login_attempts dropped (Supabase Auth handles it)
-- ============================================================

-- ---------- enums ----------
create type public.user_role as enum ('member', 'officer', 'alumni', 'intern', 'admin');
create type public.rsvp_status as enum ('going', 'maybe', 'cancelled');
create type public.payment_status as enum ('pending', 'paid', 'failed', 'refunded');
create type public.payment_method as enum ('stripe', 'cash', 'zelle', 'venmo');

-- ---------- profiles & roles ----------
create table public.profiles (
  id         uuid primary key references auth.users (id) on delete cascade,
  first_name text not null default '',
  last_name  text not null default '',
  full_name  text not null default '',
  avatar_path text,
  points     integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.user_roles (
  user_id uuid not null references public.profiles (id) on delete cascade,
  role    public.user_role not null,
  primary key (user_id, role)
);

-- Role helpers (security definer so RLS policies can consult
-- user_roles without recursive policy evaluation)
create function public.has_role(uid uuid, r public.user_role)
returns boolean
language sql stable security definer set search_path = public
as $$
  select exists (select 1 from public.user_roles ur where ur.user_id = uid and ur.role = r);
$$;

create function public.is_officer(uid uuid)
returns boolean
language sql stable security definer set search_path = public
as $$
  select exists (select 1 from public.user_roles ur where ur.user_id = uid and ur.role in ('officer', 'admin'));
$$;

-- ---------- events & rsvps ----------
create table public.events (
  id          bigint generated always as identity primary key,
  name        text not null,
  event_date  date not null,
  start_time  time,
  end_time    time,
  location    text,
  description text,
  created_by  uuid references public.profiles (id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index events_event_date_idx on public.events (event_date);

create table public.rsvps (
  id         bigint generated always as identity primary key,
  user_id    uuid not null references public.profiles (id) on delete cascade,
  event_id   bigint not null references public.events (id) on delete cascade,
  status     public.rsvp_status not null default 'going',
  created_at timestamptz not null default now(),
  unique (user_id, event_id)
);

-- ---------- officer task board ----------
create table public.tasks (
  id          bigint generated always as identity primary key,
  title       text not null,
  description text,
  due_date    date,
  status      text not null default 'open',
  priority    text not null default 'medium' check (priority in ('low', 'medium', 'high')),
  pos_x       integer not null default 40,
  pos_y       integer not null default 40,
  created_by  uuid references public.profiles (id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create table public.task_assignees (
  task_id bigint not null references public.tasks (id) on delete cascade,
  user_id uuid not null references public.profiles (id) on delete cascade,
  primary key (task_id, user_id)
);

create table public.task_edges (
  id         bigint generated always as identity primary key,
  from_id    bigint not null references public.tasks (id) on delete cascade,
  to_id      bigint not null references public.tasks (id) on delete cascade,
  created_at timestamptz not null default now(),
  unique (from_id, to_id)
);

-- ---------- officer notes ----------
create table public.note_folders (
  id         bigint generated always as identity primary key,
  name       text not null,
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.notes (
  id         bigint generated always as identity primary key,
  folder_id  bigint references public.note_folders (id) on delete cascade,
  title      text not null default 'Untitled note',
  content    text,
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index notes_folder_idx on public.notes (folder_id);

-- ---------- officer documents ----------
create table public.document_folders (
  id         bigint generated always as identity primary key,
  parent_id  bigint references public.document_folders (id) on delete cascade,
  name       text not null,
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index document_folders_parent_idx on public.document_folders (parent_id);

create table public.documents (
  id           bigint generated always as identity primary key,
  folder_id    bigint not null references public.document_folders (id) on delete cascade,
  title        text not null,
  content_html text not null default '',
  created_by   uuid references public.profiles (id) on delete set null,
  updated_by   uuid references public.profiles (id) on delete set null,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index documents_folder_updated_idx on public.documents (folder_id, updated_at);

-- ---------- inventory ----------
create table public.inventory (
  id                  bigint generated always as identity primary key,
  name                text not null,
  category            text not null default '',
  quantity            integer not null default 0,
  unit                text not null default '',
  location            text not null default '',
  low_stock_threshold integer not null default 0,
  notes               text,
  created_by          uuid references public.profiles (id) on delete set null,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

-- ---------- points economy ----------
create table public.rewards (
  id          bigint generated always as identity primary key,
  name        text not null,
  description text,
  point_cost  integer not null default 0,
  active      boolean not null default true,
  created_by  uuid references public.profiles (id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create table public.achievements (
  id          bigint generated always as identity primary key,
  name        text not null,
  description text,
  points      integer not null default 0,
  icon        text,
  active      boolean not null default true,
  created_by  uuid references public.profiles (id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create table public.achievement_awards (
  id             bigint generated always as identity primary key,
  achievement_id bigint not null references public.achievements (id) on delete cascade,
  user_id        uuid not null references public.profiles (id) on delete cascade,
  awarded_by     uuid references public.profiles (id) on delete set null,
  points_awarded integer not null default 0,
  created_at     timestamptz not null default now()
);
create index achievement_awards_user_idx on public.achievement_awards (user_id);

-- ---------- store ----------
create table public.orders (
  id                bigint generated always as identity primary key,
  user_id           uuid not null references public.profiles (id) on delete cascade,
  total             numeric(10, 2) not null default 0,
  payment_status    public.payment_status not null default 'pending',
  payment_method    public.payment_method,
  stripe_session_id text,
  marked_paid_by    uuid references public.profiles (id) on delete set null,
  marked_paid_at    timestamptz,
  created_at        timestamptz not null default now()
);
create index orders_user_idx on public.orders (user_id);

create table public.order_items (
  id        bigint generated always as identity primary key,
  order_id  bigint not null references public.orders (id) on delete cascade,
  item_name text not null,
  qty       integer not null default 1,
  price     numeric(10, 2) not null default 0
);
create index order_items_order_idx on public.order_items (order_id);

create table public.cart_items (
  id        bigint generated always as identity primary key,
  user_id   uuid not null references public.profiles (id) on delete cascade,
  item_name text not null,
  qty       integer not null default 1,
  price     numeric(10, 2) not null default 0,
  added_at  timestamptz not null default now()
);
create index cart_items_user_idx on public.cart_items (user_id);

-- ---------- audit log (written by triggers only) ----------
create table public.audit_log (
  id         bigint generated always as identity primary key,
  user_id    uuid,
  user_email text,
  action     text not null,
  entity     text not null,
  details    text,
  created_at timestamptz not null default now()
);
create index audit_log_created_idx on public.audit_log (created_at);
create index audit_log_entity_idx on public.audit_log (entity);
