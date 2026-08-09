<?php
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Helpers\Formatter;

// Only callable from admin session
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = \App\Models\Database::getInstance();

function safeVal($db, $sql, $default = 0) {
    try { return $db->query($sql)->fetchColumn() ?? $default; }
    catch(\Exception $e) { return $default; }
}

$data = [
    'bookings_total'   => (int)safeVal($db, "SELECT COUNT(*) FROM bookings"),
    'bookings_today'   => (int)safeVal($db, "SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=CURDATE()"),
    'bookings_week'    => (int)safeVal($db, "SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)"),
    'revenue_total'    => (float)safeVal($db, "SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status!='cancelled'"),
    'revenue_month'    => (float)safeVal($db, "SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND payment_status!='cancelled'"),
    'requests_new'     => (int)safeVal($db, "SELECT COUNT(*) FROM custom_safari_requests WHERE status='new'"),
    'messages_unread'  => (int)safeVal($db, "SELECT COUNT(*) FROM contact_messages WHERE is_read=0"),
    'quotes_pending'   => (int)safeVal($db, "SELECT COUNT(*) FROM quotations WHERE status IN ('draft','sent')"),
    'leads_active'     => (int)safeVal($db, "SELECT COUNT(*) FROM leads WHERE pipeline_stage NOT IN ('completed','lost')"),
    'payments_pending' => (int)safeVal($db, "SELECT COUNT(*) FROM bookings WHERE payment_status='pending'"),
    'timestamp'        => date('H:i:s'),
    'formatted_revenue'=> 'USD ' . number_format((float)safeVal($db, "SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status!='cancelled'"), 0),
];

ob_end_clean();
echo json_encode($data);
