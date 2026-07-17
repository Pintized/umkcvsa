<?php
// ============================================================
// UMKC VSA - Calendar Page
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$user = require_login();

// Current month/year from URL, or today's month/year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Basic validation
if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}

if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)$firstDay->format('t');
$startDayOfWeek = (int)$firstDay->format('w');

$prevMonthDate = $firstDay->modify('-1 month');
$nextMonthDate = $firstDay->modify('+1 month');

$monthName = $firstDay->format('F Y');

// Temporary sample events.
// Later, this can be replaced with database event + RSVP data.
$events = [];

// Merge real events from the database (app_events) into the calendar.
// Each event is keyed by its date (Y-m-d) to match the structure above.
try {
    $stmt = db()->query('SELECT name, event_date, start_time, location FROM app_events ORDER BY event_date ASC, start_time ASC');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['event_date'];
        if (!isset($events[$key])) { $events[$key] = []; }
        $events[$key][] = [
            'title'  => $row['name'],
            'rsvped' => false,
        ];
    }
} catch (Throwable $e) {
    // If the events table is unavailable, fall back to the existing sample events.
}
// Merge officer tasks that have a due date into the calendar (one-way, read-only).
try {
    $tstmt = db()->query('SELECT title, due_date FROM app_tasks WHERE due_date IS NOT NULL ORDER BY due_date ASC');
    foreach ($tstmt->fetchAll(PDO::FETCH_ASSOC) as $trow) {
        $tkey = $trow['due_date'];
        if (!isset($events[$tkey])) { $events[$tkey] = []; }
        $events[$tkey][] = [
            'title'  => 'Task: ' . $trow['title'],
            'rsvped' => false,
            'type'   => 'task',
        ];
    }
} catch (Throwable $e) {
    // If the tasks table is unavailable, just skip task markers.
}

$upcomingEvents = [];
$rsvpEvents = [];

foreach ($events as $date => $items) {
    foreach ($items as $item) {
        $eventData = [
            'date' => $date,
            'title' => $item['title'],
            'rsvped' => $item['rsvped']
        ];

        $upcomingEvents[] = $eventData;

        if ($item['rsvped']) {
            $rsvpEvents[] = $eventData;
        }
    }
}

usort($upcomingEvents, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
});

