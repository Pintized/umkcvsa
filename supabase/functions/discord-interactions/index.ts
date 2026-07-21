// Discord HTTP interactions endpoint (slash commands, buttons).
// Discord signs every request with Ed25519; unsigned/invalid requests must be
// rejected with 401 or Discord will refuse to use this URL as an endpoint.
import { createClient } from "npm:@supabase/supabase-js@2";
import Anthropic from "npm:@anthropic-ai/sdk";

const DISCORD_PUBLIC_KEY = Deno.env.get("DISCORD_PUBLIC_KEY") ?? "";
const BOT_TOKEN = Deno.env.get("DISCORD_BOT_TOKEN") ?? "";
const ANNOUNCE_CHANNEL_ID = Deno.env.get("DISCORD_ANNOUNCE_CHANNEL_ID") ?? "";
const OFFICER_ROLE_ID = Deno.env.get("DISCORD_OFFICER_ROLE_ID") ?? "";
const AI_API_KEY = Deno.env.get("ANTHROPIC_API_KEY") ?? "";

// White-labeled: the assistant presents purely as the VSA Bot and is
// instructed never to name the underlying AI provider or model.
const CHAT_SYSTEM_PROMPT = `You are the UMKC VSA Bot, the friendly AI assistant of the \
University of Missouri-Kansas City Vietnamese Student Association's Discord server. \
Be warm, helpful, and concise; a little playful is welcome. You may use Discord \
markdown and emoji. Keep every reply under 1800 characters.

Identity rules: you are simply "the VSA Bot." If asked what AI, model, or company \
powers you, say you're the VSA Bot built by the UMKC VSA tech team and leave it at \
that — never name any AI company, model, or provider.`;

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
);

const InteractionType = { PING: 1, APPLICATION_COMMAND: 2, MESSAGE_COMPONENT: 3 } as const;
const ResponseType = { PONG: 1, CHANNEL_MESSAGE: 4, DEFERRED_CHANNEL_MESSAGE: 5 } as const;
const EPHEMERAL = 1 << 6;

function hexToBytes(hex: string): Uint8Array {
  const bytes = new Uint8Array(hex.length / 2);
  for (let i = 0; i < bytes.length; i++) {
    bytes[i] = parseInt(hex.slice(i * 2, i * 2 + 2), 16);
  }
  return bytes;
}

async function verifyRequest(req: Request, body: string): Promise<boolean> {
  const signature = req.headers.get("x-signature-ed25519");
  const timestamp = req.headers.get("x-signature-timestamp");
  if (!signature || !timestamp || !DISCORD_PUBLIC_KEY) return false;
  try {
    const key = await crypto.subtle.importKey(
      "raw",
      hexToBytes(DISCORD_PUBLIC_KEY),
      { name: "Ed25519" },
      false,
      ["verify"],
    );
    return await crypto.subtle.verify(
      "Ed25519",
      key,
      hexToBytes(signature),
      new TextEncoder().encode(timestamp + body),
    );
  } catch {
    return false;
  }
}

function reply(content: string, ephemeral = false) {
  return Response.json({
    type: ResponseType.CHANNEL_MESSAGE,
    data: { content, ...(ephemeral ? { flags: EPHEMERAL } : {}) },
  });
}

function formatTime(time: string | null): string {
  if (!time) return "";
  const [h, m] = time.split(":").map(Number);
  const hour12 = h % 12 === 0 ? 12 : h % 12;
  return ` · ${hour12}:${String(m).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`;
}

async function handleEvents(): Promise<Response> {
  const today = new Date().toISOString().slice(0, 10);
  const { data, error } = await supabase
    .from("events")
    .select("name, event_date, start_time, location, description")
    .gte("event_date", today)
    .order("event_date", { ascending: true })
    .order("start_time", { ascending: true })
    .limit(5);

  if (error) return reply("Sorry, I couldn't load events right now.", true);
  if (!data || data.length === 0) {
    return reply("No upcoming events on the calendar yet — check back soon! 🌸");
  }

  const lines = data.map((e) => {
    const date = new Date(e.event_date + "T00:00:00").toLocaleDateString("en-US", {
      weekday: "short",
      month: "short",
      day: "numeric",
    });
    const where = e.location ? ` @ ${e.location}` : "";
    return `**${e.name}** — ${date}${formatTime(e.start_time)}${where}`;
  });
  return reply(`📅 **Upcoming UMKC VSA Events**\n${lines.join("\n")}`);
}

function isOfficer(interaction: { member?: { roles?: string[] } }): boolean {
  if (!OFFICER_ROLE_ID) return false;
  return (interaction.member?.roles ?? []).includes(OFFICER_ROLE_ID);
}

