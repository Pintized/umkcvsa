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


