// Client-side image compression: downscale to maxDim on the longest
// side and re-encode. Returns the original file when it's already
// small, when compression doesn't help, or for GIFs (preserves
// animation).
//
// Format is chosen from the picture, not the upload. JPEG has no alpha
// channel, so a transparent PNG re-encoded as JPEG comes back with
// every transparent pixel painted black — that's what put a dark slab
// behind the logos on the Rewards tiles. Images carrying transparency
// go to WebP instead, which keeps alpha and still compresses like a
// lossy format; everything else stays JPEG, which is smaller for
// photographs than any alpha-capable format.

// True if any pixel is not fully opaque. Reads the alpha channel rather
// than trusting the MIME type: most uploaded PNGs are screenshots or
// photos with no transparency at all, and those are much smaller as
// JPEG.
function hasAlpha(ctx, w, h) {
  try {
    const { data } = ctx.getImageData(0, 0, w, h);
    for (let i = 3; i < data.length; i += 4) {
      if (data[i] !== 255) return true;
    }
    return false;
  } catch (e) {
    // Only throws on a tainted canvas, which can't happen for a local
    // File — but if it ever does, assume alpha. Keeping transparency
    // that wasn't there is harmless; losing it is the bug above.
    return true;
  }
}

async function encode(canvas, type, quality) {
  const blob = await new Promise((r) => canvas.toBlob(r, type, quality));
  // Browsers that can't encode the requested type silently hand back a
  // PNG, so trust blob.type over what we asked for.
  return blob && blob.type === type ? blob : null;
}

export async function compressImage(file, maxDim = 1200, quality = 0.85) {
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return file;
  const bmp = await createImageBitmap(file).catch(() => null);
  if (!bmp) return file;

  const scale = Math.min(1, maxDim / Math.max(bmp.width, bmp.height));
  if (scale === 1 && file.size < 400 * 1024) {
    bmp.close?.();
    return file;
  }

  const canvas = document.createElement('canvas');
  canvas.width = Math.round(bmp.width * scale);
  canvas.height = Math.round(bmp.height * scale);
  const ctx = canvas.getContext('2d');
  ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
  bmp.close?.();

  let blob;
  if (file.type === 'image/jpeg' || !hasAlpha(ctx, canvas.width, canvas.height)) {
    // no transparency to lose
    blob = await encode(canvas, 'image/jpeg', quality);
  } else {
    // WebP first; PNG is the fallback for browsers that won't encode it
    // (lossless, so bigger, but it keeps the transparency)
    blob = await encode(canvas, 'image/webp', quality)
        || await encode(canvas, 'image/png');
  }

  return (blob && blob.size < file.size) ? blob : file;
}

// Extension matching what compressImage actually produced. Derived from
// the blob's own type — hardcoding 'jpg' would have named a WebP .jpg.
const EXT = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

export function outExt(processed, originalFile) {
  if (processed === originalFile) {
    return originalFile.name.split('.').pop().toLowerCase();
  }
  return EXT[processed.type] || 'jpg';
}
