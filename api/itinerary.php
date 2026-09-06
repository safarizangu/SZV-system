<?php
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';

// JSON auth check — no redirect for API endpoints
if (empty($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

function irespond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$db     = \App\Models\Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── LIST ──────────────────────────────────────────────────────
if ($action === 'list') {
    try {
        $rows = $db->query("SELECT id,name,slug,duration_days,accommodation_level,
                            base_price_pax,is_active FROM itineraries
                            WHERE is_active=1 ORDER BY sort_order,name")->fetchAll();
        irespond(['success'=>true,'data'=>$rows]);
    } catch(\Exception $e) { irespond(['success'=>false,'message'=>$e->getMessage()]); }
}

// ── GET SINGLE ────────────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $s = $db->prepare("SELECT * FROM itineraries WHERE id=?");
        $s->execute([$id]);
        $itin = $s->fetch();
        if (!$itin) irespond(['success'=>false,'message'=>'Not found']);
        $itin['days']     = json_decode($itin['days']     ?? '[]', true) ?: [];
        $itin['includes'] = json_decode($itin['includes'] ?? '[]', true) ?: [];
        $itin['excludes'] = json_decode($itin['excludes'] ?? '[]', true) ?: [];
        irespond(['success'=>true,'data'=>$itin]);
    } catch(\Exception $e) { irespond(['success'=>false,'message'=>$e->getMessage()]); }
}

// ── GENERATE QUOTATION LINE ITEMS ─────────────────────────────
if ($action === 'quote_items') {
    $id       = (int)($_GET['id']       ?? 0);
    $adults   = max(1,(int)($_GET['adults']   ?? 2));
    $children = max(0,(int)($_GET['children'] ?? 0));

    try {
        $s = $db->prepare("SELECT * FROM itineraries WHERE id=?");
        $s->execute([$id]);
        $itin = $s->fetch();
        if (!$itin) irespond(['success'=>false,'message'=>'Itinerary not found']);

        $built = \App\Services\ItineraryQuoteBuilder::build($itin, $adults, $children);

        irespond([
            'success'   => true,
            'itinerary' => [
                'name'                => $itin['name'],
                'duration_days'       => $itin['duration_days'],
                'overview'            => $itin['overview'],
                'accommodation_level' => $itin['accommodation_level'],
                'base_price_pax'      => (float)$itin['base_price_pax'],
            ],
        ] + $built);

    } catch(\Exception $e) {
        irespond(['success'=>false,'message'=>$e->getMessage()]);
    }
}

irespond(['success'=>false,'message'=>'Unknown action: '.$action]);
