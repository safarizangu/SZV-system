<?php
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
header('Content-Type: application/json');

function drespond(array $d): void { ob_end_clean(); header('Content-Type: application/json'); echo json_encode($d); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') drespond(['success'=>false,'message'=>'Invalid method.']);

$relPath = trim($_POST['path'] ?? '');
// Sanitise — must start with /assets/images/ and have no ..
if (!$relPath || !str_starts_with($relPath, BASE_PATH.'/assets/images/') || str_contains($relPath, '..')) {
    drespond(['success'=>false,'message'=>'Invalid path.']);
}

$absPath = $_SERVER['DOCUMENT_ROOT'] . $relPath;
if (!file_exists($absPath)) drespond(['success'=>false,'message'=>'File not found.']);

// Protect root-level logo/favicon
$basename = basename($absPath);
$protected = ['logo.jpeg','favicon.ico','logo.png'];
if (in_array($basename, $protected)) drespond(['success'=>false,'message'=>'Protected file.']);

if (!unlink($absPath)) drespond(['success'=>false,'message'=>'Could not delete file.']);
drespond(['success'=>true]);
