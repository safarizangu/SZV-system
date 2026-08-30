<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$pageTitle = 'Admin Guide';
ob_start();
?>
<div style="max-width:860px">
<style>
.guide h1{font-family:var(--font-disp);font-size:1.6rem;font-weight:500;margin-bottom:1.5rem}
.guide h2{font-family:var(--font-disp);font-size:1.15rem;font-weight:500;margin:2rem 0 .75rem;padding-top:1.5rem;border-top:1px solid var(--gray-200)}
.guide h3{font-size:.88rem;font-weight:700;margin:1.25rem 0 .5rem}
.guide p,.guide li{font-size:.85rem;line-height:1.8;color:#374151}
.guide ul,.guide ol{padding-left:1.25rem;margin:.5rem 0}
.guide li{margin-bottom:.25rem}
.guide table{width:100%;border-collapse:collapse;font-size:.82rem;margin:1rem 0}
.guide th{background:#0a0a0a;color:#fff;padding:.5rem .75rem;text-align:left;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase}
.guide td{padding:.5rem .75rem;border-bottom:1px solid #f3f4f6}
.guide tr:hover td{background:#f9f9f8}
.guide code{background:#f3f4f6;padding:.1rem .35rem;font-size:.8rem;border-radius:2px}
.guide pre{background:#0a0a0a;color:#a3e635;padding:1rem;font-size:.75rem;overflow-x:auto;margin:1rem 0;border-radius:2px}
.guide blockquote{border-left:3px solid #0a0a0a;padding:.5rem 1rem;background:#f9f9f8;font-size:.82rem;margin:1rem 0}
</style>
<div class="guide admin-card">
  <div class="admin-card-header">
    <h3>Admin System Guide</h3>
    <a href="index.php" class="admin-btn admin-btn--muted" style="font-size:.65rem">← Dashboard</a>
  </div>
  <div class="admin-card-body" style="padding:2rem">
<?php
$md = file_get_contents(__DIR__.'/../ADMIN_GUIDE.md');
// Basic markdown to HTML conversion
$md = htmlspecialchars($md);
$md = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $md);
$md = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $md);
$md = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $md);
$md = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $md);
$md = preg_replace('/`([^`]+)`/', '<code>$1</code>', $md);
$md = preg_replace('/\\*\*([^*]+)\*\*/', '<strong>$1</strong>', $md);
$md = preg_replace('/^```[a-z]*$/m', '<pre><code>', $md);
$md = preg_replace('/^```$/m', '</code></pre>', $md);
$md = preg_replace('/^\\| (.+)$/m', '<tr><td>$1</td></tr>', $md);
$md = nl2br($md);
echo $md;
?>
  </div>
</div>
</div>
<?php \\$content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
