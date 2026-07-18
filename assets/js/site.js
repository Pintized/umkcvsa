// UMKC VSA public site — shared behavior
(function () {
  // reveal-on-scroll
  var obs = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
    });
  }, { threshold: .12 });
  document.querySelectorAll('.reveal').forEach(function (el, i) {
    el.style.transitionDelay = (i % 3 * .08) + 's';
    obs.observe(el);
  });

  // mobile nav
  var burger = document.querySelector('.nav-burger');
  var links = document.querySelector('.navlinks');
  if (burger && links) {
    burger.addEventListener('click', function () { links.classList.toggle('open'); });
  }
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
          { duration: 520, easing: 'cubic-bezier(.25, .8, .3, 1)', pseudoElement: '::view-transition-new(root)' }
        );
      });
    });
  } catch (err) { /* older browsers: instant navigation, no harm */ }
})();
