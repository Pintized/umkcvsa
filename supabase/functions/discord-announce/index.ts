// Scheduled announcer: posts today's and tomorrow's events to the Discord
// announcements channel, and nudges the finance chair ahead of SAFC
// deadlines. Invoked by pg_cron (see the discord_announcements migration)
// once a day; safe to invoke manually for testing.
// Requires secrets: DISCORD_BOT_TOKEN, DISCORD_ANNOUNCE_CHANNEL_ID.
import { createClient } from "npm:@supabase/supabase-js@2";

const BOT_TOKEN = Deno.env.get("DISCORD_BOT_TOKEN")!;
const CHANNEL_ID = Deno.env.get("DISCORD_ANNOUNCE_CHANNEL_ID")!;
// SAFC reminders (ids are guild snowflakes, not secrets)
const SAFC_CHANNEL_ID = Deno.env.get("SAFC_CHANNEL_ID") ?? "1530311862200045708";
const SAFC_PING_USER_ID = Deno.env.get("SAFC_PING_USER_ID") ?? "901942585583632395";
const SAFC_REMIND_DAYS = [14, 7, 3, 1];
// [submit by 11:59 PM, SAFC reviews, event on/after, travel on/after] —
// keep in sync with docs/SAFC_Guidelines_2026-2027.md and the portal's
// Finance -> SAFC Deadlines 26-27 tab
const SAFC_ROWS: [string, string, string, string][] = [
  ["2026-08-26", "2026-08-31", "2026-09-14", "2026-10-05"],
  ["2026-09-16", "2026-09-21", "2026-10-05", "2026-10-26"],
  ["2026-09-30", "2026-10-05", "2026-10-19", "2026-11-09"],
  ["2026-10-14", "2026-10-19", "2026-11-02", "2026-11-23"],
  ["2026-10-28", "2026-11-02", "2026-11-16", "2026-12-07"],
  ["2026-11-11", "2026-11-16", "2026-11-30", "2026-12-21"],
  ["2026-12-02", "2026-12-07", "2026-12-21", "2027-01-11"],
  ["2027-01-27", "2027-02-01", "2027-02-15", "2027-03-08"],
  ["2027-02-10", "2027-02-15", "2027-03-01", "2027-03-22"],
  ["2027-02-24", "2027-03-01", "2027-03-15", "2027-04-05"],
  ["2027-03-10", "2027-03-15", "2027-03-29", "2027-04-19"],
  ["2027-03-24", "2027-04-05", "2027-04-19", "2027-05-10"],
  ["2027-04-14", "2027-04-19", "2027-05-03", "2027-05-24"],
];

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

function prettyDate(d: string): string {
  return new Date(d + "T12:00:00Z").toLocaleDateString("en-US", {
    weekday: "short", month: "short", day: "numeric",
  });
}

async function postDiscord(channel: string, content: string): Promise<boolean> {
  const res = await fetch(`https://discord.com/api/v10/channels/${channel}/messages`, {
    method: "POST",
    headers: {
      "Authorization": `Bot ${BOT_TOKEN}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ content }),
  });
  if (!res.ok) console.error(`Discord post to ${channel} failed (${res.status}):`, await res.text());
  return res.ok;
}

// 14/7/3/1 days before each SAFC submit deadline, nudge the finance chair —
// unless the cycle is already ticked off in the portal (safc_deadline_checks)
async function safcReminders(today: string): Promise<number> {
  const todayMs = Date.parse(today + "T00:00:00Z");
  const daysUntil = (submit: string) =>
    Math.round((Date.parse(submit + "T00:00:00Z") - todayMs) / 86_400_000);
  const due = SAFC_ROWS.filter(([submit]) => SAFC_REMIND_DAYS.includes(daysUntil(submit)));
  if (!due.length) return 0;

  const { data: checks, error } = await supabase
    .from("safc_deadline_checks")
    .select("deadline")
    .in("deadline", due.map(([submit]) => submit));
  if (error) {
    console.error("safc checks load failed:", error.message);
    return 0; // couldn't read the mute list — skip rather than risk nagging
  }
  const done = new Set((checks ?? []).map((c) => c.deadline));

  let posted = 0;
  for (const [submit, review, event, travel] of due) {
    if (done.has(submit)) continue;
    const days = daysUntil(submit);
    const ok = await postDiscord(
      SAFC_CHANNEL_ID,
      `<@${SAFC_PING_USER_ID}> ⏰ **SAFC deadline ${days === 1 ? "TOMORROW" : `in ${days} days`}!**
Budget requests for the **${prettyDate(review)}** SAFC meeting are due **${prettyDate(submit)} by 11:59 PM**.
> Events funded this cycle: on/after **${prettyDate(event)}** · travel: on/after **${prettyDate(travel)}**
_Already submitted? Tick this cycle off in the portal (Finance → SAFC Deadlines 26-27) to mute these reminders._`,
    );
    if (ok) posted++;
  }
  return posted;
}

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  const today = chicagoDate(0);
  const safcPosted = await safcReminders(today);
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
    return Response.json({ posted: false, reason: "no events today or tomorrow", safc: safcPosted });
  }

  const sections: string[] = [];
  if (todayEvents.length > 0) {
    sections.push(`🌸 **Happening TODAY!**\n${todayEvents.map(formatEvent).join("\n\n")}`);
  }
  if (tomorrowEvents.length > 0) {
    sections.push(`🔜 **Tomorrow:**\n${tomorrowEvents.map(formatEvent).join("\n\n")}`);
  }
  sections.push("_See all upcoming events with /events_");

  const ok = await postDiscord(CHANNEL_ID, sections.join("\n\n"));
  if (!ok) return new Response("discord error", { status: 502 });

  return Response.json({
    posted: true,
    today: todayEvents.length,
    tomorrow: tomorrowEvents.length,
    safc: safcPosted,
  });
});
