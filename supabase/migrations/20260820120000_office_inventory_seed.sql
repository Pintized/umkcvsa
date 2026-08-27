-- Office inventory seed — imported from the club's "Inventory - Office Inventory"
-- spreadsheet (last counted 04/28). The inventory table was empty before this.
--
-- Quantity is an integer, but the sheet mixes counts, approximations ("~900"),
-- units ("4 rolls") and uncounted piles ("a lot", "N/A"). Mapping used:
--   * "~n"          -> n, note "Approximate count."
--   * "n rolls"     -> quantity n + unit "rolls"
--   * "a lot"/"N/A" -> quantity 0 + a note; these land under the page's
--     Low-stock chip on purpose, so it doubles as the to-count list the sheet
--     itself asks for ("get accurate numbers").
-- Box color becomes the category, so the filter chips work as box filters.

-- The audit trigger would otherwise write ~60 actor-less rows for this import.
alter table public.inventory     disable trigger audit_row;
alter table public.inventory_log disable trigger audit_row;

insert into public.inventory (name, category, quantity, unit, location, low_stock_threshold, notes)
select v.name, v.category, v.quantity, v.unit, v.location, v.low_stock_threshold, v.notes
from (values
  -- Cardboard box
  ('Food warmers'::text,           'Cardboard box'::text, 12::integer, ''::text, 'Office'::text, 0::integer, null::text),
  ('Red rectangle table cloths',   'Cardboard box',   5, '',      'Office', 0, null),
  ('White rectangle table cloths', 'Cardboard box',   5, '',      'Office', 0, null),
  ('Gold round table cloths',      'Cardboard box',  17, '',      'Office', 0, null),
  ('Red double strand tickets',    'Cardboard box',   4, 'rolls', 'Office', 0, null),
  ('Red single strand tickets',    'Cardboard box',   1, 'roll',  'Office', 0, null),
  ('Green single strand tickets',  'Cardboard box',   1, 'roll',  'Office', 1, 'Roughly half a roll left.'),
  ('White single strand tickets',  'Cardboard box',   2, 'rolls', 'Office', 0, null),

  -- Purple box
  ('Plastic forks',                'Purple box',    900, '',      'Office', 0, 'Approximate count.'),
  ('Plastic spoons',               'Purple box',    250, '',      'Office', 0, 'Approximate count.'),
  ('Clear plastic straws',         'Purple box',    100, '',      'Office', 0, 'Approximate count.'),
  ('Black plastic straws',         'Purple box',    100, '',      'Office', 0, 'Approximate count.'),
  ('Chopsticks',                   'Purple box',    250, '',      'Office', 0, 'Approximate count.'),
  ('Plastic wrap',                 'Purple box',      1, 'boxes', 'Office', 0, 'About one and a half boxes left.'),
  ('Disposable gloves',            'Purple box',    150, 'pairs', 'Office', 0, 'Approximate count.'),
  ('Disposable serving spoons',    'Purple box',      8, '',      'Office', 0, null),
  ('Metal serving spoons',         'Purple box',      3, '',      'Office', 0, null),
  ('Disposable tongs',             'Purple box',     10, '',      'Office', 0, null),
  ('Plastic ice scoop',            'Purple box',      1, '',      'Office', 0, null),
  ('Large aluminium tray lids',    'Purple box',      3, '',      'Office', 0, null),
  ('Aluminium tray holders',       'Purple box',      6, '',      'Office', 0, null),
  ('Sauce cups + lids',            'Purple box',     25, '',      'Office', 0, 'Approximate count.'),

  -- Blue box
  ('Red square paper plates',      'Blue box',        0, '',      'Office', 0, 'Plenty on hand — exact count needed.'),
  ('White foam plates',            'Blue box',      250, '',      'Office', 0, 'Approximate count.'),
  ('Paper plates',                 'Blue box',        0, '',      'Office', 0, 'Plenty on hand — exact count needed.'),
  ('Small paper bowls',            'Blue box',        0, '',      'Office', 0, 'Plenty on hand — exact count needed.'),
  ('Large paper bowls',            'Blue box',        0, '',      'Office', 0, 'Plenty on hand — exact count needed.'),
  ('Clear lids',                   'Blue box',      100, '',      'Office', 0, 'Approximate count.'),
  ('Clear plastic cups',           'Blue box',      100, '',      'Office', 0, 'Approximate count.'),
  ('White plastic cups',           'Blue box',        0, '',      'Office', 0, 'Plenty on hand — exact count needed.'),
  ('Small white plastic cups',     'Blue box',        0, '',      'Office', 0, 'Plenty on hand — exact count needed.'),

  -- Red box
  ('Decor',                        'Red box',         0, '',      'Office', 0, 'Whole box — contents not itemized or counted yet.'),

  -- Green box
  ('Game supplies',                'Green box',       0, '',      'Office', 0, 'Whole box — contents not itemized or counted yet.'),
  ('Craft supplies',               'Green box',       0, '',      'Office', 0, 'Whole box — contents not itemized or counted yet.'),
  ('Prizes',                       'Green box',       0, '',      'Office', 0, 'Whole box — contents not itemized or counted yet.'),

  -- Not in a box
  ('8 oz water bottles',           'Not in a box',   80, '',      'Office', 0, null),
  ('Napkins',                      'Not in a box',    0, '',      'Office', 0, 'Plenty on hand — exact count needed.')
) as v(name, category, quantity, unit, location, low_stock_threshold, notes)
where not exists (
  select 1 from public.inventory i where lower(i.name) = lower(v.name)
);

-- Baseline entry so per-item History has a starting point.
insert into public.inventory_log (item_id, user_id, delta, new_quantity, reason)
select i.id, null, i.quantity, i.quantity,
       'Imported from the Office Inventory sheet (last counted 04/28)'
from public.inventory i
where i.quantity > 0
  and not exists (select 1 from public.inventory_log l where l.item_id = i.id);

alter table public.inventory     enable trigger audit_row;
alter table public.inventory_log enable trigger audit_row;
