-- Two additions:
--  1. grant_points_many() — one call grants VND to a whole list of members
--     (event attendance), reusing grant_points() so each member still gets
--     their own audit entry.
--  2. Forms — Google-Forms-style surveys built by officers. Each form has
--     an unguessable uuid link that can be shared publicly. The public
--     page reads and submits through security-definer RPCs only, so
--     anonymous visitors can open a form by exact link but can never list
--     or enumerate forms.

-- ---------- bulk VND grant ----------
create function public.grant_points_many(targets uuid[], delta integer, reason text default null)
returns integer
language plpgsql security definer set search_path = public
as $$
declare
  t uuid;
  n integer := 0;
begin
  if not public.is_officer(auth.uid()) then
    raise exception 'officers only';
  end if;
  foreach t in array targets loop
    perform public.grant_points(t, delta, reason);
    n := n + 1;
  end loop;
  return n;
end;
$$;
revoke execute on function public.grant_points_many(uuid[], integer, text) from anon, public;

-- ---------- forms ----------
create table public.forms (
  id          uuid primary key default gen_random_uuid(),
  title       text not null,
  description text,
  questions   jsonb not null default '[]',
  accepting   boolean not null default true,
  created_by  uuid references public.profiles (id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create table public.form_responses (
  id             bigint generated always as identity primary key,
  form_id        uuid not null references public.forms (id) on delete cascade,
  answers        jsonb not null,
  submitted_by   uuid references public.profiles (id) on delete set null,
  submitter_name text,
  created_at     timestamptz not null default now()
);
create index form_responses_form_idx on public.form_responses (form_id);

alter table public.forms          enable row level security;
alter table public.form_responses enable row level security;

create policy "forms: officers all"
  on public.forms for all to authenticated
  using (public.is_officer(auth.uid()))
  with check (public.is_officer(auth.uid()));

create policy "form_responses: officers read"
  on public.form_responses for select to authenticated
  using (public.is_officer(auth.uid()));

create policy "form_responses: officers delete"
  on public.form_responses for delete to authenticated
  using (public.is_officer(auth.uid()));

grant all on public.forms to authenticated;
grant select, delete on public.form_responses to authenticated;
grant all on public.forms, public.form_responses to service_role;

-- officer CRUD on forms is audited; responses are public submissions and
-- would just be noise in the audit log
drop trigger if exists audit_row on public.forms;
create trigger audit_row after insert or update or delete on public.forms
  for each row execute function public.audit_row();

-- ---------- public access, by exact link only ----------
create function public.get_form(form uuid)
returns jsonb
language sql stable security definer set search_path = public
as $$
  select to_jsonb(f) - 'created_by'
    from public.forms f
   where f.id = form;
$$;
grant execute on function public.get_form(uuid) to anon, authenticated;

create function public.submit_form(form uuid, answers jsonb, submitter text default null)
returns bigint
language plpgsql security definer set search_path = public
as $$
declare
  f   record;
  rid bigint;
begin
  select id, accepting into f from public.forms where id = form;
  if not found then
    raise exception 'form not found';
  end if;
  if not f.accepting then
    raise exception 'this form is no longer accepting responses';
  end if;
  if pg_column_size(answers) > 100000 then
    raise exception 'response too large';
  end if;

  insert into public.form_responses (form_id, answers, submitted_by, submitter_name)
  values (form, answers, auth.uid(), nullif(trim(submitter), ''))
  returning id into rid;

  return rid;
end;
$$;
grant execute on function public.submit_form(uuid, jsonb, text) to anon, authenticated;
