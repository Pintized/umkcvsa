<?php
// ============================================================
// UMKC VSA - Member profile / dashboard
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$user = require_login();

$pic = !empty($user['profile_pic'])
    ? UPLOAD_URL . rawurlencode($user['profile_pic'])
    : '/assets/logo.png';

$joined = date('F j, Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Family | UMKC VSA</title>
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-head.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">

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

  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar-styles.php'; ?><?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar-styles.php'; ?>  /* =========================
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
  </style>
</head>

<body>

  

  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar Menu -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar.php'; ?>

  <!-- Top Bar -->
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar.php'; ?>

  <!-- Main Content -->
  <div class="wrap">
    <div class="card">
      <h1 style="margin-top:0;">Family</h1>
      <p style="color:var(--muted);">View and manage your VSA family group.</p>
    </div>
    <div class="card" style="margin-top:18px; text-align:center; padding:48px 24px;">
      <p style="color:var(--muted); font-size:15px;">This page is coming soon.</p>
    </div>
  </div>
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
</body>
</html>