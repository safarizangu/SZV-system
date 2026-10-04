<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyJson('api', 'set_hero.php', 'agent_access');
}


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

\App\Middleware\CsrfMiddleware::validateOrDie();

$imageUrl = trim($_POST['image_url'] ?? '');

if (!$imageUrl) {
    echo json_encode(['success'=>false,'message'=>'No image URL provided']);
    exit;
}

// Security: must be a relative path inside our assets directory.
// Strip any domain prefix AND any BASE_PATH prefix (older callers, or a
// browser tab still open from before this was fixed, may still send one)
// so only a clean, environment-independent root-relative path is ever
// stored — BASE_PATH differs between local (/safari-zangu) and
// production (''), and baking either one into stored data breaks image
// display the moment the database is shared or copied between them.
$imageUrl = preg_replace('#^https?://[^/]+#', '', $imageUrl);
if (BASE_PATH !== '' && str_starts_with($imageUrl, BASE_PATH)) {
    $imageUrl = substr($imageUrl, strlen(BASE_PATH));
}
// A stray prefix from the OTHER environment (e.g. a production admin
// pasting a URL copied while testing locally) — strip it too.
if (str_starts_with($imageUrl, '/safari-zangu')) {
    $imageUrl = substr($imageUrl, strlen('/safari-zangu'));
}

if (!preg_match('#^/assets/images/#', $imageUrl)) {
    echo json_encode(['success'=>false,'message'=>'Invalid image path']);
    exit;
}

// Validate file actually exists on disk
$docRoot  = $_SERVER['DOCUMENT_ROOT'];
$diskPath = $docRoot . BASE_PATH . $imageUrl;
if (!file_exists($diskPath)) {
    echo json_encode(['success'=>false,'message'=>'Image file not found on disk']);
    exit;
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
