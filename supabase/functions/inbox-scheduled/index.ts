// Dispatches due scheduled emails. Invoked by pg_cron every 5 minutes
// (only when something is due); safe to invoke manually.
// Requires secrets: RESEND_API_KEY, INBOX_FROM.
import { createClient } from "npm:@supabase/supabase-js@2";

const RESEND_API_KEY = Deno.env.get("RESEND_API_KEY") ?? "";
const INBOX_FROM = Deno.env.get("INBOX_FROM") ?? "UMKC VSA <support@umkcvsa.org>";

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
);

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  const { data: due, error } = await supabase
    .from("inbox_emails")
    .select("id, to_addr, subject, text_body, html_body")
    .eq("folder", "scheduled")
    .lte("scheduled_at", new Date().toISOString())
    .limit(20);

  if (error) {
    console.error("Failed to load scheduled mail:", error);
    return new Response("db error", { status: 500 });
  }

  let sent = 0, failed = 0;
  for (const row of due ?? []) {
    if (!row.to_addr || !row.subject || (!row.text_body && !row.html_body)) {
      // Incomplete row can never send — park it back in drafts for the officer
      await supabase.from("inbox_emails")
        .update({ folder: "draft", scheduled_at: null }).eq("id", row.id);
      failed++;
      continue;
    }
    const res = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: {
        "Authorization": `Bearer ${RESEND_API_KEY}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        from: INBOX_FROM,
        to: [row.to_addr],
        subject: row.subject,
        ...(row.html_body ? { html: row.html_body } : {}),
        text: row.text_body || "(no text version)",
      }),
    });
    if (res.ok) {
      const r = await res.json();
      await supabase.from("inbox_emails").update({
        folder: "sent",
        message_id: r.id ?? null,
        received_at: new Date().toISOString(),
      }).eq("id", row.id);
      sent++;
    } else {
      // Provider rejected it — return to drafts so it doesn't retry forever
      console.error(`Scheduled send ${row.id} failed (${res.status}):`, await res.text());
      await supabase.from("inbox_emails")
        .update({ folder: "draft", scheduled_at: null }).eq("id", row.id);
      failed++;
    }
  }

  return Response.json({ sent, failed });
});
