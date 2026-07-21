-- Academic info on profiles: free-text major(s)/minor(s) plus an
-- expected graduation term (no Winter — UMKC runs Spring/Summer/Fall).
alter table public.profiles add column majors text;
alter table public.profiles add column minors text;
alter table public.profiles add column grad_term text
  check (grad_term in ('Spring', 'Summer', 'Fall'));
alter table public.profiles add column grad_year smallint
  check (grad_year between 2000 and 2100);
