# UMKC VSA — umkcvsa.org

Website and member/officer portal for the UMKC Vietnamese Student Association.

**Status:** migrating from DreamHost (PHP + MySQL) to **Supabase** (Postgres, Auth, Storage) + **GitHub** (code + hosting). See [docs/MIGRATION-PLAN.md](docs/MIGRATION-PLAN.md) for the full plan.

## Branches
- `main` — the new, reorganized project (work in progress).
- `legacy-import` — untouched snapshot of the DreamHost webroot + MySQL dump as exported on 2026-07-16. Reference only; never build on it.

## Layout (Phase 1 complete)
- `public/` — the website (deployed root): home, about, contact, eboard, gallery, store + robots/sitemap/favicon. The member portal will be added under `public/app/` in Phase 4.
- `legacy/php-app/` — old DreamHost PHP portal, kept as reference for the Supabase rebuild (do not deploy).
- `legacy/dreamhost-dump/umkcvsa_db.sql` — old MySQL dump (test data only; schema reference for Supabase migrations).
- `legacy/coming-soon/` — Apache config of the old splash page (the splash HTML itself was lost before export; superseded by the new site).
- `supabase/` — Supabase CLI config; migrations land here in Phase 3.
- `docs/` — migration plan and architecture decisions.

## Target stack
- Public site: static HTML/CSS/JS (the `new-pages/` redesign), hosted via GitHub Pages
- Portal: static pages + `supabase-js` — Supabase Auth, Postgres with Row Level Security, Storage
- CI/CD: GitHub Actions (Pages deploy + Supabase migrations)