usort($rsvpEvents, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Calendar | UMKC VSA</title>
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
    html.dark-mode .calendar-card,
    html.dark-mode .events-card,
    html.dark-mode .rsvp-card { background: #16222f; border-color: #28394a; }
    html.dark-mode .day,
    html.dark-mode .empty-day { background: #16222f; border-color: #28394a; }
    html.dark-mode .day.has-rsvp { background: #2a1f24; }
    html.dark-mode .event-item { background: #1c2c3c; }
    /* dark contrast fixes */
    html.dark-mode .page-header h1 { color: var(--text); }
    html.dark-mode .day-number { color: var(--text); }
    html.dark-mode .event-title { color: var(--text); }
    html.dark-mode .weekday { color: #e6edf3; background: #0c1722; }
    html.dark-mode .card-heading,
    html.dark-mode .card-heading h2,
    html.dark-mode .calendar-heading-left h2 { color: #ffffff; }
    html.dark-mode .events-card h2,
    html.dark-mode .rsvp-card h2 { color: #ffffff; }
    html.dark-mode .page-header p,
    html.dark-mode .sub { color: var(--muted); }

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

  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/sidebar-styles.php'; ?><?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/topbar-styles.php'; ?>/* =========================
Calendar Content
========================= */

    .wrap {
    max-width: 1400px;
    margin: 40px auto;
    padding: 0 28px;
    }

    .page-header {
    margin-bottom: 24px;
    }

    .page-header h1 {
    font-family: 'Playfair Display', serif;
    color: var(--navy);
    font-size: 2rem;
    margin-bottom: 6px;
    }

    .page-header p {
    color: var(--muted);
    }

    .calendar-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 24px;
    align-items: start;
    }

    .calendar-card,
    .events-card,
    .rsvp-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 18px 50px rgba(22, 49, 77, .12);
    animation: rise .6s ease both;
    overflow: hidden;
    }

    .calendar-card {
    padding: 0;
    }

    .events-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
    }

    .events-card,
    .rsvp-card {
    padding: 0;
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

    /* Navy heading bands */

    .card-heading {
    background: var(--navy);
    color: var(--white);
    padding: 18px 24px;
    }

    .card-heading h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.45rem;
    color: var(--white);
    }

    .card-body {
    padding: 24px;
    }

    .calendar-heading-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    }

    .calendar-heading-left h2 {
    font-family: 'Playfair Display', serif;
    color: var(--white);
    font-size: 1.75rem;
    margin-bottom: 8px;
    }

    .calendar-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    color: rgba(255, 255, 255, .85);
    font-size: .9rem;
    font-weight: 700;
    }

    .legend-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    }

    .legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e3eef9;
    border: 1px solid #c8d8e8;
    }

    .legend-dot.rsvp {
    background: var(--red);
    border-color: var(--red);
    }

    .calendar-nav {
    display: flex;
    gap: 8px;
    align-items: center;
    }

    .calendar-arrow,
    .today-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    text-decoration: none;
    font-weight: 800;
    border: 1.5px solid rgba(255, 255, 255, .45);
    border-radius: 10px;
    transition: .2s ease;
    }

    .calendar-arrow {
    width: 42px;
    height: 38px;
    font-size: 1.25rem;
    }

    .today-link {
    height: 38px;
    padding: 0 14px;
    font-size: .95rem;
    }

    .calendar-arrow:hover,
    .today-link:hover {
    background: var(--white);
    color: var(--navy);
    border-color: var(--white);
    }

    /* Wider calendar */

    .calendar-scroll {
    width: 100%;
    overflow-x: auto;
    }

    .calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(135px, 1fr));
    border: 1px solid #e3ebf3;
    border-radius: 14px;
    overflow: hidden;
    min-width: 945px;
    }

    .weekday {
    background: var(--navy);
    color: var(--white);
    font-weight: 700;
    text-align: center;
    padding: 12px 6px;
    font-size: .92rem;
    }

    .day {
    min-height: 130px;
    background: #fff;
    border-right: 1px solid #e3ebf3;
    border-bottom: 1px solid #e3ebf3;
    padding: 11px;
    overflow: hidden;
    }

    .day:nth-child(7n) {
    border-right: none;
    }

    .empty-day {
    background: #f6f9fc;
    }

    .day.has-rsvp {
    background: #fff8f9;
    box-shadow: inset 0 0 0 2px rgba(200, 32, 47, .14);
    }

    .day-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 8px;
    }

    .today .day-number {
    background: var(--navy);
    color: var(--white);
    }

    /* Event pills */

    .event-pill {
    display: block;
    width: 100%;
    background: #e3eef9;
    color: var(--navy);
    font-size: .8rem;
    font-weight: 700;
    line-height: 1.2;
    padding: 7px 8px;
    border-radius: 8px;
    margin-top: 6px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    position: relative;
    }

    .event-pill.rsvped {
    background: var(--red);
    color: var(--white);
    }

    .event-pill .event-text {
    display: inline-block;
    min-width: 100%;
    white-space: nowrap;
    }

    /* Only animate long event names */
    .event-pill.long-event .event-text {
    animation: subtleSlide 7s ease-in-out infinite;
    }

    @keyframes subtleSlide {
    0% {
        transform: translateX(0);
    }

    18% {
        transform: translateX(0);
    }

    55% {
        transform: translateX(calc(-100% + 115px));
    }

    75% {
        transform: translateX(calc(-100% + 115px));
    }

    100% {
        transform: translateX(0);
    }
    }

    .event-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    }

    .event-item {
    border-left: 4px solid var(--navy);
    background: var(--light);
    border-radius: 10px;
    padding: 12px 14px;
    }

    .event-item.rsvped {
    border-left-color: var(--red);
    background: #fff5f6;
    }

    .event-date {
    color: var(--muted);
    font-size: .85rem;
    font-weight: 700;
    margin-bottom: 3px;
    }

    .event-title {
    color: var(--navy);
    font-weight: 700;
    }

    .rsvp-tag {
    display: inline-block;
    margin-top: 7px;
    background: var(--red);
    color: var(--white);
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 3px 8px;
    border-radius: 999px;
    }

    .empty-events {
    color: var(--muted);
    font-size: .95rem;
    }

  /* =========================
     Responsive
  ========================= */

  @media (max-width: 1050px) {
    .calendar-layout {
      grid-template-columns: 1fr;
    }

    .events-sidebar {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 760px) {
    .events-sidebar {
      grid-template-columns: 1fr;
    }
  }

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

    .calendar-card,
    .events-card,
    .rsvp-card {
      padding: 20px;
    }

    .calendar-header {
      flex-direction: column;
      align-items: flex-start;
    }

    .calendar-grid {
      overflow-x: auto;
    }

    .weekday,
    .day {
      min-width: 105px;
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

  <!-- Calendar Content -->
  <main class="wrap">
    <div class="page-header">
      <h1>Calendar</h1>
      <p>View upcoming UMKC VSA meetings, socials, volunteer opportunities, and your RSVP’d events.</p>
    </div>

    <div class="calendar-layout">
        <section class="calendar-card">
        <div class="card-heading">
            <div class="calendar-heading-row">
            <div class="calendar-heading-left">
                <h2><?= e($monthName) ?></h2>

                <div class="calendar-legend">
                <span class="legend-item">
                    <span class="legend-dot"></span>
                    Event
                </span>

                <span class="legend-item">
                    <span class="legend-dot rsvp"></span>
                    RSVP’d Event
                </span>
                </div>
            </div>

            <div class="calendar-nav">
                <a
                class="calendar-arrow"
                href="?month=<?= (int)$prevMonthDate->format('n') ?>&year=<?= (int)$prevMonthDate->format('Y') ?>"
                aria-label="Previous month"
                title="Previous month"
                >
                ←
                </a>

                <a
                class="today-link"
                href="?month=<?= (int)date('n') ?>&year=<?= (int)date('Y') ?>"
                >
                Today
                </a>

                <a
                class="calendar-arrow"
                href="?month=<?= (int)$nextMonthDate->format('n') ?>&year=<?= (int)$nextMonthDate->format('Y') ?>"
                aria-label="Next month"
                title="Next month"
                >
                →
                </a>
            </div>
            </div>
        </div>

        <div class="card-body">
            <div class="calendar-scroll">
            <div class="calendar-grid">
                <div class="weekday">Sun</div>
                <div class="weekday">Mon</div>
                <div class="weekday">Tue</div>
                <div class="weekday">Wed</div>
                <div class="weekday">Thu</div>
                <div class="weekday">Fri</div>
                <div class="weekday">Sat</div>

                <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                <div class="day empty-day"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                    $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = $dateKey === date('Y-m-d');
                    $dayEvents = $events[$dateKey] ?? [];

                    $hasRsvp = false;

                    foreach ($dayEvents as $event) {
                        if (!empty($event['rsvped'])) {
                            $hasRsvp = true;
                            break;
                        }
                    }
                ?>

                <div class="day <?= $isToday ? 'today' : '' ?> <?= $hasRsvp ? 'has-rsvp' : '' ?>">
                    <div class="day-number"><?= $day ?></div>

                    <?php foreach ($dayEvents as $event): ?>
                    <?php $isLongEvent = strlen($event['title']) > 18; ?>

                    <span class="event-pill <?= !empty($event['rsvped']) ? 'rsvped' : '' ?> <?= $isLongEvent ? 'long-event' : '' ?>">
                        <span class="event-text"><?= e($event['title']) ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endfor; ?>
            </div>
            </div>
        </div>
        </section>

      <aside class="events-sidebar">
        <section class="rsvp-card">
        <div class="card-heading">
            <h2>My RSVPs This Month</h2>
        </div>

        <div class="card-body">
            <?php if (!empty($rsvpEvents)): ?>
            <div class="event-list">
                <?php foreach ($rsvpEvents as $event): ?>
                <div class="event-item rsvped">
                    <div class="event-date">
                    <?= e(date('F j, Y', strtotime($event['date']))) ?>
                    </div>

                    <div class="event-title">
                    <?= e($event['title']) ?>
                    </div>

                    <span class="rsvp-tag">RSVP’d</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="empty-events">You have not RSVP’d to any events this month.</p>
            <?php endif; ?>
        </div>
        </section>

        <section class="events-card">
        <div class="card-heading">
            <h2>Upcoming Events</h2>
        </div>

        <div class="card-body">
            <?php if (!empty($upcomingEvents)): ?>
            <div class="event-list">
                <?php foreach ($upcomingEvents as $event): ?>
                <div class="event-item <?= !empty($event['rsvped']) ? 'rsvped' : '' ?>">
                    <div class="event-date">
                    <?= e(date('F j, Y', strtotime($event['date']))) ?>
                    </div>

                    <div class="event-title">
                    <?= e($event['title']) ?>
                    </div>

                    <?php if (!empty($event['rsvped'])): ?>
                    <span class="rsvp-tag">RSVP’d</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="empty-events">No events have been added yet.</p>
            <?php endif; ?>
        </div>
        </section>
      </aside>
    </div>
  </main>

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
    (function(){
      var officerToggle = document.getElementById("officerToggle");
      var officerSubmenu = document.getElementById("officerSubmenu");
      if (officerToggle && officerSubmenu) {
        officerToggle.addEventListener("click", function(){
          officerSubmenu.classList.toggle("open");
        });
      }
    })();
  </script>

  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/officer-panel.php'; ?>
  <?php include $_SERVER['DOCUMENT_ROOT'].'/app/partials/theme-script.php'; ?>
</body>
</html>