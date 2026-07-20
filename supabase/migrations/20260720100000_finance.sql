-- ============================================================
-- Finance workspace (officer section):
--   * finance_transactions — the ledger (income/expense, receipts,
--     links to events / fundraisers / sponsorships / school requests)
--   * finance_dues          — per-member dues by term
--   * finance_fundraisers   — goal-tracked fundraisers
--   * finance_sponsorships  — pledged sponsor money (received is
--     derived from linked income transactions)
--   * finance_requests      — school/SGA funding requests
--     (requested vs awarded; received derived from linked income)
--   * finance_reimbursements— officer reimbursement workflow
--   * finance_budgets       — budget vs actual per category/event
--   * receipts bucket       — PRIVATE; officers only, signed URLs
-- All officer-writable (Kalvin's call), all audited.
-- ============================================================

create table public.finance_fundraisers (
  id         bigint generated always as identity primary key,
  name       text not null,
  goal       numeric(10, 2) not null default 0,
  term       text not null,
  active     boolean not null default true,
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.finance_sponsorships (
  id           bigint generated always as identity primary key,
  sponsor_name text not null,
  sponsor_id   bigint references public.sponsors (id) on delete set null,
  pledged      numeric(10, 2) not null default 0,
  term         text not null,
  notes        text,
  created_by   uuid references public.profiles (id) on delete set null,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);

create table public.finance_requests (
  id           bigint generated always as identity primary key,
  title        text not null,
  purpose      text,
  event_id     bigint references public.events (id) on delete set null,
  requested    numeric(10, 2) not null,
  awarded      numeric(10, 2),
  status       text not null default 'submitted'
               check (status in ('draft', 'submitted', 'approved', 'denied', 'received')),
  submitted_on date,
  decided_on   date,
  term         text not null,
  created_by   uuid references public.profiles (id) on delete set null,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);

create table public.finance_transactions (
  id             bigint generated always as identity primary key,
  kind           text not null check (kind in ('income', 'expense')),
  amount         numeric(10, 2) not null check (amount > 0),
  category       text not null default 'misc',
  description    text,
  method         text check (method in ('cash', 'venmo', 'zelle', 'card', 'check', 'other')),
  event_id       bigint references public.events (id) on delete set null,
  fundraiser_id  bigint references public.finance_fundraisers (id) on delete set null,
  sponsorship_id bigint references public.finance_sponsorships (id) on delete set null,
  request_id     bigint references public.finance_requests (id) on delete set null,
  receipt_path   text,
  occurred_on    date not null default current_date,
  term           text not null,
  created_by     uuid references public.profiles (id) on delete set null,
  created_at     timestamptz not null default now(),
  updated_at     timestamptz not null default now()
);
create index finance_transactions_term_idx on public.finance_transactions (term, occurred_on);
create index finance_transactions_event_idx on public.finance_transactions (event_id);

create table public.finance_dues (
  id             bigint generated always as identity primary key,
  user_id        uuid not null references public.profiles (id) on delete cascade,
  term           text not null,
  amount         numeric(10, 2) not null,
  method         text check (method in ('cash', 'venmo', 'zelle', 'card', 'check', 'other')),
  paid_on        date not null default current_date,
  transaction_id bigint references public.finance_transactions (id) on delete set null,
  created_by     uuid references public.profiles (id) on delete set null,
  created_at     timestamptz not null default now(),
  unique (user_id, term)
);

create table public.finance_reimbursements (
  id             bigint generated always as identity primary key,
  user_id        uuid not null references public.profiles (id) on delete cascade,
  amount         numeric(10, 2) not null check (amount > 0),
  reason         text not null,
  receipt_path   text,
  status         text not null default 'pending'
                 check (status in ('pending', 'approved', 'denied', 'paid')),
  reviewed_by    uuid references public.profiles (id) on delete set null,
  reviewed_at    timestamptz,
  transaction_id bigint references public.finance_transactions (id) on delete set null,
  term           text not null,
  created_at     timestamptz not null default now(),
  updated_at     timestamptz not null default now()
);

create table public.finance_budgets (
  id         bigint generated always as identity primary key,
  term       text not null,
  category   text,
  event_id   bigint references public.events (id) on delete cascade,
  amount     numeric(10, 2) not null,
  created_by uuid references public.profiles (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  check (category is not null or event_id is not null)
);

-- ---------- RLS: officers all ----------
do $$
declare t text;
begin
  foreach t in array array[
    'finance_transactions', 'finance_dues', 'finance_fundraisers',
    'finance_sponsorships', 'finance_requests', 'finance_reimbursements', 'finance_budgets'
  ]
  loop
    execute format('alter table public.%I enable row level security', t);
    execute format(
      'create policy "%s: officers all" on public.%I for all to authenticated
       using (public.is_officer(auth.uid())) with check (public.is_officer(auth.uid()))', t, t);
  end loop;
end;
$$;

-- ---------- updated_at + audit ----------
do $$
declare t text;
begin
  foreach t in array array[
    'finance_transactions', 'finance_fundraisers', 'finance_sponsorships',
    'finance_requests', 'finance_reimbursements', 'finance_budgets'
  ]
  loop
    execute format(
      'create trigger set_updated_at before update on public.%I
       for each row execute function public.set_updated_at()', t);
  end loop;
  foreach t in array array[
    'finance_transactions', 'finance_dues', 'finance_fundraisers',
    'finance_sponsorships', 'finance_requests', 'finance_reimbursements', 'finance_budgets'
  ]
  loop
    execute format(
      'create trigger audit_row after insert or update or delete on public.%I
       for each row execute function public.audit_row()', t);
  end loop;
end;
$$;

-- ---------- receipts: PRIVATE bucket, officers only ----------
insert into storage.buckets (id, name, public)
values ('receipts', 'receipts', false)
on conflict (id) do nothing;

create policy "receipts: officers read"
  on storage.objects for select to authenticated
  using (bucket_id = 'receipts' and public.is_officer(auth.uid()));

create policy "receipts: officers insert"
  on storage.objects for insert to authenticated
  with check (bucket_id = 'receipts' and public.is_officer(auth.uid()));

create policy "receipts: officers delete"
  on storage.objects for delete to authenticated
  using (bucket_id = 'receipts' and public.is_officer(auth.uid()));
