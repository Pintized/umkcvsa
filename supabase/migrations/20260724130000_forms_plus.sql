-- Forms, Google-Forms-grade: per-form settings (one response per
-- member, auto-close date, custom confirmation) enforced server-side
-- in submit_form so the public page can't be sidestepped.

alter table public.forms add column settings jsonb not null default '{}';

create or replace function public.submit_form(form uuid, answers jsonb, submitter text default null)
returns bigint
language plpgsql security definer set search_path = public
as $$
declare
  f   record;
  s   jsonb;
  rid bigint;
begin
  select id, accepting, settings into f from public.forms where id = form;
  if not found then
    raise exception 'form not found';
  end if;
  s := coalesce(f.settings, '{}'::jsonb);
  if not f.accepting then
    raise exception 'this form is no longer accepting responses';
  end if;
  if (s ->> 'close_at') is not null and now() > (s ->> 'close_at')::timestamptz then
    raise exception 'this form is no longer accepting responses';
  end if;
  if coalesce((s ->> 'limit_one')::boolean, false) then
    if auth.uid() is null then
      raise exception 'this form requires signing in to your VSA account';
    end if;
    if exists (
      select 1 from public.form_responses
      where form_id = form and submitted_by = auth.uid()
    ) then
      raise exception 'you have already submitted a response to this form';
    end if;
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
