// Reusable pan/zoom image cropper.
//
// cropImage({ file, aspect, maxOut, round }) opens a modal, lets the user
// position the picture inside a crop frame, and resolves to
//   { blob, type, width, height, ratio }   — or null if they cancel.
//
// It deliberately does NOT touch storage or the database; the caller decides
// where the blob goes. That's what lets the avatar editor and the About-page
// editor share it despite writing to different buckets and tables.
//
// aspect: a number locks the frame to that width/height ratio (1 = square).
//         null gives a free crop — the frame itself is drag-resizable from its
//         corners, so the exported ratio is whatever the user drew.
//
// Self-contained on purpose: the portal has no build step and pages are served
// as plain modules, so there is no cropping library to lean on.

const STAGE = 360;          // working area, CSS px

export function cropImage({ file, aspect = null, maxOut = 1600, round = false, title = 'Position your image' } = {}) {
  return new Promise(async (resolve) => {
    const bmp = await createImageBitmap(file).catch(() => null);
    if (!bmp) { resolve(null); return; }

    const back = document.createElement('div');
    back.className = 'modal-back';
    back.innerHTML = `
      <div class="modal av-modal">
        <h2>${title}</h2>
        <div class="notice" id="cr-err"></div>
        <div class="cr-stage" id="cr-stage" style="width:${STAGE}px; height:${STAGE}px">
          <canvas id="cr-canvas"></canvas>
          <div class="cr-frame" id="cr-frame">
            ${aspect ? '' : `
              <span class="cr-h nw" data-h="nw"></span><span class="cr-h ne" data-h="ne"></span>
              <span class="cr-h sw" data-h="sw"></span><span class="cr-h se" data-h="se"></span>`}
          </div>
        </div>
        <label class="av-zoom">
          <span>Zoom</span>
          <input type="range" id="cr-zoom" min="1" max="4" step="0.01" value="1">
        </label>
        <p class="av-hint">Drag the picture to reposition${aspect ? '' : ' · drag the frame corners to resize the crop'}</p>
        <div class="row-btns">
          <button type="button" class="btn ghost" id="cr-cancel">Cancel</button>
          <button type="button" class="btn" id="cr-save">Use this image</button>
        </div>
      </div>`;
    document.body.appendChild(back);

    const $ = (id) => back.querySelector('#' + id);
    const canvas = $('cr-canvas');
    const frameEl = $('cr-frame');
    if (round) frameEl.classList.add('is-round');

    const dpr = Math.min(window.devicePixelRatio || 1, 3);
    canvas.width = STAGE * dpr;
    canvas.height = STAGE * dpr;
    canvas.style.width = canvas.style.height = STAGE + 'px';
    const cx = canvas.getContext('2d');

    // crop frame in stage coordinates
    let frame;
    if (aspect) {
      const w = aspect >= 1 ? STAGE : STAGE * aspect;
      const h = aspect >= 1 ? STAGE / aspect : STAGE;
      frame = { x: (STAGE - w) / 2, y: (STAGE - h) / 2, w, h };
    } else {
      frame = { x: STAGE * .08, y: STAGE * .16, w: STAGE * .84, h: STAGE * .68 };
    }

    // start at "cover" for the frame so it's always filled
    let minScale = Math.max(frame.w / bmp.width, frame.h / bmp.height);
    let scale = minScale, offX = 0, offY = 0;

    const clampOffsets = () => {
      const w = bmp.width * scale, h = bmp.height * scale;
      const mx = Math.max(0, (w - frame.w) / 2), my = Math.max(0, (h - frame.h) / 2);
      offX = Math.min(mx, Math.max(-mx, offX));
      offY = Math.min(my, Math.max(-my, offY));
    };

    function draw() {
      clampOffsets();
      cx.save();
      cx.scale(dpr, dpr);
      cx.clearRect(0, 0, STAGE, STAGE);
      const w = bmp.width * scale, h = bmp.height * scale;
      const cxp = frame.x + frame.w / 2, cyp = frame.y + frame.h / 2;
      cx.drawImage(bmp, cxp - w / 2 + offX, cyp - h / 2 + offY, w, h);
      cx.restore();
      Object.assign(frameEl.style, {
        left: frame.x + 'px', top: frame.y + 'px',
        width: frame.w + 'px', height: frame.h + 'px',
      });
    }

    // keep the picture covering the frame after the frame is resized
    const refit = () => {
      minScale = Math.max(frame.w / bmp.width, frame.h / bmp.height);
      if (scale < minScale) scale = minScale;
      const z = $('cr-zoom');
      z.min = String(minScale); z.max = String(minScale * 4);
      z.step = String(minScale / 100); z.value = String(scale);
      draw();
    };
    refit();

    // ---- pan ----
    let panning = false, lastX = 0, lastY = 0;
    canvas.addEventListener('pointerdown', (e) => {
      panning = true; lastX = e.clientX; lastY = e.clientY;
      canvas.setPointerCapture?.(e.pointerId);
    });
    canvas.addEventListener('pointermove', (e) => {
      if (!panning) return;
      offX += e.clientX - lastX; offY += e.clientY - lastY;
      lastX = e.clientX; lastY = e.clientY;
      draw();
    });
    const endPan = () => { panning = false; };
    canvas.addEventListener('pointerup', endPan);
    canvas.addEventListener('pointercancel', endPan);

    // ---- zoom ----
    const applyZoom = (next) => {
      const prev = scale;
      scale = Math.min(minScale * 4, Math.max(minScale, next));
      offX *= scale / prev; offY *= scale / prev;
      $('cr-zoom').value = String(scale);
      draw();
    };
    $('cr-zoom').addEventListener('input', (e) => applyZoom(Number(e.target.value)));
    canvas.addEventListener('wheel', (e) => {
      e.preventDefault();
      applyZoom(scale * (e.deltaY < 0 ? 1.08 : 1 / 1.08));
    }, { passive: false });

    // ---- free-crop frame handles ----
    if (!aspect) {
      let dragH = null, start = null;
      frameEl.addEventListener('pointerdown', (e) => {
        const h = e.target.closest('[data-h]');
        if (!h) return;
        e.stopPropagation();
        dragH = h.dataset.h;
        // px/py are the pointer origin; x/y/w/h come from the frame. Keeping
        // them under separate names matters — spreading frame over x/y would
        // clobber the pointer origin and the deltas would be nonsense.
        start = { px: e.clientX, py: e.clientY, ...frame };
        h.setPointerCapture?.(e.pointerId);
      });
      window.addEventListener('pointermove', (e) => {
        if (!dragH) return;
        const dx = e.clientX - start.px, dy = e.clientY - start.py;
        const MIN = 60;
        let { x, y, w, h } = start;
        if (dragH.includes('w')) { x = start.x + dx; w = start.w - dx; }
        if (dragH.includes('e')) { w = start.w + dx; }
        if (dragH.includes('n')) { y = start.y + dy; h = start.h - dy; }
        if (dragH.includes('s')) { h = start.h + dy; }
        // keep inside the stage and above the minimum
        if (w < MIN) { if (dragH.includes('w')) x = start.x + start.w - MIN; w = MIN; }
        if (h < MIN) { if (dragH.includes('n')) y = start.y + start.h - MIN; h = MIN; }
        if (x < 0) { w += x; x = 0; }
        if (y < 0) { h += y; y = 0; }
        if (x + w > STAGE) w = STAGE - x;
        if (y + h > STAGE) h = STAGE - y;
        frame = { x, y, w, h };
        refit();
      });
      window.addEventListener('pointerup', () => { dragH = null; });
    }

    // ---- finish ----
    const close = (val) => {
      document.removeEventListener('keydown', onKey);
      back.remove();
      bmp.close?.();
      resolve(val);
    };
    const onKey = (e) => { if (e.key === 'Escape') close(null); };
    document.addEventListener('keydown', onKey);
    $('cr-cancel').onclick = () => close(null);
    back.addEventListener('click', (e) => { if (e.target === back) close(null); });

    $('cr-save').onclick = async () => {
      const btn = $('cr-save');
      btn.disabled = true; btn.textContent = 'Working…';
      try {
        // render the framed region at output resolution
        const k = Math.min(maxOut / frame.w, maxOut / frame.h, 4);
        const outW = Math.round(frame.w * k), outH = Math.round(frame.h * k);
        const out = document.createElement('canvas');
        out.width = outW; out.height = outH;
        const o = out.getContext('2d');
        const w = bmp.width * scale * k, h = bmp.height * scale * k;
        o.drawImage(bmp, outW / 2 - w / 2 + offX * k, outH / 2 - h / 2 + offY * k, w, h);

        const type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
        const blob = await new Promise(r => out.toBlob(r, type, 0.9));
        if (!blob) throw new Error('Could not process that image.');
        close({ blob, type, width: outW, height: outH, ratio: +(outW / outH).toFixed(4) });
      } catch (err) {
        const n = $('cr-err');
        n.textContent = err.message || 'Could not process that image.';
        n.className = 'notice error';
        btn.disabled = false; btn.textContent = 'Use this image';
      }
    };
  });
}
