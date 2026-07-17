<?php
// ============================================================
// UMKC VSA - Log in
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MIN    = 30;

function login_lock_seconds_left(string $email): int {
    $stmt = db()->prepare(
        'SELECT MIN(attempted_at) AS first_at, COUNT(*) AS fails
           FROM app_login_attempts
          WHERE success = 0
            AND email = :email
            AND attempted_at > (NOW() - INTERVAL :mins MINUTE)'
    );
    $stmt->execute([':email' => $email, ':mins' => LOGIN_WINDOW_MIN]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['fails'] < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    $unlockAt = strtotime($row['first_at']) + LOGIN_WINDOW_MIN * 60;
    return max(0, $unlockAt - time());
}

function login_record_attempt(string $email, bool $success): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = db()->prepare(
        'INSERT INTO app_login_attempts (email, ip_address, success)
         VALUES (:email, :ip, :success)'
    );
    $stmt->execute([
        ':email'   => $email,
        ':ip'      => $ip !== null ? inet_pton($ip) : null,
        ':success' => $success ? 1 : 0,
    ]);
}

function login_lock_message(int $secondsLeft): string {
    $mins = (int) ceil($secondsLeft / 60);
    if ($mins >= 1) {
        return 'Too many failed attempts. Please try again in '
             . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
    }
    return 'Too many failed attempts. Please try again in '
         . $secondsLeft . ' second' . ($secondsLeft === 1 ? '' : 's') . '.';
}


start_session();

if (current_user() !== null) {
    header('Location: /app/profile.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Security check failed. Please try again.';
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        $lockLeft = login_lock_seconds_left($email);
        if ($lockLeft > 0) {
            // Locked out: show live remaining time, skip password check
            $error = login_lock_message($lockLeft);
        } else {
            $stmt = db()->prepare('SELECT id, password_hash FROM app_users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                login_record_attempt($email, true);
                login_user((int) $row['id']);
                header('Location: /app/profile.php');
                exit;
            }

            // Failed attempt: record it, then re-check so the message reflects this attempt
            login_record_attempt($email, false);
            $lockLeft = login_lock_seconds_left($email);
            // Generic message - do not reveal which field was wrong
            $error = $lockLeft > 0
                ? login_lock_message($lockLeft)
                : 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In | UMKC VSA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root{--navy:#16314d;--red:#c8202f;--light:#eef3f8;--text:#1f2933;--muted:#5b6b7b;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Source Sans 3',sans-serif;color:var(--text);background:var(--light);
       min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
  .card{background:#fff;border-radius:16px;box-shadow:0 18px 50px rgba(22,49,77,.15);
        width:100%;max-width:420px;padding:40px;animation:rise .6s ease both;}
  @keyframes rise{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:none;}}
  .brand{display:flex;flex-direction:column;align-items:center;gap:12px;margin-bottom:24px;}
  .brand img{width:64px;height:64px;border-radius:50%;}
  h1{font-family:'Playfair Display',serif;color:var(--navy);font-size:1.7rem;text-align:center;}
  .sub{color:var(--muted);text-align:center;margin-bottom:24px;font-size:.95rem;}
  label{display:block;font-weight:600;margin:14px 0 6px;font-size:.9rem;}
  input{width:100%;padding:12px 14px;border:1.5px solid #d4dde6;border-radius:9px;font-size:1rem;
        transition:border-color .2s,box-shadow .2s;font-family:inherit;}
  input:focus{outline:none;border-color:var(--red);box-shadow:0 0 0 3px rgba(200,32,47,.12);}
  button{width:100%;margin-top:24px;padding:13px;background:var(--red);color:#fff;border:none;
         border-radius:9px;font-size:1.05rem;font-weight:600;cursor:pointer;transition:transform .15s,background .2s;}
  button:hover{background:#a91826;transform:translateY(-2px);}
  .err{background:#fdecee;border:1px solid #f5c2c7;color:#a91826;padding:12px 14px;border-radius:9px;
       margin-bottom:16px;font-size:.9rem;}
  .foot{text-align:center;margin-top:22px;color:var(--muted);font-size:.9rem;}
  .foot a{color:var(--red);font-weight:600;text-decoration:none;}
  .foot a:hover{text-decoration:underline;}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <img src="/assets/logo.png" alt="UMKC VSA logo">
      <h1>Welcome back</h1>
    </div>
    <p class="sub">Log in to your UMKC VSA account</p>
    <?php if ($error): ?>
      <div class="err"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/app/login.php">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <button type="submit">Log In</button>
    </form>
    <p class="foot">New here? <a href="/app/signup.php">Create an account</a></p>
  </div>
</body>
</html>
