-- ============================================================
-- School account tracking on the finance ledger:
--   SAFC-A  (SAFC Annual)          — school allocation
--   SAFC-EE (SAFC Event-by-Event)  — school allocation per event
--   SGR     (Student Generated Revenue)
--   Cash    (money held outside school accounts)
-- Overview shows SAFC (A+EE), SGR, and Cash balances separately.
-- ============================================================

alter table public.finance_transactions
  add column account text not null default 'cash'
  check (account in ('safc-a', 'safc-ee', 'sgr', 'cash'));
