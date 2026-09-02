// Avatar viewer + cropper.
//
// openAvatarEditor(ctx, onSaved) shows the member's picture large and lets them
// replace it. The crop itself is delegated to the shared image-cropper — this
// module only supplies the square/round options and owns the storage + profile
// write, which the cropper deliberately stays out of.
import { supabase } from '/app/shared/supabase.js';
import { cropImage } from '/app/shared/image-cropper.js';

const AVATAR_BASE = 'https://wrlpsetbkeyoyamkopgf.supabase.co/storage/v1/object/public/avatars/';
const PLACEHOLDER = '/assets/img/logo-128.png';
const OUT = 512;    // exported square, matches what profile.html targets

export const avatarUrlFor = (profile) =>
  profile?.avatar_path ? AVATAR_BASE + profile.avatar_path : PLACEHOLDER;

export function openAvatarEditor(ctx, onSaved) {
  const back = document.createElement('div');
  back.className = 'modal-back';
  back.innerHTML = `
    <div class="modal av-modal">
      <h2>Profile picture</h2>
      <div class="notice" id="av-err"></div>
      <img class="av-large" id="av-img" src="${avatarUrlFor(ctx.profile)}" alt="Your profile picture">
      <p class="av-hint">${ctx.profile?.avatar_path
        ? 'This is how others see you across the portal.'
        : 'You haven’t added a picture yet.'}</p>
      <input type="file" id="av-file" accept="image/png,image/jpeg,image/webp" hidden>
      <div class="row-btns">
        <button type="button" class="btn ghost" id="av-close">Close</button>
        <button type="button" class="btn" id="av-pick">${ctx.profile?.avatar_path ? 'Change picture' : 'Add a picture'}</button>
      </div>
    </div>`;
  document.body.appendChild(back);

  const $ = (id) => back.querySelector('#' + id);
  const err = $('av-err');
  const say = (m) => { err.textContent = m; err.className = 'notice error'; };
  const close = () => { document.removeEventListener('keydown', onKey); back.remove(); };
  const onKey = (e) => { if (e.key === 'Escape') close(); };
  document.addEventListener('keydown', onKey);
  back.addEventListener('click', (e) => { if (e.target === back) close(); });

  $('av-close').onclick = close;
  $('av-pick').onclick = () => $('av-file').click();

  $('av-file').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    err.textContent = ''; err.className = 'notice';
    if (file.size > 12 * 1024 * 1024) return say('That image is over 12 MB — try a smaller one.');

    // square + circular mask keeps the original avatar behaviour
    const res = await cropImage({
      file, aspect: 1, maxOut: OUT, round: true, title: 'Position your picture',
    });
    if (!res) return;

    const btn = $('av-pick');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
      const ext = res.type === 'image/png' ? 'png' : 'jpg';
      const path = `${ctx.user.id}/avatar-${Date.now()}.${ext}`;
      const { error: upErr } = await supabase.storage.from('avatars')
        .upload(path, res.blob, { upsert: true, contentType: res.type });
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
    } catch (e2) {
      say(e2.message || 'Could not save that picture.');
      btn.disabled = false; btn.textContent = 'Change picture';
    }
  });
}
