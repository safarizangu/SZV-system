<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Models\Database;

// Already logged in
if (AuthMiddleware::isAuthenticated()) {
    header('Location: index.php'); exit;
}

$error   = '';
$notice  = '';
$reason  = $_GET['reason'] ?? '';
if ($reason === 'timeout')  $notice = 'Your session expired. Please sign in again.';
if ($reason === 'logout')   $notice = 'You have been signed out.';
if ($reason === 'required') $notice = 'Please sign in to access the admin panel.';

// ── Rate limiting (server-side — session bypass proof) ──────
$ip          = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
$lockFile    = '/tmp/szv_ratelimit/login_' . hash('sha256', $ip) . '.json';
$maxAttempts = 5;
$lockSeconds = 900; // 15 min

$lockData    = ['attempts'=>0,'last_attempt'=>0];
if (file_exists($lockFile)) {
    $lockData = json_decode(file_get_contents($lockFile), true) ?? $lockData;
}
// Reset if window expired
if ((time() - $lockData['last_attempt']) >= $lockSeconds) {
    $lockData = ['attempts'=>0,'last_attempt'=>0];
}
$attempts  = (int)$lockData['attempts'];
$isLocked  = $attempts >= $maxAttempts;

if ($isLocked) {
    $remaining = $lockSeconds - (time() - $lockData['last_attempt']);
    $error = 'Too many failed attempts. Try again in ' . ceil($remaining / 60) . ' minutes.';
}

