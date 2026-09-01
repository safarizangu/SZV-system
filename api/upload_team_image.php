<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'POST required']);
    exit;
}

$type = trim($_POST['type'] ?? 'team'); // team | fleet | accommodation
$allowedTypes = ['team','fleet','accommodation'];
if (!in_array($type, $allowedTypes)) {
    echo json_encode(['success'=>false,'message'=>'Invalid type']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'message'=>'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['image'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','webp'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Only JPG, PNG, WebP allowed']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success'=>false,'message'=>'File too large (max 5MB)']);
    exit;
}

// Validate it's actually an image
$imgInfo = @getimagesize($file['tmp_name']);
if (!$imgInfo) {
    echo json_encode(['success'=>false,'message'=>'File is not a valid image']);
    exit;
}

$targetDir  = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . BASE_PATH . "/assets/images/{$type}/";
$targetUrl  = BASE_PATH . "/assets/images/{$type}/";

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$filename   = uniqid($type.'_', true) . '.' . $ext;
$targetPath = $targetDir . $filename;
$imageUrl   = $targetUrl . $filename;

// Validate path stays inside assets/images/
$realTarget = realpath($targetDir);
$realAssets = realpath(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . BASE_PATH . '/assets/images/');
if (!$realTarget || !str_starts_with($realTarget, $realAssets)) {
    echo json_encode(['success'=>false,'message'=>'Invalid upload path']);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success'=>false,'message'=>'Failed to save file']);
    exit;
}

echo json_encode([
    'success'  => true,
    'url'      => $imageUrl,
    'filename' => $filename,
    'type'     => $type,
]);
