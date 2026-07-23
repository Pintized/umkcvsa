// Registers slash commands with Discord. Run whenever commands change:
//   npm run register   (from the bot/ folder)
// Registers to the single VSA guild so updates appear instantly
// (global commands can take up to an hour to propagate).
const { DISCORD_TOKEN, DISCORD_APP_ID, DISCORD_GUILD_ID } = process.env;

if (!DISCORD_TOKEN || !DISCORD_APP_ID || !DISCORD_GUILD_ID) {
  console.error("Missing DISCORD_TOKEN, DISCORD_APP_ID, or DISCORD_GUILD_ID in .env");
  process.exit(1);
}

const commands = [
  {
    name: "ping",
    description: "Check that the VSA bot is alive",
  },
  {
    name: "events",
    description: "Show upcoming UMKC VSA events",
  },
  {
    name: "chat",
    description: "Chat with the VSA AI assistant",
    options: [
      {
        type: 3, // STRING
        name: "message",
        description: "What do you want to ask?",
        required: true,
      },
    ],
  },
  {
    name: "warnings",
    description: "Check a member's moderation warning count (Officers only)",
    options: [
      {
        type: 6, // USER
        name: "user",
        description: "The member to look up",
        required: true,
      },
    ],
  },
  {
    name: "verify",
    description: "Link your Discord to your umkcvsa.org account",
    options: [
      {
        type: 3, // STRING
        name: "code",
        description: "The code from umkcvsa.org → Settings → Discord",
        required: true,
      },
    ],
  },
  {
    name: "teach",
    description: "Teach the VSA bot a fact to remember (Officers only)",
    options: [
      {
        type: 3, // STRING
        name: "fact",
        description: "The fact to remember",
        required: true,
      },
    ],
  },
  {
    name: "announce",
    description: "Post an announcement to the announcements channel (Officers only)",
    options: [
      {
        type: 3, // STRING
        name: "message",
        description: "The announcement text",
        required: true,
      },
    ],
  },
];

const res = await fetch(
  `https://discord.com/api/v10/applications/${DISCORD_APP_ID}/guilds/${DISCORD_GUILD_ID}/commands`,
  {
    method: "PUT",
    headers: {
      "Authorization": `Bot ${DISCORD_TOKEN}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify(commands),
  },
);

if (!res.ok) {
  console.error(`Failed (${res.status}):`, await res.text());
  process.exit(1);
}

const registered = await res.json();
console.log(`Registered ${registered.length} commands: ${registered.map((c) => `/${c.name}`).join(", ")}`);
