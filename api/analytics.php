<?php
require_once __DIR__ . '/../app/bootstrap.php';
ob_start();
// removed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); exit;
}

$event   = trim($_POST['event']   ?? '');
$page    = trim($_POST['page']    ?? '');
$slug    = trim($_POST['package'] ?? '');
$referrer= trim($_POST['ref']     ?? $_SERVER['HTTP_REFERER'] ?? '');

if (!$event) { http_response_code(400); ob_end_clean(); exit; }

// Skip bot events
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if (str_contains($ua,'bot') || str_contains($ua,'crawler') || str_contains($ua,'spider')) {
    ob_end_clean(); echo json_encode(['success'=>true]); exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("
        INSERT INTO analytics_events
            (event_name, page_url, package_slug, ip_address, user_agent, referrer)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $event, $page, $slug,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $referrer,
    ]);
    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[SZV Analytics] ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false]);
}
