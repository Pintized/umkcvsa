-- ============================================================
-- SAFC-A and SAFC-EE are one pot in practice — the school just
-- deposits the funding under two labels. Collapse them into a
-- single 'safc' account. Also: planner transaction codes are
-- numeric strings starting at '001'.
-- ============================================================

alter table public.finance_transactions
  drop constraint if exists finance_transactions_account_check;

update public.finance_transactions
  set account = 'safc'
  where account in ('safc-a', 'safc-ee');

alter table public.finance_transactions
  add constraint finance_transactions_account_check
  check (account in ('safc', 'sgr', 'cash'));

alter table public.finance_transactions
  alter column account set default 'cash';

alter table public.finance_planner_items
  alter column tx_code set default '001';
