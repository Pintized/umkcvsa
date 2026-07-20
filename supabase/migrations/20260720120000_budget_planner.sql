-- ============================================================
-- Budget Planner (replaces the Dues + Budgets tabs in Finance):
-- per-event purchase planning. Items are grouped by a transaction
-- code — one code per store receipt — and receipts are uploaded
-- against that code so paper matches the sheet. Tax only applies
-- to VSA Personal purchases (school accounts are tax-exempt).
-- ============================================================

create table public.finance_planner_items (
  id         bigint generated always as identity primary key,
  event_id   bigint not null references public.events (id) on delete cascade,
  account    text not null default 'safc' check (account in ('safc', 'sgr', 'personal')),
  tx_code    text not null default 'T1',
  store_name text not null default '',
  purchased  boolean not null default false,
  item_name  text not null,
  quantity   numeric(10, 2) not null default 1 check (quantity > 0),
  unit_price numeric(10, 2) not null default 0,
  tax        numeric(10, 2) not null default 0,
  item_type  text not null default 'supply' check (item_type in ('food', 'supply')),
  url        text,
  term       text not null,
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index finance_planner_items_event_idx on public.finance_planner_items (event_id, tx_code);

create table public.finance_planner_receipts (
  id           bigint generated always as identity primary key,
  event_id     bigint not null references public.events (id) on delete cascade,
  tx_code      text not null,
  receipt_path text not null,
  created_by   uuid references public.profiles (id) on delete set null,
  created_at   timestamptz not null default now()
);
create index finance_planner_receipts_event_idx on public.finance_planner_receipts (event_id, tx_code);

do $$
declare t text;
begin
  foreach t in array array['finance_planner_items', 'finance_planner_receipts']
  loop
    execute format('alter table public.%I enable row level security', t);
    execute format(
      'create policy "%s: officers all" on public.%I for all to authenticated
       using (public.is_officer(auth.uid())) with check (public.is_officer(auth.uid()))', t, t);
    execute format(
      'create trigger audit_row after insert or update or delete on public.%I
       for each row execute function public.audit_row()', t);
  end loop;
end;
$$;

create trigger set_updated_at before update on public.finance_planner_items
  for each row execute function public.set_updated_at();
