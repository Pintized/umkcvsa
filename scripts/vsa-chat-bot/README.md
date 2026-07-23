# VSA Bot — live chat channel

While this script runs, the bot answers **every human message** in one
dedicated channel — no `/chat` needed. Same brain, memory, taught
facts, event calendar, and guardrails as the slash command. Close the
terminal and the channel goes quiet; slash commands keep working
either way (they run in the cloud).

## One-time setup

1. **Python 3.10+**, then in this folder:
   `pip install -r requirements.txt`
2. **Message Content Intent**: [Discord Developer Portal](https://discord.com/developers/applications)
   → your app → **Bot** → Privileged Gateway Intents → enable
   **Message Content Intent** → Save.
3. **Dedicated channel**: create it (e.g. `#ask-vsa-bot`), make sure
   the bot's role can View Channel / Read Message History / Send
   Messages there. Right-click the channel → Copy Channel ID
   (needs Developer Mode: User Settings → Advanced).
4. **Secrets**: copy `.env.example` to `.env` and fill in:
   - `DISCORD_BOT_TOKEN` — dev portal → Bot → Reset Token
   - `ANTHROPIC_API_KEY` — the same key the edge functions use
   - `SUPABASE_SERVICE_ROLE_KEY` — Supabase dashboard → Project
     Settings → API keys → service_role (keep this one especially safe)
   - `VSA_CHAT_CHANNEL_ID` — from step 3; multiple channels are
     comma-separated: `VSA_CHAT_CHANNEL_ID=111,222,333`

## Run it

```
python bot.py
```

You'll see `VSA Bot is live as … — answering everything in #ask-vsa-bot.`
Leave the window open; **Ctrl+C** stops it. If the PC sleeps, the bot
sleeps with it.

## Notes

- 8-second per-user cooldown (spam gets a ⏳ reaction instead of a
  reply, and no AI call is spent).
- The system prompt mirrors the edge function's — if you change the
  bot's personality or rules, change both.
- `.env` is gitignored; never commit it or paste the service key
  anywhere public.
