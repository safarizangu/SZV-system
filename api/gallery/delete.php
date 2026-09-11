<?php
ob_start();
require_once __DIR__ . '/../../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

header('Content-Type: application/json');
function drespond(array $d): void { ob_end_clean(); header('Content-Type: application/json'); echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') drespond(['success'=>false,'message'=>'Invalid method.']);

\App\Middleware\CsrfMiddleware::validateOrDie();

$rawPath   = trim($_POST['path'] ?? '');
if (!$rawPath) drespond(['success'=>false,'message'=>'No path provided.']);

// Resolve the allowed root
$assetsRoot = realpath($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/assets/images/');
if (!$assetsRoot) drespond(['success'=>false,'message'=>'Assets directory not found.']);

// Strip BASE_PATH prefix if present (client sends URL path)
$urlPath = preg_replace('#^' . preg_quote(BASE_PATH, '#') . '#', '', $rawPath);
$fullPath = realpath($_SERVER['DOCUMENT_ROOT'] . $urlPath);

// Security: must resolve to a real path inside assets/images/
if (!$fullPath || strpos($fullPath, $assetsRoot) !== 0) {
    drespond(['success'=>false,'message'=>'Invalid file path.']);
}

// Must be a file, not a directory
if (!is_file($fullPath)) {
    drespond(['success'=>false,'message'=>'File not found.']);
}

// Only allow image extensions
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
    drespond(['success'=>false,'message'=>'Invalid file type.']);
}

if (!unlink($fullPath)) {
    drespond(['success'=>false,'message'=>'Failed to delete file.']);
}
drespond(['success'=>true,'message'=>'Deleted.']);
