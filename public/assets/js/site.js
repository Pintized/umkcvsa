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

  // logged-in visitors get "Account Portal" instead of "Login".
  // Reads the supabase session straight from localStorage — no SDK
  // needed just to relabel a button. A refresh token means the portal
  // can restore the session even if the access token has expired.
  try {
    var key = Object.keys(localStorage).find(function (k) {
      return k.indexOf('sb-') === 0 && k.indexOf('-auth-token') > 0;
    });
    var sess = key && JSON.parse(localStorage.getItem(key));
    if (sess && (sess.refresh_token || (sess.expires_at && sess.expires_at * 1000 > Date.now()))) {
      document.querySelectorAll('a.login').forEach(function (a) {
        a.textContent = 'Account Portal';
        a.href = '/app/';
      });
    }
  } catch (e) {}
})();