async function handleAnnounce(interaction: {
  guild_id?: string;
  member?: { roles?: string[]; user?: { id: string } };
  data: { options?: { name: string; value: string }[] };
}): Promise<Response> {
  if (!isOfficer(interaction)) {
    return reply("Only members with the **Officer** role can use /announce.", true);
  }

  const text = interaction.data.options?.find((o) => o.name === "message")?.value;
  if (!text) return reply("Nothing to announce — the message was empty.", true);

  const res = await fetch(
    `https://discord.com/api/v10/channels/${ANNOUNCE_CHANNEL_ID}/messages`,
    {
      method: "POST",
      headers: {
        "Authorization": `Bot ${BOT_TOKEN}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ content: `📣 ${text}` }),
    },
  );

  if (!res.ok) {
    console.error(`Announce post failed (${res.status}):`, await res.text());
    return reply("Couldn't post the announcement — check the bot's channel permissions.", true);
  }
  return reply(`Announcement posted to <#${ANNOUNCE_CHANNEL_ID}>. ✅`, true);
}

async function handleWarnings(interaction: {
  member?: { roles?: string[] };
  data: { options?: { name: string; value: string }[] };
}): Promise<Response> {
  if (!isOfficer(interaction)) {
    return reply("Only members with the **Officer** role can use /warnings.", true);
  }

  const userId = interaction.data.options?.find((o) => o.name === "user")?.value;
  if (!userId) return reply("No user provided.", true);

  const { data, error } = await supabase
    .from("discord_warnings")
    .select("count, last_offense_at")
    .eq("discord_user_id", userId)
    .maybeSingle();

  if (error) return reply("Couldn't look up warnings right now.", true);
  if (!data || data.count === 0) {
    return reply(`<@${userId}> has a clean record — **0/3** warnings.`, true);
  }

  const remaining = Math.max(0, 3 - data.count);
  const last = data.last_offense_at
    ? ` Last offense: <t:${Math.floor(new Date(data.last_offense_at).getTime() / 1000)}:f>.`
    : "";
  return reply(
    `<@${userId}> has **${data.count}/3** warnings (${remaining} remaining).${last}`,
    true,
  );
}

async function runChat(
  applicationId: string,
  interactionToken: string,
  question: string,
): Promise<void> {
  let answer: string;
  try {
    const anthropic = new Anthropic({ apiKey: AI_API_KEY });
    const msg = await anthropic.messages.create({
      model: "claude-opus-4-8",
      max_tokens: 1024,
      thinking: { type: "adaptive" },
      output_config: { effort: "medium" },
      system: CHAT_SYSTEM_PROMPT,
      messages: [{ role: "user", content: question }],
    });
    if (msg.stop_reason === "refusal") {
      answer = "I'd rather not answer that one. 🌸";
    } else {
      answer = msg.content
        .filter((b): b is Anthropic.TextBlock => b.type === "text")
        .map((b) => b.text)
        .join("\n")
        .trim() || "…I've got nothing. Try rephrasing?";
    }
  } catch (err) {
    console.error("AI chat failed:", err);
    answer = "The AI assistant is unavailable right now — try again later.";
  }

  const quoted = `> ${question.slice(0, 150)}${question.length > 150 ? "…" : ""}\n`;
  const content = (quoted + answer).slice(0, 2000);

  const res = await fetch(
    `https://discord.com/api/v10/webhooks/${applicationId}/${interactionToken}/messages/@original`,
    {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ content }),
    },
  );
  if (!res.ok) console.error(`Chat followup failed (${res.status}):`, await res.text());
}

function handleChat(interaction: {
  application_id: string;
  token: string;
  data: { options?: { name: string; value: string }[] };
}): Response {
  if (!AI_API_KEY) {
    return reply("AI chat isn't set up yet — ask an officer to configure it.", true);
  }
  const question = interaction.data.options?.find((o) => o.name === "message")?.value;
  if (!question) return reply("Give me something to chat about!", true);

  // The AI call takes longer than Discord's 3s interaction window: defer now,
  // finish the work in the background, then edit the "thinking…" placeholder.
  const work = runChat(interaction.application_id, interaction.token, question);
  // deno-lint-ignore no-explicit-any
  (globalThis as any).EdgeRuntime?.waitUntil?.(work) ?? work;

  return Response.json({ type: ResponseType.DEFERRED_CHANNEL_MESSAGE });
}

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  const body = await req.text();
  if (!(await verifyRequest(req, body))) {
    return new Response("Invalid request signature", { status: 401 });
  }

  const interaction = JSON.parse(body);

  if (interaction.type === InteractionType.PING) {
    return Response.json({ type: ResponseType.PONG });
  }

  if (interaction.type === InteractionType.APPLICATION_COMMAND) {
    switch (interaction.data.name) {
      case "ping":
        return reply("Pong! 🏓 The VSA bot is alive.", true);
      case "events":
        return await handleEvents();
      case "announce":
        return await handleAnnounce(interaction);
      case "warnings":
        return await handleWarnings(interaction);
      case "chat":
        return handleChat(interaction);
      default:
        return reply(`Unknown command: ${interaction.data.name}`, true);
    }
  }

  return reply("Unhandled interaction type.", true);
});
