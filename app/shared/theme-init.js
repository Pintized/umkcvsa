// Include as a classic (non-module) script in <head> to avoid a
// flash of the wrong theme. Mirrors legacy theme-script.php.
(function () {
  try {
    if (localStorage.getItem('vsa-theme') === 'dark') {
      document.documentElement.classList.add('dark-mode');
    }
  } catch (e) { /* private mode */ }
})();

// page transitions: circular reveal expanding from the clicked element
(function () {
  try {
    addEventListener('click', function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
      if (!a || a.target === '_blank' || a.origin !== location.origin) return;
      sessionStorage.setItem('vt-origin', JSON.stringify({ x: e.clientX, y: e.clientY }));
    }, true);
    addEventListener('pagereveal', function (e) {
      if (!e.viewTransition) return;
      var o = null;
      try { o = JSON.parse(sessionStorage.getItem('vt-origin') || 'null'); sessionStorage.removeItem('vt-origin'); } catch (err) {}
      var x = o ? o.x : innerWidth / 2;
      var y = o ? o.y : 64;
      var r = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
      e.viewTransition.ready.then(function () {
        document.documentElement.animate(
          { clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + r + 'px at ' + x + 'px ' + y + 'px)'] },
          { duration: 480, easing: 'cubic-bezier(.25, .8, .3, 1)', pseudoElement: '::view-transition-new(root)' }
        );
      });
    });
  } catch (err) { /* older browsers: instant navigation, no harm */ }
})();
