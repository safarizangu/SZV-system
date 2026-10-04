<?php
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';

if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyJson('api', 'upload_team_image.php', 'agent_access');
}

use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

header('Content-Type: application/json');

function urespond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') urespond(['success'=>false,'message'=>'POST required']);

\App\Middleware\CsrfMiddleware::validateOrDie();

$type = trim($_POST['type'] ?? 'team'); // team | fleet | accommodation
$allowedTypes = ['team','fleet','accommodation'];
if (!in_array($type, $allowedTypes, true)) {
    urespond(['success'=>false,'message'=>'Invalid type']);
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    urespond(['success'=>false,'message'=>'No file uploaded or upload error']);
}

if ($file['size'] > 5 * 1024 * 1024) {
    urespond(['success'=>false,'message'=>'File too large (max 5MB)']);
}

// Real content-type sniffing — not the user-supplied filename/extension —
// matching api/gallery/upload.php's standard (the reference implementation
// this endpoint previously fell short of: it trusted pathinfo() + getimagesize()).
$allowedMime = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!isset($allowedMime[$mime])) {
    urespond(['success'=>false,'message'=>'Only JPG, PNG, WebP allowed']);
}
$ext = $allowedMime[$mime];

$assetsRoot = realpath(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . BASE_PATH . '/assets/images/');
$targetDir  = $assetsRoot . "/{$type}/";

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$filename   = uniqid($type . '_', true) . '.' . $ext;
$targetPath = $targetDir . $filename;

// Path traversal check — ensure final path is inside assets/images/
$realTarget = realpath($targetDir) ?: $targetDir;
if (!$assetsRoot || strpos($realTarget, $assetsRoot) !== 0) {
    urespond(['success'=>false,'message'=>'Invalid upload path']);
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    urespond(['success'=>false,'message'=>'Failed to save file']);
}

\App\Helpers\ImageOptimizer::optimize($targetPath, $mime);

// Root-relative, WITHOUT BASE_PATH — matches api/gallery/upload.php's
// convention (BASE_PATH is added at render time by the consuming view,
// never stored — storing it here previously caused saved paths to break
// the moment they were viewed from a different BASE_PATH environment).
$imageUrl = "/assets/images/{$type}/{$filename}";

urespond([
    'success'  => true,
    'url'      => $imageUrl,
    'filename' => $filename,
    'type'     => $type,
]);