// ── Handle login ───────────────────────────────────────────
if (!$isLocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = 'Please enter your username and password.';
    } else {
        $authenticated = false;

        // ── Try DB login first ─────────────────────────────
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT id, username, password_hash, full_name, email, role
                 FROM admin_users WHERE username = ? LIMIT 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $authenticated = true;

                // Update last_login
                $db->prepare("UPDATE admin_users SET last_login=NOW() WHERE id=?")
                   ->execute([$user['id']]);

                // Set detailed session
                $_SESSION['admin_user_id']   = $user['id'];
                $_SESSION['admin_role']       = $user['role'] ?? 'admin';
                $_SESSION['admin_username']  = $user['username'];
                $_SESSION['admin_full_name'] = $user['full_name'];
                $_SESSION['admin_email']     = $user['email'];
                $_SESSION['admin_auth_type'] = 'password';
            }
        } catch (\Exception $e) {
            error_log('[SZV Login DB] ' . $e->getMessage());
            // Fall through to PIN check
        }

        // ── Fallback: PIN as password ──────────────────────
        if (!$authenticated && $username === 'admin') {
            if (defined('ADMIN_PIN') && hash_equals(ADMIN_PIN, $password)) {
                $authenticated = true;
                $_SESSION['admin_username']  = 'admin';
                $_SESSION['admin_full_name'] = 'Administrator';
                $_SESSION['admin_auth_type'] = 'pin';
            }
        }

        if ($authenticated) {
            // Clear server-side lockout file
            if (file_exists($lockFile)) unlink($lockFile);

            // Set auth session
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['pin_verified']        = true;
            $_SESSION['admin_last_active']   = time();
            session_regenerate_id(true);

            // Agents now have their own front door — send them straight
            // there instead of into the admin panel, which the agent-scoped
            // pages here still work as a fallback/bridge for anything the
            // portal doesn't cover yet (quotations, bookings, invoices).
            if (($_SESSION['admin_role'] ?? '') === 'agent') {
                header('Location: ../agent-portal/index.php'); exit;
            }

            // Strict redirect: only allow known admin pages — no open redirect
            $allowedRedirects = ['index.php','bookings.php','quotations.php','quotation_new.php',
                'packages.php','leads.php','commissions.php','gallery.php','itineraries.php',
                'users.php','email_queue.php','reminders.php','analytics.php'];
            $redirect = $_GET['redirect'] ?? 'index.php';
            $redirectBase = strtok($redirect, '?');
            if (!in_array($redirectBase, $allowedRedirects, true)) {
                $redirect = 'index.php';
            }
            header('Location: ' . $redirect); exit;

        } else {
            // Failed — increment server-side lockout
            $lockData['attempts']     = $attempts + 1;
            $lockData['last_attempt'] = time();
            if (!is_dir('/tmp/szv_ratelimit/')) mkdir('/tmp/szv_ratelimit/', 0700, true);
            file_put_contents($lockFile, json_encode($lockData), LOCK_EX);
            $remaining = $maxAttempts - ($attempts + 1);
            $error = $remaining > 0
                ? 'Incorrect credentials. ' . $remaining . ' attempt' . ($remaining!==1?'s':'') . ' remaining.'
                : 'Too many failed attempts. Locked for 15 minutes.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Sign In — Safari Zangu Ventures</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Jost', system-ui, sans-serif;
      background: #0a0a0a;
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 480px;
    }

    /* ── Left panel — branding ── */
    .login-brand {
      background: #0a0a0a;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 3rem 3.5rem;
      border-right: 1px solid rgba(255,255,255,.07);
      position: relative;
      overflow: hidden;
    }
    .login-brand::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 30% 60%, rgba(255,255,255,.03) 0%, transparent 70%);
      pointer-events: none;
    }
    .brand-logo {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 2.25rem;
      font-weight: 500;
      color: #fff;
      letter-spacing: .04em;
      font-style: italic;
    }
    .brand-tagline {
      font-size: .58rem;
      font-weight: 600;
      letter-spacing: .25em;
      text-transform: uppercase;
      color: rgba(255,255,255,.3);
      margin-top: .4rem;
    }
    .brand-mid {
      padding: 2rem 0;
    }
    .brand-mid h2 {
      font-family: 'Playfair Display', serif;
      font-size: 2.2rem;
      font-weight: 500;
      color: #fff;
      line-height: 1.25;
      margin-bottom: 1.25rem;
    }
    .brand-mid h2 em {
      font-style: italic;
      color: rgba(255,255,255,.55);
    }
    .brand-mid p {
      font-size: .82rem;
      color: rgba(255,255,255,.35);
      line-height: 1.75;
      max-width: 340px;
    }
    .brand-stats {
      display: flex;
      gap: 2.5rem;
    }
    .brand-stat-val {
      font-family: 'Playfair Display', serif;
      font-size: 1.5rem;
      font-weight: 500;
      color: #fff;
    }
    .brand-stat-lbl {
      font-size: .58rem;
      font-weight: 600;
      letter-spacing: .15em;
      text-transform: uppercase;
      color: rgba(255,255,255,.3);
      margin-top: .15rem;
    }

    /* ── Right panel — form ── */
    .login-form-panel {
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 3rem 3.5rem;
    }
    .login-form-top {
      margin-bottom: 2.5rem;
    }
    .login-form-eyebrow {
      font-size: .6rem;
      font-weight: 700;
      letter-spacing: .2em;
      text-transform: uppercase;
      color: #9ca3af;
      margin-bottom: .6rem;
    }
    .login-form-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.75rem;
      font-weight: 500;
      color: #0a0a0a;
    }

    .form-group { margin-bottom: 1.25rem; }
    .form-label {
      display: block;
      font-size: .62rem;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: #374151;
      margin-bottom: .4rem;
    }
    .form-input {
      width: 100%;
      padding: .85rem 1rem;
      border: 1.5px solid #e5e7eb;
      font-family: 'Jost', sans-serif;
      font-size: .92rem;
      color: #0a0a0a;
      outline: none;
      background: #fafafa;
      transition: border-color .15s;
    }
    .form-input:focus { border-color: #0a0a0a; background: #fff; }
    .form-input::placeholder { color: #d1d5db; }

    .password-wrap { position: relative; }
    .toggle-pw {
      position: absolute;
      right: .85rem; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer; color: #9ca3af;
      font-size: .75rem; font-weight: 600;
      letter-spacing: .05em; text-transform: uppercase;
    }
    .toggle-pw:hover { color: #0a0a0a; }

    .login-btn {
      width: 100%;
      padding: .95rem;
      background: #0a0a0a;
      color: #fff;
      border: none;
      font-family: 'Jost', sans-serif;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .18em;
      text-transform: uppercase;
      cursor: pointer;
      margin-top: .5rem;
      transition: background .15s;
    }
    .login-btn:hover:not(:disabled) { background: #1e1e1e; }
    .login-btn:disabled { background: #6b7280; cursor: not-allowed; }

    .login-notice {
      background: #eff6ff;
      border-left: 3px solid #3b82f6;
      color: #1e40af;
      padding: .75rem 1rem;
      font-size: .8rem;
      margin-bottom: 1.5rem;
      line-height: 1.5;
    }
    .login-error {
      background: #fef2f2;
      border-left: 3px solid #dc2626;
      color: #991b1b;
      padding: .75rem 1rem;
      font-size: .8rem;
      margin-bottom: 1.25rem;
      line-height: 1.5;
    }
    .login-footer {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid #f3f4f6;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .5rem;
    }
    .login-footer a {
      font-size: .68rem;
      color: #9ca3af;
      text-decoration: none;
      letter-spacing: .04em;
    }
    .login-footer a:hover { color: #0a0a0a; }
    .login-footer-badge {
      font-size: .58rem;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: #d1d5db;
      padding: .2rem .5rem;
      border: 1px solid #e5e7eb;
    }

    /* ── Responsive ── */
    @media (max-width: 900px) {
      body { grid-template-columns: 1fr; }
      .login-brand { display: none; }
      .login-form-panel { min-height: 100vh; padding: 2.5rem 2rem; }
    }
    @media (max-width: 480px) {
      .login-form-panel { padding: 2rem 1.25rem; }
    }
  </style>
</head>
<body>

<!-- Left: branding -->
<div class="login-brand">
  <div>
    <div class="brand-logo">SZV</div>
    <div class="brand-tagline">Safari Zangu Ventures · Admin</div>
  </div>
  <div class="brand-mid">
    <h2>Manage Every<br><em>Safari Journey</em></h2>
    <p>Bookings, quotations, client communications, package management and analytics — all in one place.</p>
  </div>
  <div class="brand-stats">
    <div>
      <div class="brand-stat-val">6</div>
      <div class="brand-stat-lbl">Safari Packages</div>
    </div>
    <div>
      <div class="brand-stat-val">59</div>
      <div class="brand-stat-lbl">Accommodations</div>
    </div>
    <div>
      <div class="brand-stat-val">∞</div>
      <div class="brand-stat-lbl">Possibilities</div>
    </div>
  </div>
</div>

<!-- Right: form -->
<div class="login-form-panel">
  <div class="login-form-top">
    <div class="login-form-eyebrow">Safari Zangu Ventures</div>
    <div class="login-form-title">Admin Sign In</div>
  </div>

  <?php if ($notice): ?>
  <div class="login-notice"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="login-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <?= \App\Middleware\CsrfMiddleware::field() ?>
    <div class="form-group">
      <label class="form-label" for="username">Username</label>
      <input class="form-input" type="text" id="username" name="username"
             value=""
             placeholder="admin"
             autocomplete="username"
             autofocus
             <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <div class="password-wrap">
        <input class="form-input" type="password" id="password" name="password"
               placeholder="············"
               autocomplete="current-password"
               <?= $isLocked ? 'disabled' : '' ?>>
        <button type="button" class="toggle-pw" onclick="togglePw()">Show</button>
      </div>
    </div>

    <button class="login-btn" type="submit" <?= $isLocked ? 'disabled' : '' ?>>
      <?= $isLocked ? 'Locked — wait ' . ceil(($lockSeconds - (time()-$lastAttempt))/60) . ' min' : 'Sign In →' ?>
    </button>
  </form>

  <div class="login-footer">
    <a href="<?= BASE_PATH ?>/">← Back to public site</a>
    <span class="login-footer-badge">Secure · Session 30min</span>
  </div>
</div>

<script>
function togglePw() {
  var inp = document.getElementById('password');
  var btn = inp.nextElementSibling;
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.textContent = 'Hide';
  } else {
    inp.type = 'password';
    btn.textContent = 'Show';
  }
}
</script>
</body>
</html>
