<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db   = \App\Models\Database::getInstance();
$type = $_GET['type'] ?? 'bookings';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="szv-'.$type.'-'.date('Y-m-d').'.csv"');
header('Cache-Control: no-cache');
$out = fopen('php://output','w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
if ($type==='bookings') {
    fputcsv($out,['Ref','Name','Email','Phone','Country','Package','Travel Date','Nights','Adults','Children','Total','Status','Created']);
    $s=$db->prepare("SELECT * FROM bookings WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
    $s->execute([$from,$to]);
    foreach($s->fetchAll() as $r) fputcsv($out,[$r['booking_ref'],$r['full_name'],$r['email'],$r['phone']??'',$r['country']??'',$r['package_name']??'',$r['travel_date']??'',$r['duration_days']??'',$r['adults']??'',$r['children']??0,'$'.number_format((float)($r['total_amount']??0),2),$r['payment_status']??'',$r['created_at']??'']);
} elseif ($type==='quotations') {
    fputcsv($out,['Ref','Client','Email','Country','Package','Nights','Adults','Total','Per Person','Status','Created']);
    $s=$db->prepare("SELECT * FROM quotations WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
    $s->execute([$from,$to]);
    foreach($s->fetchAll() as $r) fputcsv($out,[$r['quote_ref'],$r['client_name'],$r['client_email'],$r['client_country']??'',$r['package_name']??'',$r['duration_days']??'',$r['adults']??'','$'.number_format((float)($r['grand_total']??0),2),'$'.number_format((float)($r['per_person']??0),2),$r['status']??'draft',$r['created_at']??'']);
}
fclose($out);
