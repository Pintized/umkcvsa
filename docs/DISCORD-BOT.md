# UMKC VSA Discord Bot

Hybrid bot for the UMKC VSA Discord server. Two halves, one Discord application:

| Half | Where it runs | Uptime | Handles |
|---|---|---|---|
| **Interactions** (`supabase/functions/discord-interactions/`) | Supabase Edge Functions | Always online, free | Slash commands, buttons, scheduled announcements (via pg_cron) |
| **Gateway** (`bot/`) | Any PC / host with Node | While the host is on | Welcome messages, live events, bot presence |

Both halves read the same Supabase database, so features never duplicate data.
If the gateway host is offline, only welcomes/presence pause — commands and
reminders keep working.

## Setup (one-time)

1. **Create the Discord application** at <https://discord.com/developers/applications>:
   - *New Application* → name it (e.g. "UMKC VSA Bot").
   - **General Information**: copy the **Application ID** and **Public Key**.
   - **Bot** tab: *Reset Token* and copy the **token** (shown once!).
     Enable **Server Members Intent** (needed for welcome messages).
   - **Installation** / OAuth2 URL: scopes `bot` + `applications.commands`;
     bot permissions: *Send Messages*, *Embed Links*, *Manage Roles* (for
     verification later). Open the generated URL and invite the bot to the server.
2. **Gateway half** (on the PC that hosts it):
   ```
   cd bot
   copy .env.example .env    # then fill in the values
   npm install
   npm run register          # registers /ping and /events with Discord
   npm start
   ```
3. **Interactions half** (from repo root):
   ```
   npx supabase secrets set DISCORD_PUBLIC_KEY=<public key from step 1>
   npx supabase functions deploy discord-interactions
   ```
   Then in the developer portal → General Information → **Interactions Endpoint
   URL**, paste:
   `https://<project-ref>.supabase.co/functions/v1/discord-interactions`
   Discord verifies the endpoint with a signed PING when you save — the deployed
   function must be live first.

## Migrating the gateway bot to a new PC

Clone the repo, `cd bot && npm install`, copy your old `bot/.env` over, `npm start`.
Nothing else — the Supabase half is unaffected.

## Todo

### v1
- [x] Scaffold hybrid structure (edge function + gateway bot)
- [x] Create Discord application, invite bot, fill in `bot/.env`
- [x] Deploy edge function + set Interactions Endpoint URL; verify `/ping`
- [x] Event announcements: daily pg_cron job (15:00 UTC ≈ 10 AM Central) posts
      today's + tomorrow's events via `discord-announce`
- [x] `/announce <message>` — officer-only (role ID via `DISCORD_OFFICER_ROLE_ID`
      secret) posts to the announcements channel
- [x] Moderation: Discord AutoMod (Slurs preset) blocks hate speech 24/7;
      gateway bot DMs the offender, tracks warnings (3 max) in
      `discord_warnings`, posts offense reports (reason, time, message,
      channel, warnings, user id) to the mod channel, and applies a 24h
      timeout on the 3rd warning. Requires Message Content intent +
      Manage Server + Moderate Members permissions.
- [x] `/warnings <user>` — officer-only lookup of a member's warning count
- [ ] Run gateway bot; verify welcome message fires
- [ ] `/events` — polish formatting (Discord timestamps, embeds)
- [ ] Member verification: `/verify` links Discord account ↔ Supabase profile, assigns Member/Officer roles
- [ ] Role picker: buttons/dropdown for interest roles (handled by edge function)
- [ ] Auto-start gateway bot on PC boot (pm2 or Task Scheduler)

### Ideas / later
- [ ] `/chat <text>` — AI chat via the Claude API (Messages API) in the
      discord-interactions edge function. Blocked on buying Anthropic API
      credits (platform.claude.com → API Keys). Plan: deferred interaction
      response (3s limit) → call Claude → edit reply; default model
      `claude-opus-4-8` (~1¢/chat; `claude-haiku-4-5` ~0.1¢ as cheap option);
      set ANTHROPIC_API_KEY as a Supabase secret; consider per-user rate limit.
- [ ] `/points` or member engagement tracking
- [ ] RSVP from Discord (buttons on event announcements → `rsvps` table)
- [ ] Officer task-board notifications (`tasks` table → officer channel)
- [ ] Anniversary/birthday shoutouts
- [ ] Move gateway half to a cloud host if PC uptime becomes annoying

*(Add new ideas here — this file is the bot's running todo list.)*
