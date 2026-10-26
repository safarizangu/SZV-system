<?php
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Helpers\Formatter;

// SEC-9: was a direct $_SESSION check, bypassing AuthMiddleware::requireAdmin()
// entirely — the 30-min inactivity timeout never applied here. requireAdmin()
// redirects rather than returning JSON on failure, same as every other
// admin-only api/*.php endpoint in this codebase.
AuthMiddleware::requireAdmin();

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
    'emails_pending'   => (int)safeVal($db,"SELECT COUNT(*) FROM email_queue WHERE status='pending'"),
    'emails_failed'    => (int)safeVal($db,"SELECT COUNT(*) FROM email_queue WHERE status='failed'"),
    'reminders_due'    => (int)safeVal($db,"SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)"),
    'timestamp'        => date('H:i:s'),
    'formatted_revenue'=> 'USD ' . number_format((float)safeVal($db, "SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status!='cancelled'"), 0),
];

ob_end_clean();
echo json_encode($data);
