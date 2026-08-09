<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;

// ── Already authenticated → go straight to dashboard ──────
if (AuthMiddleware::isAuthenticated()) {
    // Check session timeout (30 minutes of inactivity)
    if (isset($_SESSION['admin_last_active'])
        && (time() - $_SESSION['admin_last_active']) > 1800) {
        session_destroy();
        session_start();
        $error = 'Session expired. Please sign in again.';
    } else {
        $_SESSION['admin_last_active'] = time();
        header('Location: index.php'); exit;
    }
}

// ── Brute-force lockout ────────────────────────────────────
$ip           = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$lockKey      = 'admin_attempts_' . md5($ip);
$attempts     = $_SESSION[$lockKey] ?? 0;
$lastAttempt  = $_SESSION[$lockKey . '_time'] ?? 0;
$lockDuration = 900; // 15 minutes
$maxAttempts  = 5;

$isLocked = ($attempts >= $maxAttempts)
    && ((time() - $lastAttempt) < $lockDuration);

if ($isLocked) {
    $remaining = $lockDuration - (time() - $lastAttempt);
    $error = 'Too many failed attempts. Try again in '
           . ceil($remaining / 60) . ' minutes.';
}

// ── Handle PIN submission ──────────────────────────────────
if (!$isLocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');
    if (hash_equals(ADMIN_PIN, $pin)) {
        // Success — reset lockout, set session
        unset($_SESSION[$lockKey], $_SESSION[$lockKey . '_time']);
        $_SESSION['pin_verified']        = true;
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_last_active']   = time();
        session_regenerate_id(true); // prevent session fixation
        header('Location: index.php'); exit;
    } else {
        // Failed attempt
        $_SESSION[$lockKey]             = $attempts + 1;
        $_SESSION[$lockKey . '_time']   = time();
        $remaining_attempts = $maxAttempts - ($attempts + 1);
        if ($remaining_attempts > 0) {
            $error = 'Incorrect PIN. '.$remaining_attempts.' attempt(s) remaining.';
        } else {
            $error = 'Too many failed attempts. Locked for 15 minutes.';
        }
    }
}

$error = $error ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Access — Safari Zangu Ventures</title>
  <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Jost',sans-serif;background:#0a0a0a;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#fff}
    .gate{width:100%;max-width:340px;padding:0 1.5rem}
    .gate-logo{font-size:2rem;font-weight:500;letter-spacing:.06em;margin-bottom:.25rem;font-style:italic}
    .gate-sub{font-size:.65rem;font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:2.5rem}
    .gate-label{font-size:.65rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.4);display:block;margin-bottom:.5rem}
    .gate-input{width:100%;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.1);color:#fff;font-family:'Jost',sans-serif;font-size:1rem;padding:.85rem 1rem;outline:none;letter-spacing:.12em}
    .gate-input:focus{border-color:rgba(255,255,255,.4)}
    .gate-btn{width:100%;margin-top:1rem;background:#fff;color:#000;border:none;font-family:'Jost',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;padding:.9rem;cursor:pointer}
    .gate-btn:hover{background:#e0e0e0}
    .gate-btn:disabled{background:#555;color:#999;cursor:not-allowed}
    .gate-error{color:#ff6b6b;font-size:.75rem;margin-top:.75rem;letter-spacing:.05em;line-height:1.5}
    .gate-back{display:block;margin-top:2rem;font-size:.65rem;letter-spacing:.1em;color:rgba(255,255,255,.3);text-decoration:none;text-align:center}
    .gate-back:hover{color:rgba(255,255,255,.6)}
    .gate-attempts{font-size:.62rem;color:rgba(255,255,255,.25);margin-top:.5rem;text-align:right}
  </style>
</head>
<body>
  <div class="gate">
    <div class="gate-logo">SZV</div>
    <div class="gate-sub">Admin Access</div>
    <form method="POST" action="">
      <label class="gate-label" for="pin">Access PIN</label>
      <input class="gate-input" type="password" id="pin" name="pin"
             placeholder="· · · · · · · ·" autofocus autocomplete="off"
             <?= $isLocked ? 'disabled' : '' ?>>
      <?php if ($error): ?>
        <p class="gate-error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      <button class="gate-btn" type="submit" <?= $isLocked ? 'disabled' : '' ?>>
        Enter Admin Panel
      </button>
    </form>
    <a class="gate-back" href="<?= BASE_PATH ?>/">← Back to Safari Zangu</a>
  </div>
</body>
</html>
