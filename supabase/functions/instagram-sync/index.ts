// Pulls the latest @umkc_vsa posts into public.instagram_posts for the News
// page. Invoked hourly by pg_cron (see 20260902140000_instagram_feed.sql).
//
// Requires a row in public.integration_tokens with provider = 'instagram',
// holding a long-lived Instagram Graph API token. That table has RLS enabled
// with no policies, so only this function (service role) can read it — the
// token must never reach a browser.
//
// Instagram long-lived tokens expire after 60 days. This refreshes the token
// when it's within 10 days of expiry, otherwise the feed would quietly stop
// updating two months after setup.
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
);

const BUCKET = "site-images";
const KEEP = 12;               // posts shown on the News page
const GRAPH = "https://graph.instagram.com";

type Row = { access_token: string; expires_at: string | null };

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  // ---------- token ----------
  const { data: tok } = await supabase
    .from("integration_tokens").select("access_token, expires_at")
    .eq("provider", "instagram").maybeSingle<Row>();

  if (!tok?.access_token) {
    // not configured yet — the News section stays hidden, nothing is broken
    return Response.json({ skipped: "no instagram token configured" });
  }

  let token = tok.access_token;
  const expires = tok.expires_at ? new Date(tok.expires_at).getTime() : 0;
  const tenDays = 10 * 24 * 3600 * 1000;
  if (expires && expires - Date.now() < tenDays) {
    const r = await fetch(`${GRAPH}/refresh_access_token?grant_type=ig_refresh_token&access_token=${token}`);
    if (r.ok) {
      const j = await r.json();
      token = j.access_token ?? token;
      await supabase.from("integration_tokens").update({
        access_token: token,
        expires_at: new Date(Date.now() + (j.expires_in ?? 5184000) * 1000).toISOString(),
        updated_at: new Date().toISOString(),
      }).eq("provider", "instagram");
      console.log("Instagram token refreshed");
    } else {
      console.error(`Token refresh failed (${r.status}):`, await r.text());
    }
  }

  // ---------- fetch ----------
  const fields = "id,caption,media_type,media_url,permalink,thumbnail_url,timestamp";
  const res = await fetch(`${GRAPH}/me/media?fields=${fields}&limit=${KEEP}&access_token=${token}`);
  if (!res.ok) {
    console.error(`Instagram fetch failed (${res.status}):`, await res.text());
    return Response.json({ error: "instagram rejected the request" }, { status: 502 });
  }
  const posts = (await res.json()).data ?? [];
  if (!posts.length) return Response.json({ posts: 0 });

  const { data: existing } = await supabase.from("instagram_posts").select("ig_id, image_path");
  const known = new Map((existing ?? []).map((r) => [r.ig_id, r.image_path]));

  let mirrored = 0;
  for (const p of posts) {
    let image_path = known.get(p.id) ?? null;

    // Mirror once. Instagram's CDN URLs are signed and expire, so hotlinking
    // would turn every post into a broken image after a while.
    if (!image_path) {
      const src = p.media_type === "VIDEO" ? p.thumbnail_url : p.media_url;
      if (src) {
        try {
          const img = await fetch(src);
          if (img.ok) {
            const path = `ig-${p.id}.jpg`;
            const { error } = await supabase.storage.from(BUCKET)
              .upload(path, new Uint8Array(await img.arrayBuffer()), {
                contentType: "image/jpeg", upsert: true,
              });
            if (!error) { image_path = path; mirrored++; }
            else console.error("mirror upload failed:", error.message);
          }
        } catch (e) { console.error("mirror fetch failed:", String(e)); }
      }
    }

    await supabase.from("instagram_posts").upsert({
      ig_id: p.id,
      caption: p.caption ?? null,
      permalink: p.permalink,
      media_type: p.media_type ?? null,
      image_path,
      posted_at: p.timestamp ?? null,
      fetched_at: new Date().toISOString(),
    });
  }

  // ---------- prune ----------
  // Drop anything that fell out of the latest KEEP, and its mirrored file, so
  // the bucket doesn't grow without bound.
  const live = new Set(posts.map((p: { id: string }) => p.id));
  const stale = (existing ?? []).filter((r) => !live.has(r.ig_id));
  if (stale.length) {
    const paths = stale.map((r) => r.image_path).filter(Boolean) as string[];
    if (paths.length) await supabase.storage.from(BUCKET).remove(paths);
    await supabase.from("instagram_posts").delete()
      .in("ig_id", stale.map((r) => r.ig_id));
  }

  return Response.json({ posts: posts.length, mirrored, pruned: stale.length });
});
