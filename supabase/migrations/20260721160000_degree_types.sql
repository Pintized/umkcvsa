-- Per-program degree types (BA/BS/BBA/BM/...) so a major can be saved
-- as e.g. "Computer Science (BS)". Programs offered under two degrees
-- (Biology BA+BS, ...) list both; the UI asks which one. The trigger
-- validates the "Name (TYPE)" format against this column — a bare
-- name stays legal (pre-existing rows, Undecided).

alter table public.academic_programs add column degree_types text[] not null default '{}';

update public.academic_programs set degree_types = '{BA,BS}'
  where name in ('Biology', 'Chemistry', 'Computer Science', 'Mathematics and Statistics', 'Physics');
update public.academic_programs set degree_types = '{BS}'
  where name in ('Accounting', 'Applied Science', 'Biomedical Engineering', 'Civil Engineering',
                 'Dental Hygiene', 'Earth and Environmental Science', 'Electrical and Computer Engineering',
                 'Mechanical Engineering');
update public.academic_programs set degree_types = '{BA}'
  where name in ('Art History', 'Communication', 'Criminal Justice and Criminology', 'Early Childhood Education',
                 'Economics', 'Elementary Education', 'English', 'Environmental Studies', 'Film and Media Arts',
                 'History', 'Languages and Literatures', 'Media, Art and Design', 'Music', 'Music Therapy',
                 'Philosophy', 'Political Science', 'Psychology', 'Sociology', 'Studio Art', 'Theatre',
                 'Urban Planning + Design');
update public.academic_programs set degree_types = '{BBA}'   where name = 'Business Administration';
update public.academic_programs set degree_types = '{BFA}'   where name = 'Dance';
update public.academic_programs set degree_types = '{BHS}'   where name = 'Health Sciences';
update public.academic_programs set degree_types = '{BIT}'   where name = 'Information Technology';
update public.academic_programs set degree_types = '{BLA}'   where name = 'Liberal Arts';
update public.academic_programs set degree_types = '{BM}'    where name in ('Jazz Studies', 'Music Composition', 'Music Performance', 'Music Theory');
update public.academic_programs set degree_types = '{BME}'   where name = 'Music Education';
update public.academic_programs set degree_types = '{BSN}'   where name = 'Nursing';
update public.academic_programs set degree_types = '{BArch}' where name = 'Architecture';

create or replace function public.validate_academics()
returns trigger
language plpgsql security definer set search_path = public
as $$
declare
  v text;
  base text;
  typ text;
begin
  if new.majors is not null then
    foreach v in array string_to_array(new.majors, '; ') loop
      base := regexp_replace(v, ' \([A-Za-z]+\)$', '');
      typ  := (regexp_match(v, '\(([A-Za-z]+)\)$'))[1];
      if not exists (
        select 1 from public.academic_programs p
        where p.name = base and p.is_major
          and (typ is null or typ = any(p.degree_types))
      ) then
        raise exception 'majors must be chosen from the program list';
      end if;
    end loop;
  end if;
  if new.minors is not null and exists (
    select 1 from unnest(string_to_array(new.minors, '; ')) m
    where not exists (select 1 from public.academic_programs p where p.name = m and p.is_minor)
  ) then
    raise exception 'minors must be chosen from the program list';
  end if;
  return new;
end;
$$;
