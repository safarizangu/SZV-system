<?php
require_once __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json');

$key = trim($_GET['key'] ?? '');
if (!$key || !preg_match('/^[a-z0-9_]+$/', $key)) {
    echo json_encode(['success'=>false,'message'=>'Invalid key']);
    exit;
}

try {
    $db   = \App\Models\Database::getInstance();
    $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
    $stmt->execute([$key]);
    $row  = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'key'     => $key,
        'value'   => $row ? $row['value'] : null,
    ]);
} catch (\Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Database error']);
}
