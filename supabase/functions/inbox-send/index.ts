// Sends email from the officer portal via Resend and records it in
// inbox_emails. Called with the signed-in officer's JWT; officer status is
// re-checked server-side against user_roles.
// Requires secrets: RESEND_API_KEY, INBOX_FROM (e.g. "UMKC VSA <contact@umkcvsa.org>").
import { createClient } from "npm:@supabase/supabase-js@2";

const RESEND_API_KEY = Deno.env.get("RESEND_API_KEY") ?? "";
const INBOX_FROM = Deno.env.get("INBOX_FROM") ?? "UMKC VSA <support@umkcvsa.org>";

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
);

const CORS = {
  "Access-Control-Allow-Origin": "*", // JWT + role check are the real gate
  "Access-Control-Allow-Headers": "authorization, content-type, apikey, x-client-info",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...CORS, "Content-Type": "application/json" },
  });
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response(null, { headers: CORS });
  if (req.method !== "POST") return json(405, { error: "Method not allowed" });
  if (!RESEND_API_KEY) return json(503, { error: "Email sending is not configured yet" });

  // Identify the caller from their JWT and confirm they're an officer
  const jwt = (req.headers.get("Authorization") ?? "").replace(/^Bearer\s+/i, "");
  const { data: userData, error: userErr } = await supabase.auth.getUser(jwt);
  if (userErr || !userData?.user) return json(401, { error: "Not signed in" });

  const { data: roles } = await supabase
    .from("user_roles")
    .select("role")
    .eq("user_id", userData.user.id);
  const isOfficer = (roles ?? []).some((r) => r.role === "officer" || r.role === "admin");
  if (!isOfficer) return json(403, { error: "Officers only" });

  const { to, subject, text, reply_to_id } = await req.json().catch(() => ({}));
  if (typeof to !== "string" || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
    return json(400, { error: "Invalid recipient address" });
  }
  if (!subject?.trim() || !text?.trim()) {
    return json(400, { error: "Subject and message are required" });
  }

  const res = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${RESEND_API_KEY}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ from: INBOX_FROM, to: [to], subject, text }),
  });
  if (!res.ok) {
    console.error(`Resend send failed (${res.status}):`, await res.text());
    return json(502, { error: "The email provider rejected the send" });
  }
  const sent = await res.json();

  const { error: dbErr } = await supabase.from("inbox_emails").insert({
    message_id: sent.id ?? null,
    direction: "out",
    from_addr: INBOX_FROM,
    to_addr: to,
    subject,
    text_body: text,
    replied_to: reply_to_id ?? null,
    sent_by: userData.user.id,
    read_at: new Date().toISOString(), // outgoing mail is inherently "read"
  });
  if (dbErr) console.error("Sent but failed to record:", dbErr);

  return json(200, { sent: true, id: sent.id ?? null });
});
