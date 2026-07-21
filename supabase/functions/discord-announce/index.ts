// Scheduled announcer: posts today's and tomorrow's events to the Discord
// announcements channel. Invoked by pg_cron (see the discord_announcements
// migration) once a day; safe to invoke manually for testing.
// Requires secrets: DISCORD_BOT_TOKEN, DISCORD_ANNOUNCE_CHANNEL_ID.
import { createClient } from "npm:@supabase/supabase-js@2";

const BOT_TOKEN = Deno.env.get("DISCORD_BOT_TOKEN")!;
const CHANNEL_ID = Deno.env.get("DISCORD_ANNOUNCE_CHANNEL_ID")!;

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
);

// Event dates are calendar dates in Kansas City time, so compute "today"
// in America/Chicago regardless of what timezone the server runs in.
function chicagoDate(offsetDays = 0): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "America/Chicago" })
    .format(new Date(Date.now() + offsetDays * 86_400_000));
}

function formatTime(time: string | null): string {
  if (!time) return "";
  const [h, m] = time.split(":").map(Number);
  const hour12 = h % 12 === 0 ? 12 : h % 12;
  return ` · ${hour12}:${String(m).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`;
}

type EventRow = {
  name: string;
  event_date: string;
  start_time: string | null;
  location: string | null;
  description: string | null;
};

function formatEvent(e: EventRow): string {
  const where = e.location ? ` @ **${e.location}**` : "";
  const desc = e.description ? `\n> ${e.description}` : "";
  return `**${e.name}**${formatTime(e.start_time)}${where}${desc}`;
}

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  const today = chicagoDate(0);
  const tomorrow = chicagoDate(1);

  const { data, error } = await supabase
    .from("events")
    .select("name, event_date, start_time, location, description")
    .in("event_date", [today, tomorrow])
    .order("event_date", { ascending: true })
    .order("start_time", { ascending: true });

  if (error) {
    console.error("Failed to load events:", error);
    return new Response("db error", { status: 500 });
  }

  const todayEvents = (data ?? []).filter((e) => e.event_date === today);
  const tomorrowEvents = (data ?? []).filter((e) => e.event_date === tomorrow);

  if (todayEvents.length === 0 && tomorrowEvents.length === 0) {
    return Response.json({ posted: false, reason: "no events today or tomorrow" });
  }

  const sections: string[] = [];
  if (todayEvents.length > 0) {
    sections.push(`🌸 **Happening TODAY!**\n${todayEvents.map(formatEvent).join("\n\n")}`);
  }
  if (tomorrowEvents.length > 0) {
    sections.push(`🔜 **Tomorrow:**\n${tomorrowEvents.map(formatEvent).join("\n\n")}`);
  }
  sections.push("_See all upcoming events with /events_");

  const res = await fetch(`https://discord.com/api/v10/channels/${CHANNEL_ID}/messages`, {
    method: "POST",
    headers: {
      "Authorization": `Bot ${BOT_TOKEN}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ content: sections.join("\n\n") }),
  });

  if (!res.ok) {
    console.error(`Discord post failed (${res.status}):`, await res.text());
    return new Response("discord error", { status: 502 });
  }

  return Response.json({ posted: true, today: todayEvents.length, tomorrow: tomorrowEvents.length });
});
