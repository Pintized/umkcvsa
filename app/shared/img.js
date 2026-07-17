// Client-side image compression: downscale to maxDim on the longest
// side and re-encode as JPEG. Returns the original file when it's
// already small, when compression doesn't help, or for GIFs
// (preserves animation).
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
  canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
  bmp.close?.();

  const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', quality));
  return (blob && blob.size < file.size) ? blob : file;
}

// Extension matching what compressImage actually produced
export function outExt(processed, originalFile) {
  return processed === originalFile
    ? originalFile.name.split('.').pop().toLowerCase()
    : 'jpg';
}
