# UMKC VSA — umkcvsa.org

Website and member/officer portal for the UMKC Vietnamese Student Association.

**Status:** migrating from DreamHost (PHP + MySQL) to **Supabase** (Postgres, Auth, Storage) + **GitHub** (code + hosting). See [docs/MIGRATION-PLAN.md](docs/MIGRATION-PLAN.md) for the full plan.

## Branches
- `main` — the new, reorganized project (work in progress).
- `legacy-import` — untouched snapshot of the DreamHost webroot + MySQL dump as exported on 2026-07-16. Reference only; never build on it.

## Current layout (Phase 0/1)
- `umkcvsa/` — legacy DreamHost webroot (being reorganized per the plan)
- `umkcvsa_db.sql` — legacy MySQL dump (test data only; schema reference for Supabase migrations)
- `docs/` — migration plan and architecture decisions

## Target stack
- Public site: static HTML/CSS/JS (the `new-pages/` redesign), hosted via GitHub Pages
- Portal: static pages + `supabase-js` — Supabase Auth, Postgres with Row Level Security, Storage
- CI/CD: GitHub Actions (Pages deploy + Supabase migrations)
