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
