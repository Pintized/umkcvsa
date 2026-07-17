# UMKC VSA â€” Reorganization & Migration Master Plan
**DreamHost â†’ Supabase + GitHub**

- **Created:** 2026-07-16
- **Source analyzed:** `E:\UMKCVSA` (full DreamHost webroot export + phpMyAdmin DB dump)
- **Status:** Phase 0 âœ… Â· Phase 1 âœ… Â· Phase 2 âœ… Â· DNS âœ… (**https://umkcvsa.org live**) Â· Phase 3 âœ… (DB verified) Â· Phase 4a âœ… portal shell + auth live at /app/ (2026-07-17) Â· Next: 4b member pages, 4c officer pages
- **First-admin note:** grant Kalvin's real account `admin`+`officer` in `user_roles` via the dashboard SQL editor once he signs up (see session-06 changelog).
- **DNS:** zone still managed at DreamHost (ns1-3.dreamhost.com), domain set to "DNS Only"; apex â†’ 4Ã— GitHub Pages A records, www â†’ CNAME pintized.github.io. DreamHost shared hosting is now unused and cancelable.
- **Deploy method:** repo made public (Free-plan Pages); Pages serves the `gh-pages` branch (subtree of `public/`). Actions workflow is written (`.github/workflows/deploy-pages.yml`, uncommitted) but blocked until the gh token gets `workflow` scope (`gh auth refresh -h github.com -s workflow`); until then redeploy via `git subtree split --prefix=public HEAD` + push to `gh-pages`.
- **Known caveat:** absolute links (`/about/`, `/assets/â€¦`) 404 when browsing the github.io **subpath** preview â€” they are correct for the real domain and will work once umkcvsa.org points at Pages.
- **Repo:** https://github.com/Pintized/umkcvsa Â· **Supabase:** ref `wrlpsetbkeyoyamkopgf` (us-east-2)
- âš ï¸ 2026-07-17: The Coming Soon splash (`index.html`) + `index-backup.html` vanished from the local export before git import, and umkcvsa.org now returns 404 (DreamHost apparently already emptied). No Wayback snapshot. Splash is unrecoverable â€” accepted loss, it was obsolete. The `legacy/coming-soon/` folder keeps only the Apache htaccess for reference.

---

## 1. What exists today (full discovery inventory)

`E:\UMKCVSA` contains 79 files (~2.5 MB) â€” a DreamHost webroot export (`umkcvsa/`) plus one database dump (`umkcvsa_db.sql`). It is really **four different websites/apps layered on top of each other**, which is why it feels like a mess:

### Layer A â€” "Coming Soon" splash (currently the live site)
- `umkcvsa/index.html` (23 KB) â€” animated "UMKC VSA | Coming Soon" page.
- `umkcvsa/.htaccess` â€” rewrites **every non-file URL to index.html**, so the splash is effectively the whole public site right now.

### Layer B â€” Old React/Vite SPA (dead weight)
- `umkcvsa/assets/index-Dxf7RxNo.js` (628 KB) + `index-U8WWV61K.css` (23 KB) + `logo.png` (572 KB) â€” a **built** Vite bundle. No source code exists anywhere in this export â€” only the compiled output.
- `umkcvsa/about|contact|eboard|gallery|store/index.html` (~2.5 KB each) â€” SPA redirect shims (the classic GitHub-Pages-style `/?p=/path` hack) that load the React bundle. They are unreachable in practice because of the Layer A rewrite.
- `umkcvsa/index-backup.html` â€” the SPA's real index.html, saved off when the splash replaced it.

### Layer C â€” New static site, in progress (the future public site)
- `umkcvsa/home-new.html` (24 KB) â€” new homepage design (topbar/nav/hero, navy #16314d + red #c8202f + Playfair Display / Source Sans 3 branding).
- `umkcvsa/new-pages/about.html, contact.html, eboard.html, gallery.html, store.html` (9â€“14 KB each) â€” full hand-written static redesigns of every page. **This is the newest, best public-site content in the export.**

### Layer D â€” PHP member/officer portal (`/app`) â€” the real application
- **Core:** `app/db.php` (PDO MySQL), `app/auth.php` (sessions, roles, bcrypt), `app/login.php`, `app/signup.php`, `app/logout.php`, `app/profile.php`.
- **Partials:** theme (dark mode), sidebar, topbar, officer-chrome, audit helper â€” included via `$_SERVER['DOCUMENT_ROOT']` paths.
- **Member pages** (`app/user/`): achievements (37 KB, real), calendar (20 KB, real), members (14 KB, real), rewards (32 KB, real), plus **six ~7.5 KB placeholder pages** (events, family, language, notifications, settings, support â€” same template, minimal content) and `nightmode.php` (tiny helper).
- **Officer pages** (`app/user/officer/`): tasks board (89 KB **and** a 91 KB `tasksnew.php` variant â€” near-duplicates, only diverged slightly), events admin (`eventsnew.php` 35 KB + `modify-events.php` 34 KB â€” another duplicate pair), inventory (35 KB + `inventory-api.php`), notes (39 KB + `notes-api.php`), tasks-api, audit-log, permissions, roles.
- **Pattern:** every page is a self-contained monolith â€” PHP + inline CSS + inline JS in one file. APIs are separate `*-api.php` JSON endpoints; some pages post to themselves.
- **DreamHost coupling:** `db.php` does `require_once '/home/pintized/vsa-config/config.php'` â€” DB credentials and the `UPLOAD_URL` constant live **outside the webroot and are NOT in this backup**. `UPLOAD_URL` implies a profile-picture upload directory that is **also not in this export** (no user uploads found).

### Layer E â€” Junk / metadata
- `umkcvsa/backup/` â€” byte-identical (verified by hash) copy of the Layer B SPA site + DreamHost's `.dh-diag` PHP diagnostic. Pure duplicate. Delete.
- `.dh-diag` (0 bytes), `favicon.gif` (0 bytes), `favicon.ico` (0 bytes) â€” empty files. The real favicon is `/assets/logo.png`.
- `robots.txt`, `agents.txt`, `sitemap.xml` â€” keep (sitemap lists `umkcvsa.org` + the five subpages).

### Database (`umkcvsa_db.sql`, MySQL 8.0 dump from 2026-07-16)
18 tables, all prefixed `app_`:

| Table | Rows | Notes |
|---|---|---|
| `app_users` | 3 | **All test users.** Roles are a MySQL `SET('member','officer','alumni','intern','admin')`. Bcrypt `$2y$12` hashes. |
| `app_events` | 1 | Test event |
| `app_rsvps` | 0 | References events **by name+date string**, not FK â€” schema smell |
| `app_tasks` / `app_task_assignees` / `app_task_edges` | 6 / 5 / 3 | Free-position task board (pos_x/pos_y + dependency edges) |
| `app_notes` / `app_note_folders` | 0 / 0 | |
| `app_documents` / `app_document_folders` | 1 / 3 | HTML content docs |
| `app_inventory` | 2 | |
| `app_rewards` / `app_achievements` / `app_achievement_awards` | 1 / 1 / 4 | Points economy |
| `app_orders` / `app_order_items` / `app_cart_items` | 0 / 0 / 0 | Store schema exists; **no Stripe code anywhere** (only an enum value) |
| `app_audit_log` | 165 | All dev-testing noise |
| `app_login_attempts` | 10 | Rate-limit log |

> **Key insight: there is no production data.** Every row is test data from June 2026 development. This means we do **not** need a careful data migration â€” we need a clean **schema** migration, and can optionally re-seed the handful of real-ish rows (1 achievement, 1 reward). This dramatically simplifies everything.

### External dependencies found
- Google Fonts (every page) â€” fine, keep.
- **Nothing else.** No Stripe SDK, no mail sending, no third-party APIs. The `payment_method` enum mentions stripe/cash/zelle/venmo but no integration was ever built.

---

## 2. Target architecture (recommendation)

**Goal state: a single GitHub monorepo; Supabase for database + auth + storage; static/JS frontend hosting from the repo. DreamHost fully retired.**

### Recommended stack

| Concern | Today (DreamHost) | Target |
|---|---|---|
| Public site | Coming-soon splash + dead SPA + unpublished redesign | The **Layer C redesign** as the public site (static HTML/CSS/JS) |
| Member/officer portal | PHP monoliths + MySQL sessions | **Rebuild as a JS frontend on Supabase**: supabase-js + Supabase Auth + Postgres with Row Level Security |
| Database | MySQL 8 on DreamHost | **Supabase Postgres**, schema rebuilt idiomatically (see Â§4) |
| Auth | PHP sessions, bcrypt, hand-rolled roles | **Supabase Auth** (email/password; bcrypt hashes are importable, but with only 3 test users, just recreate accounts) |
| File uploads (profile pics, gallery) | `UPLOAD_URL` dir on DreamHost (not in backup) | **Supabase Storage** buckets (`avatars`, `gallery`, `documents`) |
| API endpoints | `*-api.php` | PostgREST (auto API from Supabase) + RLS; Edge Functions only where server logic is truly needed (e.g., points granting) |
| Hosting | DreamHost Apache | **GitHub Pages** (free, fits a static site + supabase-js portal) â€” or Vercel/Netlify if we later want redirects/headers/SSR. Start with Pages. |
| CI/CD | FTP by hand | GitHub Actions: deploy on push to `main`; Supabase migrations via `supabase db push` |
| DNS | umkcvsa.org â†’ DreamHost | umkcvsa.org â†’ GitHub Pages (CNAME), cut over last |

### Why rebuild the portal instead of porting PHP?
1. GitHub Pages / Supabase cannot run PHP â€” keeping PHP means keeping a PHP host, which defeats the stated goal ("everything to Supabase and GitHub").
2. The PHP code's *server* responsibilities are exactly what Supabase gives for free: auth/sessions â†’ Supabase Auth; `require_officer()` â†’ RLS policies; `*-api.php` â†’ PostgREST; audit log â†’ DB triggers.
3. Each PHP page is already 80â€“90% client-side HTML/CSS/JS with inline fetch calls â€” the UI can be lifted nearly as-is into static pages that call supabase-js instead of `*-api.php`.
4. There is no production data and only 3 test users â€” zero migration risk.

### Alternative considered (rejected)
Port PHP to a VPS/Render + Supabase Postgres via PDO. Rejected: keeps two runtimes, keeps session handling, keeps a paid host, and MySQLâ†’Postgres SQL differences would touch every query anyway. Only worth revisiting if a hard requirement for server-rendered PHP appears.

---

## 3. New repository layout

One GitHub repo, e.g. `umkcvsa/umkcvsa.org`:

```
umkcvsa.org/
â”œâ”€â”€ README.md
â”œâ”€â”€ .gitignore                  # .env, node_modules, supabase/.temp
â”œâ”€â”€ docs/
â”‚   â”œâ”€â”€ ARCHITECTURE.md
â”‚   â””â”€â”€ DECISIONS.md            # running ADR log
â”œâ”€â”€ public/                     # â† deployed site root (GitHub Pages artifact)
â”‚   â”œâ”€â”€ index.html              # from home-new.html
â”‚   â”œâ”€â”€ about/index.html        # from new-pages/about.html
â”‚   â”œâ”€â”€ contact/index.html      # from new-pages/contact.html
â”‚   â”œâ”€â”€ eboard/index.html       # from new-pages/eboard.html
â”‚   â”œâ”€â”€ gallery/index.html      # from new-pages/gallery.html
â”‚   â”œâ”€â”€ store/index.html        # from new-pages/store.html
â”‚   â”œâ”€â”€ robots.txt, sitemap.xml, agents.txt, favicon.ico (real one)
â”‚   â”œâ”€â”€ assets/
â”‚   â”‚   â”œâ”€â”€ css/site.css        # extracted shared styles (topbar/nav/footer)
â”‚   â”‚   â”œâ”€â”€ js/
â”‚   â”‚   â””â”€â”€ img/logo.png        # compressed (572 KB â†’ target <100 KB)
â”‚   â””â”€â”€ app/                    # member/officer portal (static + supabase-js)
â”‚       â”œâ”€â”€ login.html  signup.html
â”‚       â”œâ”€â”€ shared/             # supabaseClient.js, auth-guard.js, sidebar, theme
â”‚       â”œâ”€â”€ user/               # profile, calendar, achievements, rewards, members, â€¦
â”‚       â””â”€â”€ officer/            # tasks, events, inventory, notes, audit-log, roles
â”œâ”€â”€ supabase/
â”‚   â”œâ”€â”€ config.toml
â”‚   â”œâ”€â”€ migrations/             # 0001_schema.sql, 0002_rls.sql, 0003_triggers.sql, 0004_seed.sql
â”‚   â””â”€â”€ functions/              # edge functions (only if needed: grant-points, checkout)
â”œâ”€â”€ legacy/                     # frozen reference copy during migration (deleted at the end)
â”‚   â”œâ”€â”€ php-app/                # the old /app tree
â”‚   â”œâ”€â”€ dreamhost-dump/umkcvsa_db.sql
â”‚   â””â”€â”€ coming-soon/index.html
â””â”€â”€ .github/workflows/
    â”œâ”€â”€ deploy-pages.yml
    â””â”€â”€ supabase-migrations.yml
```

**What does NOT come over:** `backup/` (hash-verified duplicate), the compiled SPA (`assets/index-*.js/css`, the five SPA shim pages, `index-backup.html`), `.dh-diag`, empty favicons, and whichever of the duplicate-pair PHP files loses (see Â§5).

---

## 4. Database migration (MySQL â†’ Supabase Postgres)

Rebuild the schema as Postgres migrations rather than converting the dump. Conventions: `snake_case` kept; `int UNSIGNED AUTO_INCREMENT` â†’ `bigint generated always as identity`; `datetime/timestamp` â†’ `timestamptz`; MySQL `SET` role column â†’ join table; `enum` â†’ Postgres enum or `text + check`.

### Table-by-table mapping

| MySQL | Postgres plan |
|---|---|
| `app_users` | `public.profiles` keyed by `auth.users.id (uuid)`. Columns: first/last/full name, avatar_path (â†’ Storage), points. **Drop** `password_hash`, `email` dup (auth owns those). Auto-create via `on auth.users insert` trigger. |
| role SET column | `public.user_roles (user_id, role)` + `role` enum (`member,officer,alumni,intern,admin`). Powers RLS via a `has_role(uid, role)` security-definer function. |
| `app_events` | `events` â€” same shape, FK `created_by â†’ profiles`. |
| `app_rsvps` | `rsvps` â€” **fix the smell:** FK `event_id â†’ events.id` instead of name+date strings; unique `(user_id, event_id)`. |
| `app_tasks`, `app_task_assignees`, `app_task_edges` | Same three tables; keep pos_x/pos_y board model; `status`/`priority` as `text + check`. |
| `app_notes`, `app_note_folders` | Same; consider merging with documents later (both are folder+content trees â€” note as a post-migration cleanup). |
| `app_documents`, `app_document_folders` | Same; `content_html` stays `text`. |
| `app_inventory` | Same. |
| `app_rewards`, `app_achievements`, `app_achievement_awards` | Same; points mutations via an RPC/Edge Function so totals can't be forged client-side. |
| `app_orders`, `app_order_items`, `app_cart_items` | Same shape; `payment_status`/`payment_method` as enums. Store stays "manual payment" until a Stripe decision is made. |
| `app_audit_log` | `audit_log`, but written by **DB triggers** on the audited tables (insert/update/delete) instead of PHP calls â€” more complete and tamper-resistant. |
| `app_login_attempts` | **Drop.** Supabase Auth has its own rate limiting + auth logs. |

### RLS policy sketch (replaces auth.php)
- `profiles`: user reads/updates own row; officers read all; points column only updatable via definer function.
- `events`: authenticated read; officer insert/update/delete.
- `tasks*`, `inventory`, `notes*`, `documents*`, `audit_log`: officer-only (audit_log: no client writes at all).
- `rsvps`, `cart_items`: owner-only CRUD; officers read all rsvps.
- `rewards`/`achievements`: authenticated read; officer write. Awards/redemptions via RPC.

### Data to carry over
Only seeds: the "First Event Attended" achievement, the "VSA T-Shirt" reward, optionally the GBM test event. Everything else is dev noise. Real users re-register (3 test accounts don't matter).

---

## 5. Duplicate/variant resolution decisions

| Conflict | Resolution |
|---|---|
| `index.html` (Coming Soon) vs `home-new.html` | `home-new.html` becomes the real homepage. Keep splash in `legacy/` until launch. |
| `about/index.html` (SPA shim) vs `new-pages/about.html` | `new-pages/*` wins for all five pages; shims deleted. |
| `backup/` vs root | Delete `backup/` (hash-verified identical where it matters). |
| `tasks.php` vs `tasksnew.php` (89/91 KB, same header, small divergence) | **Diff them at rebuild time**, port the newer behavior (`tasksnew.php` likely â€” verify by diff, not by name), discard the other. |
| `eventsnew.php` vs `modify-events.php` | Same treatment â€” diff, pick one, port once. |
| Six placeholder member pages (events/family/language/notifications/settings/support) | Do **not** port six copies of the same template. Rebuild as one layout; ship only pages with real content (settings first; others become nav stubs or are cut). |
| Empty favicons | Generate a real favicon set from logo.png. |

---

## 6. Phased execution plan

### Phase 0 â€” Safety net & repo bootstrap (first work session)
1. Zip `E:\UMKCVSA` as an untouched archive (keep on E: + one copy elsewhere).
2. Create GitHub repo, push the current mess as-is on a `legacy-import` branch (instant off-DreamHost code backup), add `.gitignore`, README, this plan in `docs/`.
3. Create the Supabase project (free tier); record project ref/URL; store keys in a password manager + GitHub Actions secrets â€” never in the repo.
4. ~~Retrieve from DreamHost~~ **Resolved 2026-07-17:** Kalvin confirmed `vsa-config/config.php` is useless and already deleted on DreamHost; nothing to retrieve. `backup/` folder is very old and permanently out of scope â€” never reference or reuse it. All users/uploads are test data, so no uploads directory matters either.

### Phase 1 â€” Repo reorganization (pure file moves, no rewrites)
5. Build the Â§3 layout on `main`: promote Layer C to `public/`, move PHP app + dump + splash into `legacy/`, delete Layer B/E junk (it survives in git history and the zip anyway).
6. Compress `logo.png`; create real favicons; update `sitemap.xml` lastmod dates.
7. Extract the shared topbar/nav/footer/styles duplicated across the six Layer C pages into `assets/css/site.css` + a tiny include mechanism (or accept duplication for v1 â€” decide at implementation).

### Phase 2 â€” Public site live on GitHub Pages
8. GitHub Actions â†’ Pages deploy of `public/`; verify at `*.github.io`.
9. Keep DreamHost live during this phase; DNS moves in Phase 6.

### Phase 3 â€” Supabase foundation
10. Write migrations `0001â€“0004` per Â§4 (schema â†’ RLS â†’ triggers â†’ seed) with the Supabase CLI; commit; CI runs `supabase db push`.
11. Configure Auth (email/password, redirect URLs, email confirmation) and Storage buckets (`avatars` public-read/owner-write; `documents` officer-only).
12. Test RLS with three throwaway accounts (member / officer / admin) before any UI exists â€” via SQL editor or a scratch script.

### Phase 4 â€” Portal rebuild (the big one; multiple sessions)
Order: shared shell first, then member pages, then officer pages.
13. `shared/`: supabase client, auth guard, sidebar/topbar/theme ported from the PHP partials.
14. Auth pages: login, signup, logout, password reset (new â€” Supabase gives it nearly free).
15. Member: profile (+ avatar upload to Storage), calendar, achievements, rewards, members directory, settings.
16. Officer: tasks board (port the winning variant; PostgREST + realtime subscription replaces its polling), events admin, inventory, notes, audit-log viewer, roles manager.
17. Cut: permissions.php (folded into roles), login-attempt tracking, the five contentless placeholder pages.
18. Store: keep static "how to buy" page for v1; cart/checkout only after a payments decision (Stripe Checkout via Edge Function is the natural v2).

### Phase 5 â€” Verification
19. Per-page checklist: member cannot reach officer pages (RLS, not just UI); dark mode; mobile; avatar upload; audit triggers fire; task board CRUD + drag positions persist.
20. Lighthouse pass on public pages; link check; sitemap/robots sanity.

### Phase 6 â€” Cutover & decommission
21. Point umkcvsa.org DNS at GitHub Pages (A/ALIAS + CNAME, enforce HTTPS). Lower TTL a day ahead.
22. Watch for a few days; keep DreamHost paid-but-idle for one billing cycle as rollback.
23. Final DreamHost sweep (files, DB export re-check, mailboxes, cron), then cancel hosting. Delete `legacy/` from `main` (history keeps it).

---

## 7. Risks & open questions

| # | Item | Impact | Mitigation / needed answer |
|---|---|---|---|
| 1 | `vsa-config/config.php` + uploads dir not in backup | Lose profile pics & the only copy of DB creds | Phase 0 step 4 â€” grab from DreamHost **now** |
| 2 | Is anything on DreamHost besides this site (email @umkcvsa.org, other subdomains, cron)? | Could break silently at cutover | Audit DreamHost panel in Phase 0 |
| 3 | Who owns DNS for umkcvsa.org (registrar)? | Blocks Phase 6 | Confirm registrar access before Phase 2 ends |
| 4 | Payments for the store (Stripe? manual Venmo/Zelle only?) | Determines store scope | Defer store checkout to v2; decide later |
| 5 | Supabase free-tier pausing (projects pause after ~1 week inactivity) | Portal downtime for a small club site | Acceptable during build; consider Pro or a keep-alive ping at launch |
| 6 | GitHub Pages = no server-side redirects/headers | Minor (deep-link 404 handling) | Static site needs none; if it hurts, move to Vercel/Netlify without changing the repo |
| 7 | Org continuity (officers change yearly) | Repo/Supabase orphaned accounts | Use a GitHub **organization** + shared credential vault, not a personal account |

---

## 8. Session log convention (standing rule)
Every working session gets a changelog file in
`C:\Users\Kalvin\Desktop\Heo\Heo\Projects\Claude Code Chats\UMKC VSA\changelogs\`
named `YYYY-MM-DD-session-NN.md`, covering: what changed, decisions made, and next steps. This plan document gets updated (phases checked off, decisions appended) as work proceeds.
