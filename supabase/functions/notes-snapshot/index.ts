// Point-in-time backup of the Notes page. Invoked hourly by pg_cron
// (see 20261007130000_note_snapshots.sql).
//
// Writes one JSON file holding every note, folder and attendee row into the
// private note-snapshots bucket. The whole set is ~54 kB today, so a full
// dump is cheaper and far simpler to restore from than per-row deltas.
//
// Exists because deleting a note is permanent and, since interns were given
// write access to Notes, more people can do it. Nothing here is user-facing:
// recovery means reading a snapshot and putting the rows back.
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
);

const BUCKET = "note-snapshots";
const KEEP = 60;        // newest N kept; older pruned every run

// Short content hash, carried in the filename. The cron fires hourly whether
// or not anyone edited anything, and writing 24 identical files a day would
// bury the few that represent real changes — so a run that matches the
// newest snapshot writes nothing.
async function hash(s: string): Promise<string> {
  const buf = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(s));
  return [...new Uint8Array(buf)].slice(0, 6).map((b) => b.toString(16).padStart(2, "0")).join("");
}

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  const [notes, folders, attendees] = await Promise.all([
    supabase.from("notes").select("*").order("id"),
    supabase.from("note_folders").select("*").order("id"),
    supabase.from("note_attendees").select("*").order("note_id"),
  ]);

  const failed = [notes, folders, attendees].find((r) => r.error);
  if (failed) {
    console.error("notes-snapshot read failed:", failed.error);
    return Response.json({ error: failed.error!.message }, { status: 500 });
  }

  // Refuse to write an empty snapshot over a healthy history. If every note
  // really has been deleted, that is exactly when the previous snapshots
  // matter most — a run that happened to read nothing must not push them
  // out through pruning.
  if (!notes.data?.length) {
    console.warn("notes-snapshot: zero notes read, skipping");
    return Response.json({ skipped: "no notes to snapshot" });
  }

  const body = {
    counts: {
      notes: notes.data.length,
      folders: folders.data?.length ?? 0,
      attendees: attendees.data?.length ?? 0,
    },
    notes: notes.data,
    note_folders: folders.data ?? [],
    note_attendees: attendees.data ?? [],
  };

  // Hash the DATA only. taken_at is deliberately added afterwards: fold it
  // into the hash and every run differs from the last, so the skip below
  // never fires and an idle week leaves 168 identical files.
  const sig = await hash(JSON.stringify(body));
  const payload = JSON.stringify({ taken_at: new Date().toISOString(), ...body }, null, 2);

  // listed newest-first; names sort lexically by the ISO timestamp they start with
  const { data: existing } = await supabase.storage.from(BUCKET)
    .list("", { limit: 200, sortBy: { column: "name", order: "desc" } });
  const files = existing ?? [];

  if (files[0]?.name.includes(`-${sig}.json`)) {
    return Response.json({ skipped: "unchanged since last snapshot", signature: sig });
  }

  // ':' is legal in an object key but awkward in a URL and in filenames on
  // a machine someone downloads these onto
  const stamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
  const name = `notes-${stamp}-${sig}.json`;

  const { error: upErr } = await supabase.storage.from(BUCKET)
    .upload(name, new Blob([payload], { type: "application/json" }), {
      contentType: "application/json",
    });
  if (upErr) {
    console.error("notes-snapshot upload failed:", upErr);
    return Response.json({ error: upErr.message }, { status: 500 });
  }

  // prune, counting the file just written
  const stale = [name, ...files.map((f) => f.name)]
    .sort().reverse().slice(KEEP);
  if (stale.length) await supabase.storage.from(BUCKET).remove(stale);

  return Response.json({
    saved: name,
    notes: notes.data.length,
    bytes: payload.length,
    pruned: stale.length,
  });
});
