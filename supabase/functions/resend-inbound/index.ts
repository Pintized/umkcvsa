// Receives Resend inbound-email webhooks (email.received) and stores the
// message in inbox_emails for the officer portal.
// Resend signs webhooks with Svix headers; requests failing verification
// are rejected. Requires secret: RESEND_WEBHOOK_SECRET (whsec_...).
import { createClient } from "npm:@supabase/supabase-js@2";

const WEBHOOK_SECRET = Deno.env.get("RESEND_WEBHOOK_SECRET") ?? "";
// Full-access key: the email.received webhook carries only metadata, so the
// message body is fetched back from Resend's API by id.
const READ_KEY = Deno.env.get("RESEND_READ_KEY") ?? "";

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
);

async function verifySvix(req: Request, body: string): Promise<boolean> {
  const id = req.headers.get("svix-id");
  const timestamp = req.headers.get("svix-timestamp");
  const signatures = req.headers.get("svix-signature");
  if (!id || !timestamp || !signatures || !WEBHOOK_SECRET) return false;

  // Reject stale timestamps (replay window: 5 minutes)
  const age = Math.abs(Date.now() / 1000 - Number(timestamp));
  if (!Number.isFinite(age) || age > 300) return false;

  const secretBytes = Uint8Array.from(
    atob(WEBHOOK_SECRET.replace(/^whsec_/, "")),
    (c) => c.charCodeAt(0),
  );
  const key = await crypto.subtle.importKey(
    "raw",
    secretBytes,
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signed = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(`${id}.${timestamp}.${body}`),
  );
  const expected = btoa(String.fromCharCode(...new Uint8Array(signed)));

  // Header holds space-separated "v1,<base64>" entries
  return signatures.split(" ").some((entry) => {
    const [version, sig] = entry.split(",");
    return version === "v1" && sig === expected;
  });
}

function addr(value: unknown): { email: string; name: string | null } {
  // Handles "a@b.c", "Name <a@b.c>", and {email, name} shapes
  if (value && typeof value === "object") {
    const v = value as { email?: string; name?: string };
    return { email: v.email ?? "", name: v.name ?? null };
  }
  const s = String(value ?? "");
  const m = s.match(/^\s*"?([^"<]*)"?\s*<([^>]+)>\s*$/);
  if (m) return { email: m[2].trim(), name: m[1].trim() || null };
  return { email: s.trim(), name: null };
}

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  const body = await req.text();
  if (!(await verifySvix(req, body))) {
    return new Response("Invalid signature", { status: 401 });
  }

  const event = JSON.parse(body);
  if (event.type !== "email.received") {
    return Response.json({ ignored: event.type });
  }

  const data = event.data ?? {};
  const from = addr(data.from);
  const to = Array.isArray(data.to) ? addr(data.to[0]).email : addr(data.to).email;

  // Fetch the full message content by id (webhook payload is metadata-only)
  let text: string | null = data.text ?? null;
  let html: string | null = data.html ?? null;
  if (READ_KEY && data.email_id && text === null && html === null) {
    for (const path of [`emails/receiving/${data.email_id}`, `emails/${data.email_id}`]) {
      try {
        const res = await fetch(`https://api.resend.com/${path}`, {
          headers: { "Authorization": `Bearer ${READ_KEY}` },
        });
        if (res.ok) {
          const full = await res.json();
          text = full.text ?? null;
          html = full.html ?? null;
          break;
        }
      } catch (err) {
        console.error(`Body fetch failed (${path}):`, err);
      }
    }
    if (text === null && html === null) {
      console.error("Could not retrieve body for", data.email_id);
    }
  }

  const { error } = await supabase.from("inbox_emails").upsert(
    {
      message_id: data.email_id ?? req.headers.get("svix-id"),
      direction: "in",
      from_addr: from.email || "(unknown sender)",
      from_name: from.name,
      to_addr: to || null,
      subject: data.subject ?? "(no subject)",
      text_body: text,
      html_body: html,
      raw: data,
      received_at: data.created_at ?? new Date().toISOString(),
    },
    { onConflict: "message_id", ignoreDuplicates: true },
  );

  if (error) {
    console.error("Failed to store inbound email:", error);
    return new Response("db error", { status: 500 }); // non-2xx → Resend retries
  }
  return Response.json({ stored: true });
});
