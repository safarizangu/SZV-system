<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Models\Database;

// Already logged in as an agent
if (AuthMiddleware::isAuthenticated() && AuthMiddleware::isAgentRole()) {
    header('Location: index.php'); exit;
}

$error   = '';
$notice  = '';
$reason  = $_GET['reason'] ?? '';
if ($reason === 'timeout')  $notice = 'Your session expired. Please sign in again.';
if ($reason === 'logout')   $notice = 'You have been signed out.';
if ($reason === 'required') $notice = 'Please sign in to access the agent portal.';

// ── Rate limiting (server-side, own namespace from admin login) ─
$ip          = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
$lockFile    = '/tmp/szv_ratelimit/agentlogin_' . hash('sha256', $ip) . '.json';
$maxAttempts = 5;
$lockSeconds = 900; // 15 min

$lockData    = ['attempts'=>0,'last_attempt'=>0];
if (file_exists($lockFile)) {
    $lockData = json_decode(file_get_contents($lockFile), true) ?? $lockData;
}
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

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT id, username, password_hash, full_name, email, role, can_change_status, can_delete_records, theme_preference
                 FROM admin_users WHERE username = ? LIMIT 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (($user['role'] ?? '') !== 'agent') {
                    // A valid admin/staff credential, but this is the
                    // agent-only front door — send them to the right one
                    // instead of creating a session here.
                    $error = 'This sign-in is for agents only. Admin and staff should use the admin panel.';
                } else {
                    $authenticated = true;
                    $db->prepare("UPDATE admin_users SET last_login=NOW() WHERE id=?")
                       ->execute([$user['id']]);

                    $_SESSION['admin_user_id']   = $user['id'];
                    $_SESSION['admin_role']       = $user['role'];
                    $_SESSION['admin_username']  = $user['username'];
                    $_SESSION['admin_full_name'] = $user['full_name'];
                    $_SESSION['admin_email']     = $user['email'];
                    $_SESSION['admin_auth_type'] = 'password';
                    $_SESSION['can_change_status'] = (int)($user['can_change_status'] ?? 1);
                    $_SESSION['can_delete_records'] = (int)($user['can_delete_records'] ?? 1);
                    $_SESSION['theme_preference'] = $user['theme_preference'] ?? 'light';
                }
            }
        } catch (\Exception $e) {
            error_log('[SZV Agent Login DB] ' . $e->getMessage());
        }

        if ($authenticated) {
            if (file_exists($lockFile)) unlink($lockFile);

            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_last_active']   = time();
            session_regenerate_id(true);

            header('Location: index.php'); exit;

        } elseif (!$error) {
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
  <title>Agent Sign In — Safari Zangu Ventures</title>
  <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Times New Roman', Times, serif;
      background: #0a0a0a;
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 480px;
    }

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
      font-size: 2.25rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: .04em;
      font-style: italic;
    }
    .brand-tagline {
      font-size: .58rem;
      font-weight: 700;
      letter-spacing: .25em;
      text-transform: uppercase;
      color: rgba(255,255,255,.3);
      margin-top: .4rem;
    }
    .brand-mid { padding: 2rem 0; }
    .brand-mid h2 {
      font-size: 2.2rem;
      font-weight: 700;
      color: #fff;
      line-height: 1.25;
      margin-bottom: 1.25rem;
    }
    .brand-mid h2 em { font-style: italic; color: rgba(255,255,255,.55); }
    .brand-mid p { font-size: .82rem; color: rgba(255,255,255,.4); line-height: 1.75; max-width: 340px; }
    .brand-stats { display: flex; gap: 2.5rem; }
    .brand-stat-val { font-size: 1.5rem; font-weight: 700; color: #fff; }
    .brand-stat-lbl {
      font-size: .58rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase;
      color: rgba(255,255,255,.3); margin-top: .15rem;
    }

    .login-form-panel {
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 3rem 3.5rem;
    }
    .login-form-top { margin-bottom: 2.5rem; }
    .login-form-eyebrow {
      font-size: .6rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase;
      color: #9ca3af; margin-bottom: .6rem;
    }
    .login-form-title { font-size: 1.75rem; font-weight: 700; color: #0a0a0a; }

    .form-group { margin-bottom: 1.25rem; }
    .form-label {
      display: block; font-size: .62rem; font-weight: 700; letter-spacing: .14em;
      text-transform: uppercase; color: #374151; margin-bottom: .4rem;
    }
    .form-input {
      width: 100%; padding: .85rem 1rem; border: 1.5px solid #e5e7eb;
      font-family: inherit; font-size: .92rem; color: #0a0a0a; outline: none;
      background: #fafafa; transition: border-color .15s;
    }
    .form-input:focus { border-color: #0a0a0a; background: #fff; }
    .form-input:focus-visible { outline: 2.5px solid #c8963e; outline-offset: 1px; } /* A11Y-3 */
    .form-input::placeholder { color: #d1d5db; }

    .password-wrap { position: relative; }
    .toggle-pw {
      position: absolute; right: .85rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: #9ca3af;
      font-size: .75rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    }
    .toggle-pw:hover { color: #0a0a0a; }

    .login-btn {
      width: 100%; padding: .95rem; background: #0a0a0a; color: #fff; border: none;
      font-family: inherit; font-size: .72rem; font-weight: 700; letter-spacing: .18em;
      text-transform: uppercase; cursor: pointer; margin-top: .5rem; transition: background .15s;
    }
    .login-btn:hover:not(:disabled) { background: #1e1e1e; }
    .login-btn:disabled { background: #6b7280; cursor: not-allowed; }

    .login-notice {
      background: #eff6ff; border-left: 3px solid #3b82f6; color: #1e40af;
      padding: .75rem 1rem; font-size: .8rem; margin-bottom: 1.5rem; line-height: 1.5;
    }
    .login-error {
      background: #fef2f2; border-left: 3px solid #dc2626; color: #991b1b;
      padding: .75rem 1rem; font-size: .8rem; margin-bottom: 1.25rem; line-height: 1.5;
    }
    .login-footer {
      margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f3f4f6;
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem;
    }
    .login-footer a { font-size: .68rem; color: #9ca3af; text-decoration: none; letter-spacing: .04em; }
    .login-footer a:hover { color: #0a0a0a; }
    .login-footer-badge {
      font-size: .58rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
      color: #d1d5db; padding: .2rem .5rem; border: 1px solid #e5e7eb;
    }

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

<div class="login-brand">
  <div>
    <div class="brand-logo">SZV</div>
    <div class="brand-tagline">Safari Zangu Ventures · Agent Portal</div>
  </div>
  <div class="brand-mid">
    <h2>Grow Every<br><em>Client Relationship</em></h2>
    <p>Track your leads, follow up at the right moment, and watch your commission grow as each one books.</p>
  </div>
  <div class="brand-stats">
    <div>
      <div class="brand-stat-val">Your</div>
      <div class="brand-stat-lbl">Leads</div>
    </div>
    <div>
      <div class="brand-stat-val">Your</div>
      <div class="brand-stat-lbl">Commission</div>
    </div>
    <div>
      <div class="brand-stat-val">Your</div>
      <div class="brand-stat-lbl">Clients</div>
    </div>
  </div>
</div>

<div class="login-form-panel">
  <div class="login-form-top">
    <div class="login-form-eyebrow">Safari Zangu Ventures</div>
    <div class="login-form-title">Agent Sign In</div>
  </div>

  <?php if ($notice): ?>
  <div class="login-notice" role="status"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="login-error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <?= \App\Middleware\CsrfMiddleware::field() ?>
    <div class="form-group">
      <label class="form-label" for="username">Username</label>
      <input class="form-input" type="text" id="username" name="username"
             placeholder="your.username"
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
      <?= $isLocked ? 'Locked — try again later' : 'Sign In →' ?>
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
