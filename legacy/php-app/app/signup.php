<?php
// ============================================================
// UMKC VSA - Sign up (register a new member account)
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

start_session();

// Already logged in? Send to profile.
if (current_user() !== null) {
    header('Location: /app/profile.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$first_name = '';
$last_name  = '';
$full_name  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security check failed. Please try again.';
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $full_name  = trim($first_name . ' ' . $last_name);
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';

    if ($first_name === '' || mb_strlen($first_name) > 100) {
        $errors[] = 'Please enter your first name (under 100 characters).';
    }
    if ($last_name === '' || mb_strlen($last_name) > 100) {
        $errors[] = 'Please enter your last name (under 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must include at least one special character.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        // Check for existing email
        $stmt = db()->prepare('SELECT id FROM app_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = db()->prepare(
            'INSERT INTO app_users (first_name, last_name, full_name, email, password_hash) VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$first_name, $last_name, $full_name, $email, $hash]);
        $newId = (int) db()->lastInsertId();
        login_user($newId);
        header('Location: /app/profile.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up | UMKC VSA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root{--navy:#16314d;--red:#c8202f;--light:#eef3f8;--text:#1f2933;--muted:#5b6b7b;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Source Sans 3',sans-serif;color:var(--text);background:var(--light);
       min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
  .card{background:#fff;border-radius:16px;box-shadow:0 18px 50px rgba(22,49,77,.15);
        width:100%;max-width:440px;padding:40px;animation:rise .6s ease both;}
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
  .err ul{margin-left:18px;}
  .foot{text-align:center;margin-top:22px;color:var(--muted);font-size:.9rem;}
  .foot a{color:var(--red);font-weight:600;text-decoration:none;}
  .foot a:hover{text-decoration:underline;}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <img src="/assets/logo.png" alt="UMKC VSA logo">
      <h1>Create your account</h1>
    </div>
    <p class="sub">Join the UMKC Vietnamese Student Association</p>
    <?php if ($errors): ?>
      <div class="err"><ul>
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
    <form method="post" action="/app/signup.php">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <label for="first_name">First name</label>
      <input type="text" id="first_name" name="first_name" value="<?= e($first_name) ?>" required>
      <label for="last_name">Last name</label>
      <input type="text" id="last_name" name="last_name" value="<?= e($last_name) ?>" required>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <small id="password-hint" style="display:block;margin:2px 0 4px;color:var(--muted);font-size:.8rem;line-height:1.35;">At least 8 characters, including one uppercase letter, one lowercase letter, and one special character.</small>
      <label for="confirm">Confirm password</label>
      <input type="password" id="confirm" name="confirm" required>
      <button type="submit">Sign Up</button>
    </form>
    <p class="foot">Already have an account? <a href="/app/login.php">Log in</a></p>
  </div>
</body>
</html>
