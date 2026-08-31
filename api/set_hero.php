<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Admin only
if (empty($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'POST required']);
    exit;
}

$imageUrl = trim($_POST['image_url'] ?? '');

if (!$imageUrl) {
    echo json_encode(['success'=>false,'message'=>'No image URL provided']);
    exit;
}

// Security: must be a relative path inside our assets directory
// Strip any domain prefix so we only store relative paths
$imageUrl = preg_replace('#^https?://[^/]+#', '', $imageUrl);

// Must be within /safari-zangu/assets/images/
if (!preg_match('#^/safari-zangu/assets/images/#', $imageUrl) &&
    !preg_match('#^/assets/images/#', $imageUrl)) {
    echo json_encode(['success'=>false,'message'=>'Invalid image path']);
    exit;
}

// Validate file actually exists on disk
$docRoot   = $_SERVER['DOCUMENT_ROOT'];
$diskPath  = $docRoot . $imageUrl;
// Also try without the /safari-zangu prefix
if (!file_exists($diskPath)) {
    $diskPath2 = $docRoot . str_replace('/safari-zangu', '', $imageUrl);
    if (file_exists($diskPath2)) {
        $diskPath = $diskPath2;
    } else {
        echo json_encode(['success'=>false,'message'=>'Image file not found on disk']);
        exit;
    }
}

// Save to settings table
try {
    $db = \App\Models\Database::getInstance();
    $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('hero_image',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW()")
       ->execute([$imageUrl, $imageUrl]);

    echo json_encode([
        'success'   => true,
        'message'   => 'Hero image updated',
        'image_url' => $imageUrl,
    ]);
} catch (\Exception $e) {
    error_log('[set_hero] ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Database error']);
}
