// Avatar viewer + cropper.
//
// openAvatarEditor(ctx, onSaved) shows the member's picture large, lets them
// pick a new one, then pan/zoom it inside a circular viewport before saving.
// The crop is rasterised client-side to a square JPEG, so what lands in
// storage is exactly what was framed — no server-side processing needed.
//
// Self-contained on purpose: the portal has no build step and the pages are
// served as plain modules, so there's no cropping library to lean on.
import { supabase } from '/app/shared/supabase.js';

const AVATAR_BASE = 'https://wrlpsetbkeyoyamkopgf.supabase.co/storage/v1/object/public/avatars/';
const PLACEHOLDER = '/assets/img/logo-128.png';
const VIEW = 300;   // crop viewport, CSS px
const OUT = 512;    // exported square, matches what profile.html targets

export const avatarUrlFor = (profile) =>
  profile?.avatar_path ? AVATAR_BASE + profile.avatar_path : PLACEHOLDER;

export function openAvatarEditor(ctx, onSaved) {
  const back = document.createElement('div');
  back.className = 'modal-back';
  back.innerHTML = `
    <div class="modal av-modal">
      <h2 id="av-title">Profile picture</h2>
      <div class="notice" id="av-err"></div>

      <div id="av-view">
        <img class="av-large" id="av-img" src="${avatarUrlFor(ctx.profile)}" alt="Your profile picture">
        <p class="av-hint" id="av-hint">${ctx.profile?.avatar_path
          ? 'This is how others see you across the portal.'
          : 'You haven’t added a picture yet.'}</p>
      </div>

      <div id="av-crop" hidden>
        <div class="av-stage">
          <canvas id="av-canvas" width="${VIEW}" height="${VIEW}"></canvas>
          <div class="av-ring" aria-hidden="true"></div>
        </div>
        <label class="av-zoom">
          <span>Zoom</span>
          <input type="range" id="av-zoom" min="1" max="4" step="0.01" value="1">
        </label>
        <p class="av-hint">Drag the picture to reposition &middot; scroll or use the slider to zoom</p>
      </div>

      <input type="file" id="av-file" accept="image/png,image/jpeg,image/webp" hidden>
      <div class="row-btns" id="av-actions"></div>
    </div>`;
  document.body.appendChild(back);

  const $ = (id) => back.querySelector('#' + id);
  const err = $('av-err');
  const say = (m) => { err.textContent = m; err.className = 'notice error'; };
  const clearErr = () => { err.textContent = ''; err.className = 'notice'; };
  const close = () => { document.removeEventListener('keydown', onKey); back.remove(); };
  const onKey = (e) => { if (e.key === 'Escape') close(); };
  document.addEventListener('keydown', onKey);
  back.addEventListener('click', (e) => { if (e.target === back) close(); });

  // ---------- view mode ----------
  function showView() {
    clearErr();
    $('av-view').hidden = false;
    $('av-crop').hidden = true;
    $('av-title').textContent = 'Profile picture';
    $('av-actions').innerHTML = `
      <button type="button" class="btn ghost" id="av-close">Close</button>
      <button type="button" class="btn" id="av-pick">${ctx.profile?.avatar_path ? 'Change picture' : 'Add a picture'}</button>`;
    $('av-close').onclick = close;
    $('av-pick').onclick = () => $('av-file').click();
  }

  // ---------- crop mode ----------
  let bmp = null, scale = 1, minScale = 1, offX = 0, offY = 0, srcType = 'image/jpeg';

  const canvas = $('av-canvas');
  const dpr = Math.min(window.devicePixelRatio || 1, 3);
  canvas.width = VIEW * dpr;
  canvas.height = VIEW * dpr;
  canvas.style.width = canvas.style.height = VIEW + 'px';
  const cx = canvas.getContext('2d');

  const clamp = () => {
    const w = bmp.width * scale, h = bmp.height * scale;
    const mx = Math.max(0, (w - VIEW) / 2), my = Math.max(0, (h - VIEW) / 2);
    offX = Math.min(mx, Math.max(-mx, offX));
    offY = Math.min(my, Math.max(-my, offY));
  };

  function draw() {
    clamp();
    cx.save();
    cx.scale(dpr, dpr);
    cx.clearRect(0, 0, VIEW, VIEW);
    const w = bmp.width * scale, h = bmp.height * scale;
    cx.drawImage(bmp, VIEW / 2 - w / 2 + offX, VIEW / 2 - h / 2 + offY, w, h);
    cx.restore();
  }

  async function startCrop(file) {
    clearErr();
    if (file.size > 12 * 1024 * 1024) return say('That image is over 12 MB — try a smaller one.');
    const loaded = await createImageBitmap(file).catch(() => null);
    if (!loaded) return say('That file could not be read as an image.');
    bmp?.close?.();
    bmp = loaded;
    srcType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';

    // start at "cover" so the circle is always filled
    minScale = Math.max(VIEW / bmp.width, VIEW / bmp.height);
    scale = minScale; offX = 0; offY = 0;
    const zoom = $('av-zoom');
    zoom.min = String(minScale);
    zoom.max = String(minScale * 4);
    zoom.step = String(minScale / 100);
    zoom.value = String(minScale);

    $('av-view').hidden = true;
    $('av-crop').hidden = false;
    $('av-title').textContent = 'Position your picture';
    $('av-actions').innerHTML = `
      <button type="button" class="btn ghost" id="av-back">Cancel</button>
      <button type="button" class="btn" id="av-save">Save picture</button>`;
    $('av-back').onclick = () => { $('av-file').value = ''; showView(); };
    $('av-save').onclick = save;
    draw();
  }

  // pan
  let dragging = false, lastX = 0, lastY = 0;
  canvas.addEventListener('pointerdown', (e) => {
    dragging = true; lastX = e.clientX; lastY = e.clientY;
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    offX += e.clientX - lastX; offY += e.clientY - lastY;
    lastX = e.clientX; lastY = e.clientY;
    draw();
  });
  const endDrag = () => { dragging = false; };
  canvas.addEventListener('pointerup', endDrag);
  canvas.addEventListener('pointercancel', endDrag);

  // zoom, keeping the centre of the frame anchored
  const applyZoom = (next) => {
    const prev = scale;
    scale = Math.min(minScale * 4, Math.max(minScale, next));
    offX *= scale / prev; offY *= scale / prev;
    $('av-zoom').value = String(scale);
    draw();
  };
  $('av-zoom').addEventListener('input', (e) => applyZoom(Number(e.target.value)));
  canvas.addEventListener('wheel', (e) => {
    e.preventDefault();
    applyZoom(scale * (e.deltaY < 0 ? 1.08 : 1 / 1.08));
  }, { passive: false });

  // ---------- save ----------
  async function save() {
    const btn = $('av-save');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
      // re-render the same framing at output resolution
      const out = document.createElement('canvas');
      out.width = out.height = OUT;
      const o = out.getContext('2d');
      const k = OUT / VIEW;
      const w = bmp.width * scale * k, h = bmp.height * scale * k;
      o.drawImage(bmp, OUT / 2 - w / 2 + offX * k, OUT / 2 - h / 2 + offY * k, w, h);

      const blob = await new Promise(r => out.toBlob(r, srcType, 0.9));
      if (!blob) throw new Error('Could not process that image.');

      const ext = srcType === 'image/png' ? 'png' : 'jpg';
      const path = `${ctx.user.id}/avatar-${Date.now()}.${ext}`;
      const { error: upErr } = await supabase.storage.from('avatars')
        .upload(path, blob, { upsert: true, contentType: srcType });
      if (upErr) throw upErr;

      const prev = ctx.profile?.avatar_path;
      const { error } = await supabase.from('profiles').update({ avatar_path: path }).eq('id', ctx.user.id);
      if (error) throw error;
      if (prev) await supabase.storage.from('avatars').remove([prev]);

      ctx.profile.avatar_path = path;
      const url = AVATAR_BASE + path;
      // keep every avatar on the page in step (hero, profile header, topbar chip)
      document.querySelectorAll('.hero-avatar img, .avatar-lg img, .userchip img')
        .forEach(img => { img.src = url; });
      onSaved?.(url);
      close();
    } catch (e) {
      say(e.message || 'Could not save that picture.');
      btn.disabled = false; btn.textContent = 'Save picture';
    }
  }

  $('av-file').addEventListener('change', (e) => {
    const f = e.target.files[0];
    if (f) startCrop(f);
  });

  showView();
}
