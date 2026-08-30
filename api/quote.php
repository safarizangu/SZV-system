<?php
/**
 * Quotation Engine API endpoint.
 * Returns a full itemised quote as JSON.
 * Used by: custom safari builder, admin quotation tool.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\QuotationEngine;
use App\Middleware\RateLimiter;

RateLimiter::enforceOrDie('quote', 30, 3600);

$params = [
    'adults'             => max(1, (int)($_POST['adults']             ?? $_GET['adults']             ?? 2)),
    'children'           => max(0, (int)($_POST['children']           ?? $_GET['children']           ?? 0)),
    'infants'            => max(0, (int)($_POST['infants']            ?? $_GET['infants']            ?? 0)),
    'rooms'              => max(1, (int)($_POST['rooms']              ?? $_GET['rooms']              ?? 1)),
    'duration_days'      => max(1, (int)($_POST['duration_days']      ?? $_GET['duration_days']      ?? 5)),
    'accommodation_level'=> $_POST['accommodation_level'] ?? $_GET['accommodation_level'] ?? 'midrange',
    'season'             => $_POST['season']              ?? $_GET['season']              ?? 'peak',
    'destinations'       => (array)($_POST['destinations'] ?? $_GET['destinations'] ?? ['serengeti','ngorongoro']),
    'activities'         => (array)($_POST['activities']   ?? $_GET['activities']   ?? []),
    'guide_language'     => $_POST['guide_language']      ?? $_GET['guide_language']      ?? 'english',
    'meal_plan'          => $_POST['meal_plan']           ?? $_GET['meal_plan']           ?? 'fb',
    'internal_flight'    => (bool)($_POST['internal_flight'] ?? $_GET['internal_flight'] ?? false),
    'start_date'         => $_POST['start_date']          ?? $_GET['start_date']          ?? '',
];

// Auto-detect season from date if provided
if (!empty($params['start_date'])) {
    $params['season'] = QuotationEngine::detectSeason($params['start_date']);
}

$engine = new QuotationEngine($params);
$quote  = $engine->calculate();

echo json_encode(['success' => true, 'quote' => $quote]);
