// One-shot registrar for the bot's global slash commands. Idempotent:
// PUT overwrites the full command set, so this file is the source of
// truth — re-invoke after adding a command. The application id comes
// from Discord itself via the bot token.
const BOT_TOKEN = Deno.env.get("DISCORD_BOT_TOKEN") ?? "";

const STRING = 3, USER = 6;
const COMMANDS = [
  { name: "ping", description: "Check that the VSA bot is alive" },
  { name: "events", description: "See upcoming UMKC VSA events" },
  {
    name: "announce",
    description: "Post an announcement (officers only)",
    options: [{ type: STRING, name: "message", description: "What to announce", required: true }],
  },
  {
    name: "warnings",
    description: "Check a member's warning count (officers only)",
    options: [{ type: USER, name: "user", description: "Member to look up", required: true }],
  },
  {
    name: "chat",
    description: "Chat with the VSA Bot",
    options: [{ type: STRING, name: "message", description: "What do you want to ask?", required: true }],
  },
  {
    name: "teach",
    description: "Teach the VSA Bot a club fact (special role only)",
    options: [{ type: STRING, name: "fact", description: "The fact to remember (max 500 chars)", required: true, max_length: 500 }],
  },
];

Deno.serve(async () => {
  if (!BOT_TOKEN) return Response.json({ error: "DISCORD_BOT_TOKEN not set" }, { status: 500 });

  const app = await fetch("https://discord.com/api/v10/oauth2/applications/@me", {
    headers: { Authorization: `Bot ${BOT_TOKEN}` },
  }).then((r) => r.json());
  if (!app.id) return Response.json({ error: "could not resolve application id", app }, { status: 500 });

  const res = await fetch(`https://discord.com/api/v10/applications/${app.id}/commands`, {
    method: "PUT",
    headers: { Authorization: `Bot ${BOT_TOKEN}`, "Content-Type": "application/json" },
    body: JSON.stringify(COMMANDS),
  });
  const body = await res.json();
  return Response.json({
    status: res.status,
    registered: Array.isArray(body) ? body.map((c: { name: string }) => c.name) : body,
  }, { status: res.ok ? 200 : 500 });
});
