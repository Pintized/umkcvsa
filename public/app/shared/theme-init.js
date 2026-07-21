// Include as a classic (non-module) script in <head> to avoid a
// flash of the wrong theme. Mirrors legacy theme-script.php.
// 'vsa-theme' is 'light', 'dark', or 'system' (follow the OS).
(function () {
  try {
    var t = localStorage.getItem('vsa-theme');
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var apply = function () {
      var dark = t === 'dark' || (t === 'system' && mq.matches);
      document.documentElement.classList.toggle('dark-mode', dark);
    };
    apply();
    if (t === 'system' && mq.addEventListener) mq.addEventListener('change', apply);
  } catch (e) { /* private mode */ }
})();
