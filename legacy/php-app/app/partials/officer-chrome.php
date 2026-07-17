<?php if (!isset($officerActive)) { $officerActive = ''; } ?>
<style>
  :root {
    --navy: #16314d;
    --red: #c8202f;
    --light: #eef3f8;
    --text: #1f2933;
    --muted: #5b6b7b;
    --white: #ffffff;
    --sidebar-width: 270px;
  }
    html.dark-mode .card { background: #16222f; border-color: #28394a; }
    html.dark-mode .stat { background: #1c2c3c; }
    html.dark-mode .head h1,
    html.dark-mode .page-header h1,
    html.dark-mode h1, html.dark-mode h2, html.dark-mode h3 { color: var(--text); }
    html.dark-mode .badge.member { background: #20364a; color: #cfe0f0; }

  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: 'Source Sans 3', sans-serif;
    color: var(--text);
    background: var(--light);
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* =========================
     Sidebar
  ========================= */

  .menu-toggle {
    position: absolute;
    top: 12px;
    left: 22px;
    z-index: 1200;
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 10px;
    background: var(--navy);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    padding: 0 10px;
    box-shadow: 0 8px 24px rgba(22, 49, 77, .25);
  }

  .menu-toggle span {
    display: block;
    height: 3px;
    width: 100%;
    background: var(--white);
    border-radius: 999px;
    transition: .25s ease;
  }

  .menu-toggle.active span:nth-child(1) {
    transform: translateY(8px) rotate(45deg);
  }

  .menu-toggle.active span:nth-child(2) {
    opacity: 0;
  }

  .menu-toggle.active span:nth-child(3) {
    transform: translateY(-8px) rotate(-45deg);
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1100;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--navy);
    color: var(--white);
    padding: 88px 18px 24px;
    transform: translateX(-100%);
    transition: transform .28s ease;
    box-shadow: 18px 0 45px rgba(22, 49, 77, .22);
    overflow-y: auto;
  }

  .sidebar.open {
    transform: translateX(0);
  }

  .sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    padding: 0 8px;
  }

  .sidebar-logo img {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--white);
  }

  .sidebar-logo span {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: rgba(255, 255, 255, .9);
  }

  .back-site-link {
    display: block;
    color: rgba(255, 255, 255, .9);
    text-decoration: none;
    font-size: .95rem;
    font-weight: 700;
    margin: 0 8px 18px;
    padding: 9px 12px;
    border-radius: 10px;
    transition: .2s ease;
  }

  .back-site-link:hover {
    background: rgba(255, 255, 255, .12);
    color: var(--white);
  }

  .menu-section {
    border-top: 1px solid rgba(255, 255, 255, .18);
    padding-top: 12px;
    margin-top: 12px;
  }

  .section-toggle {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    color: rgba(255, 255, 255, .75);
    background: transparent;
    border: none;
    border-radius: 10px;
    padding: 9px 10px;
    margin-bottom: 6px;
    font-family: inherit;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .09em;
    cursor: pointer;
    transition: .2s ease;
  }

  .section-toggle:hover {
    background: rgba(255, 255, 255, .1);
    color: var(--white);
  }

  .section-toggle .arrow {
    font-size: .9rem;
    transition: transform .25s ease;
  }

  .section-toggle.open .arrow {
    transform: rotate(180deg);
  }

  .section-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height .3s ease;
  }

  .section-content.open {
    max-height: 420px;
  }

  .menu-link {
    width: 100%;
    display: flex;
    align-items: center;
    color: var(--white);
    text-decoration: none;
    font-family: inherit;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 10px;
    padding: 11px 12px;
    cursor: pointer;
    transition: .2s ease;
  }

  .menu-link:hover {
    background: rgba(255, 255, 255, .12);
  }

  .menu-link.active {
    background: var(--red);
  }

  .sidebar-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: rgba(22, 49, 77, .38);
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s ease;
  }

  .sidebar-overlay.show {
    opacity: 1;
    pointer-events: auto;
  }

  /* =========================
     Topbar
  ========================= */

  .topbar {
    position: relative;
    background: var(--navy);
    color: #fff;
    padding: 14px 32px 14px 86px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .topbar::after { content:''; position:absolute; left:0; right:0; bottom:-2px; height:2px; z-index:5; background:linear-gradient(90deg, #1f6feb 0%, #2f9bff 25%, #7fdcff 50%, #2f9bff 75%, #1f6feb 100%); background-size:200% 100%; box-shadow:0 0 6px rgba(47,155,255,.7), 0 0 12px rgba(127,220,255,.4); }

  .topbar .left {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .topbar img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
  }

  .topbar .name {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
  }

  .topbar a {
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    font-size: .95rem;
    border: 1.5px solid rgba(255, 255, 255, .5);
    padding: 7px 16px;
    border-radius: 8px;
    transition: .2s;
  }

  .topbar a:hover {
    background: #fff;
    color: var(--navy);
  }

  /* =========================
     Profile Content
  ========================= */

  .wrap {
    max-width: 760px;
    margin: 40px auto;
    padding: 0 24px;
  }

  .card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 18px 50px rgba(22, 49, 77, .12);
    padding: 40px;
    animation: rise .6s ease both;
  }

  @keyframes rise {
    from {
      opacity: 0;
      transform: translateY(24px);
    }

    to {
      opacity: 1;
      transform: none;
    }
  }

  .head {
    display: flex;
    align-items: center;
    gap: 24px;
    border-bottom: 1px solid #e6edf4;
    padding-bottom: 24px;
    margin-bottom: 24px;
  }

  .avatar {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--light);
  }

  .head h1 {
    font-family: 'Playfair Display', serif;
    color: var(--navy);
    font-size: 1.8rem;
  }

  .head .email {
    color: var(--muted);
  }

  .badge {
    display: inline-block;
    margin-top: 6px;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .badge.member {
    background: #e3eef9;
    color: var(--navy);
  }

  .badge.admin {
    background: #fdecee;
    color: var(--red);
  }
        .badge.officer {
            background: #e9f3ea;
            color: #1f6f33;
        }
        .badge.alumni {
            background: #f3eee0;
            color: #7a5b1f;
        }
        .badge.intern {
            background: #eceff5;
            color: #3a4a6b;
        }
        .role-badges {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .role-badges .badge {
            margin-top: 6px;
        }

  .stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
  }

  .stat {
    background: var(--light);
    border-radius: 12px;
    padding: 22px;
    text-align: center;
    transition: transform .2s;
  }

  .stat:hover {
    transform: translateY(-4px);
  }

  .stat .num {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: var(--red);
  }

  .stat .lbl {
    color: var(--muted);
    font-size: .85rem;
    margin-top: 4px;
  }

  .note {
    margin-top: 28px;
    color: var(--muted);
    font-size: .85rem;
    text-align: center;
  }

  /* =========================
     Responsive
  ========================= */

  @media (max-width: 680px) {
    .topbar {
      padding-right: 18px;
    }

    .topbar .name {
      font-size: 1rem;
    }

    .topbar a {
      padding: 6px 12px;
      font-size: .85rem;
    }

    .wrap {
      margin: 28px auto;
      padding: 0 16px;
    }

    .card {
      padding: 28px 22px;
    }

    .head {
      flex-direction: column;
      text-align: center;
    }

    .stats {
      grid-template-columns: 1fr;
    }
  }
  /* Dark Mode toggle switch (parity with user pages) */
  .theme-row { display: flex; align-items: center; justify-content: space-between; cursor: default; }
  .theme-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex: none; }
  .theme-switch input { opacity: 0; width: 0; height: 0; }
  .theme-slider { position: absolute; cursor: pointer; inset: 0; background: rgba(255,255,255,0.25); border-radius: 22px; transition: background .2s ease; }
  .theme-slider:before { content: ""; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: transform .2s ease; }
  .theme-switch input:checked + .theme-slider { background: var(--red); }
  .theme-switch input:checked + .theme-slider:before { transform: translateX(20px); }
  </style>
  

  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar Menu -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar.php'; ?>

  <!-- Top Bar -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar.php'; ?>
  <script>
    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    const mainMenuToggle = document.getElementById("mainMenuToggle");
    const mainMenuContent = document.getElementById("mainMenuContent");

    const profileToggle = document.getElementById("profileToggle");
    const profileSubmenu = document.getElementById("profileSubmenu");

    function openSidebar() {
      sidebar.classList.add("open");
      sidebarOverlay.classList.add("show");
      menuToggle.classList.add("active");
      menuToggle.setAttribute("aria-expanded", "true");
    }

    function closeSidebar() {
      sidebar.classList.remove("open");
      sidebarOverlay.classList.remove("show");
      menuToggle.classList.remove("active");
      menuToggle.setAttribute("aria-expanded", "false");
    }

    function toggleSection(button, content) {
      button.classList.toggle("open");
      content.classList.toggle("open");
    }

    menuToggle.addEventListener("click", () => {
      const isOpen = sidebar.classList.contains("open");

      if (isOpen) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    sidebarOverlay.addEventListener("click", closeSidebar);

    mainMenuToggle.addEventListener("click", () => {
      toggleSection(mainMenuToggle, mainMenuContent);
    });

    profileToggle.addEventListener("click", () => {
      toggleSection(profileToggle, profileSubmenu);
    });

    // Officer section toggle (only present for officers)
    const officerToggle = document.getElementById("officerToggle");
    const officerSubmenu = document.getElementById("officerSubmenu");
    if (officerToggle && officerSubmenu) {
      officerToggle.addEventListener("click", () => {
        toggleSection(officerToggle, officerSubmenu);
      });
    }
  </script>
<?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-panel.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-script.php'; ?>