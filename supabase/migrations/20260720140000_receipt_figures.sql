-- ============================================================
-- Planner receipts capture the paper's real numbers when uploaded:
-- subtotal, any number of tax lines, and the net total. Groups can
-- then show planned vs. what the register actually said.
-- ============================================================

alter table public.finance_planner_receipts
  add column subtotal  numeric(10, 2),
  add column taxes     jsonb not null default '[]',
  add column net_total numeric(10, 2);
