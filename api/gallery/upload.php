<?php
ob_start();
require_once __DIR__ . '/../../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

header('Content-Type: application/json');

function grespond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') grespond(['success'=>false,'message'=>'Invalid method.']);

$folder  = trim($_POST['folder'] ?? 'general');
$folder  = preg_replace('/[^a-z0-9\-_]/', '', strtolower($folder));
if (!$folder) $folder = 'general';

$assetsRoot = realpath($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/assets/images/');
$uploadDir  = $assetsRoot . '/' . $folder . '/';

// Path traversal check — ensure final path is inside assets/images/
$realUpload = realpath($uploadDir) ?: $uploadDir;
if ($assetsRoot && strpos($realUpload, $assetsRoot) !== 0) {
    grespond(['success'=>false,'message'=>'Invalid upload folder.']);
}

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        grespond(['success'=>false,'message'=>'Could not create folder: '.$folder]);
    }
}

$file   = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    grespond(['success'=>false,'message'=>'Upload error: '.($file['error']??'no file')]);
}

// Validate
$maxSize = 8 * 1024 * 1024; // 8MB
if ($file['size'] > $maxSize) grespond(['success'=>false,'message'=>'File too large (max 8MB).']);

$allowed  = ['image/jpeg','image/jpg','image/png','image/webp'];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed, true)) grespond(['success'=>false,'message'=>'Invalid file type. JPG, PNG or WebP only.']);

// Generate filename
$ext      = match($mime) { 'image/png'=>'png', 'image/webp'=>'webp', default=>'jpg' };
$basename = pathinfo($file['name'], PATHINFO_FILENAME);
$basename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $basename);
$filename = $basename . '_' . time() . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    grespond(['success'=>false,'message'=>'Failed to save file.']);
}

// Root-relative, WITHOUT BASE_PATH — see api/gallery/list.php for why.
$url = '/assets/images/' . $folder . '/' . $filename;
grespond(['success'=>true, 'url'=>$url, 'filename'=>$filename, 'folder'=>$folder]);
