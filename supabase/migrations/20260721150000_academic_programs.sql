-- Curated UMKC program list + enforcement. Majors/minors move from
-- free text to picks from this table (protects against junk/offensive
-- input at the DB level, not just the UI). Multiple selections are
-- stored '; '-separated because several program names contain commas.

create table public.academic_programs (
  name     text primary key,
  is_major boolean not null default false,
  is_minor boolean not null default false
);

alter table public.academic_programs enable row level security;
create policy "programs are readable" on public.academic_programs
  for select using (true);

insert into public.academic_programs (name, is_major, is_minor) values
  ('Accounting', true, false),
  ('Actuarial Science', false, true),
  ('Anthropology', false, true),
  ('Applied Linguistics', false, true),
  ('Applied Science', true, false),
  ('Architecture', true, false),
  ('Art History', true, true),
  ('Artificial Intelligence', false, true),
  ('Astronomy', false, true),
  ('Bioethics and Medical Humanities', false, true),
  ('Biology', true, true),
  ('Biomedical Engineering', true, false),
  ('Business Administration', true, true),
  ('Chemistry', true, true),
  ('Civil Engineering', true, false),
  ('Classical and Ancient Studies', false, true),
  ('Communication', true, true),
  ('Computer Science', true, true),
  ('Creative Writing', false, true),
  ('Criminal Justice and Criminology', true, true),
  ('Dance', true, true),
  ('Data Analytics', false, true),
  ('Dental Hygiene', true, false),
  ('Digital and Public Humanities', false, true),
  ('Early Childhood Education', true, false),
  ('Earth and Environmental Science', true, false),
  ('Economics', true, true),
  ('Education', false, true),
  ('Electrical and Computer Engineering', true, false),
  ('Elementary Education', true, false),
  ('English', true, false),
  ('English Language and Literature', false, true),
  ('Environmental Communications', false, true),
  ('Environmental Studies', true, true),
  ('Environmental Sustainability', false, true),
  ('Exercise Science', false, true),
  ('Film and Media Arts', true, false),
  ('Film Studies', false, true),
  ('French', false, true),
  ('Geography', false, true),
  ('Geology', false, true),
  ('Geospatial Science', false, true),
  ('German Studies', false, true),
  ('Health Sciences', true, true),
  ('History', true, true),
  ('Information Technology', true, false),
  ('International Studies', false, true),
  ('Jazz Studies', true, false),
  ('Languages and Literatures', true, false),
  ('Liberal Arts', true, false),
  ('Manuscript, Print Culture, and Editing', false, true),
  ('Material Science and Engineering', false, true),
  ('Mathematics', false, true),
  ('Mathematics and Statistics', true, false),
  ('Mechanical Engineering', true, false),
  ('Media, Art and Design', true, true),
  ('Medieval and Early Modern Studies', false, true),
  ('Music', true, true),
  ('Music Composition', true, false),
  ('Music Education', true, false),
  ('Music Performance', true, false),
  ('Music Theory', true, false),
  ('Music Therapy', true, false),
  ('Nursing', true, false),
  ('Philosophy', true, true),
  ('Physics', true, true),
  ('Political Science', true, true),
  ('Professional Communication', false, true),
  ('Psychology', true, true),
  ('Public Health', false, true),
  ('Race, Ethnic, and Gender Studies', false, true),
  ('Sociology', true, true),
  ('Spanish', false, true),
  ('Statistics', false, true),
  ('Studio Art', true, true),
  ('Sustainable Energy Technologies', false, true),
  ('Theatre', true, true),
  ('Undecided', true, false),
  ('Urban Planning + Design', true, false),
  ('Urban Studies', false, true),
  ('Writing', false, true);

-- reject anything not on the list (values are '; '-separated)
create function public.validate_academics()
returns trigger
language plpgsql security definer set search_path = public
as $$
begin
  if new.majors is not null and exists (
    select 1 from unnest(string_to_array(new.majors, '; ')) v
    where not exists (select 1 from public.academic_programs p where p.name = v and p.is_major)
  ) then
    raise exception 'majors must be chosen from the program list';
  end if;
  if new.minors is not null and exists (
    select 1 from unnest(string_to_array(new.minors, '; ')) v
    where not exists (select 1 from public.academic_programs p where p.name = v and p.is_minor)
  ) then
    raise exception 'minors must be chosen from the program list';
  end if;
  return new;
end;
$$;

create trigger validate_academics
  before insert or update on public.profiles
  for each row execute function public.validate_academics();
