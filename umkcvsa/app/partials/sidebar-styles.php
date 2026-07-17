/* ============================================================
   Shared sidebar styles - single source of truth.
   Included inside each page's style block (see partials).
   ============================================================ */
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

  