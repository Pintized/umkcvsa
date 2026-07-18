// Include as a classic (non-module) script in <head> to avoid a
// flash of the wrong theme. Mirrors legacy theme-script.php.
(function () {
  try {
    if (localStorage.getItem('vsa-theme') === 'dark') {
      document.documentElement.classList.add('dark-mode');
    }
  } catch (e) { /* private mode */ }
})();
