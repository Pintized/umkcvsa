<?php /* UMKC VSA - Dark Mode toggle behavior. Include before </body> on every dashboard page. */ ?>
<script>
(function(){
  var t = document.getElementById('darkModeToggle');
  if (!t) return;
  t.checked = document.documentElement.classList.contains('dark-mode');
  t.addEventListener('change', function(){
    if (t.checked) {
      document.documentElement.classList.add('dark-mode');
      try { localStorage.setItem('vsa-theme', 'dark'); } catch(e){}
    } else {
      document.documentElement.classList.remove('dark-mode');
      try { localStorage.setItem('vsa-theme', 'light'); } catch(e){}
    }
  });
})();
</script>
