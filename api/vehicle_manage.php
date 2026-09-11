<?php
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
header('Content-Type: application/json');

function vrespond(array $d): void { ob_end_clean(); header('Content-Type: application/json'); echo json_encode($d); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') vrespond(['success'=>false,'message'=>'Invalid method.']);

\App\Middleware\CsrfMiddleware::validateOrDie();

$action = $_POST['action'] ?? '';
$db     = \App\Models\Database::getInstance();

if ($action === 'add_vehicle') {
    $name   = trim($_POST['name']   ?? '');
    $cap    = max(1,(int)($_POST['capacity']       ?? 6));
    $rate   = (float)($_POST['daily_rate']         ?? 0);
    $fuel   = (float)($_POST['fuel_cost_day']      ?? 0);
    $driver = (float)($_POST['driver_cost_day']    ?? 0);
    $level  = trim($_POST['level'] ?? 'standard');

    if (!$name) vrespond(['success'=>false,'message'=>'Vehicle name is required.']);

    try {
        $db->prepare("INSERT INTO vehicles (name, capacity, daily_rate, fuel_cost_day, driver_cost_day, level, is_active) VALUES (?,?,?,?,?,?,1)")
           ->execute([$name, $cap, $rate, $fuel, $driver, $level]);
        $id = $db->lastInsertId();
        vrespond(['success'=>true, 'id'=>$id, 'name'=>$name, 'daily_rate'=>$rate, 'fuel_cost_day'=>$fuel, 'driver_cost_day'=>$driver]);
    } catch(\Exception $e) {
        vrespond(['success'=>false,'message'=>$e->getMessage()]);
    }
}

vrespond(['success'=>false,'message'=>'Unknown action.']);
