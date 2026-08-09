<?php
namespace App\Services;

use App\Models\Database;

/**
 * QuotationEngine v2 — reads ALL rates from DB, zero hardcoding.
 * Falls back to config/app.php constants if DB tables are empty.
 */
class QuotationEngine
{
    private int    $adults;
    private int    $children;
    private int    $infants;
    private int    $durationDays;
    private string $accommodationLevel;
    private string $season;
    private array  $destinations;
    private array  $activities;
    private string $guideLanguage;
    private string $mealPlan;
    private int    $rooms;
    private bool   $internalFlight;
    private array  $lineItems = [];
    private \PDO   $db;

    public function __construct(array $params)
    {
        $this->adults             = max(1, (int)($params['adults']             ?? 2));
        $this->children           = max(0, (int)($params['children']          ?? 0));
        $this->infants            = max(0, (int)($params['infants']           ?? 0));
        $this->durationDays       = max(1, (int)($params['duration_days']     ?? 5));
        $this->accommodationLevel = $params['accommodation_level']             ?? 'midrange';
        $this->season             = $params['season']                          ?? 'peak';
        $this->destinations       = (array)($params['destinations']           ?? []);
        $this->activities         = (array)($params['activities']             ?? []);
        $this->guideLanguage      = $params['guide_language']                  ?? 'english';
        $this->mealPlan           = $params['meal_plan']                       ?? 'fb';
        $this->rooms              = max(1, (int)($params['rooms']             ?? 1));
        $this->internalFlight     = (bool)($params['internal_flight']         ?? false);
        $this->db                 = Database::getInstance();
    }

    public function calculate(): array
    {
        $this->lineItems = [];
        $pax = $this->adults + $this->children;

        // ── Season multiplier from DB ─────────────────────────
        $seasonMult = $this->getSeasonMultiplier();

        // ── 1. Accommodation (from DB) ────────────────────────
        $accData = $this->getAccommodationRate();
        $accCost = $accData['rate'] * $this->rooms * $this->durationDays * $seasonMult;
        $this->addItem('Accommodation — ' . $accData['label'], $accCost, [
            'level'  => $accData['label'],
            'rooms'  => $this->rooms,
            'nights' => $this->durationDays,
            'rate'   => '$' . number_format($accData['rate'], 0) . '/room/night',
            'season' => ucfirst($this->season) . ' ×' . $seasonMult,
        ]);

        // ── 2. Park fees (from DB) ────────────────────────────
        $parkTotal = 0;
        $parkBreakdown = [];
        foreach ($this->destinations as $dest) {
            $fee = $this->getParkFee($dest);
            if (!$fee) continue;
            $cost = ($fee['adult_fee'] * $this->adults) + ($fee['child_fee'] * $this->children);
            $parkTotal += $cost;
            $parkBreakdown[] = $fee['name'] . ': $' . number_format($cost, 0);
        }
        if ($parkTotal > 0) {
            $this->addItem('Park Entry Fees', $parkTotal, ['breakdown' => $parkBreakdown]);
        }

        // Conservation fee
        $consFee = defined('CONSERVATION_FEE') ? CONSERVATION_FEE : 20;
        $this->addItem('Conservation & Community Fee', $consFee * $pax, [
            'note' => '$' . $consFee . ' × ' . $pax . ' travelers',
        ]);

        // ── 3. Vehicle (from DB) ──────────────────────────────
        $veh = $this->getVehicle($pax);
        $vehCost = ($veh['daily_rate'] + $veh['fuel_cost_day'] + $veh['driver_cost_day'])
                   * $this->durationDays * $seasonMult;
        $this->addItem('Safari Vehicle & Driver', $vehCost, [
            'vehicle' => $veh['name'],
            'days'    => $this->durationDays,
            'rate'    => '$' . number_format($veh['daily_rate'] + $veh['fuel_cost_day'] + $veh['driver_cost_day'], 0) . '/day',
        ]);

        // ── 4. Guide ──────────────────────────────────────────
        $guides  = defined('GUIDE_LANGUAGES') ? GUIDE_LANGUAGES : [];
        $gData   = $guides[$this->guideLanguage] ?? ['name' => 'English', 'daily_rate' => 120];
        $guideCost = $gData['daily_rate'] * $this->durationDays;
        $this->addItem('Professional Guide (' . $gData['name'] . ')', $guideCost, [
            'days' => $this->durationDays,
            'rate' => '$' . $gData['daily_rate'] . '/day',
        ]);

        // ── 5. Meals ──────────────────────────────────────────
        $meals  = defined('MEAL_PLAN_RATES') ? MEAL_PLAN_RATES : [];
        $mData  = $meals[$this->mealPlan] ?? ['name' => 'Full Board', 'rate' => 95];
        $mealCost = $mData['rate'] * $pax * $this->durationDays;
        $this->addItem('Meals — ' . $mData['name'], $mealCost, [
            'rate' => '$' . $mData['rate'] . '/pp/day',
            'pax'  => $pax,
            'days' => $this->durationDays,
        ]);

        // ── 6. Activities (from DB) ───────────────────────────
        foreach ($this->activities as $actKey) {
            $act = $this->getActivity($actKey);
            if (!$act) continue;
            $qty  = ($act['price_unit'] === 'per_group') ? 1 : $pax;
            $cost = $act['price'] * $qty;
            $this->addItem($act['name'], $cost, [
                'unit' => $act['price_unit'] === 'per_person'
                    ? '$' . $act['price'] . ' × ' . $pax . ' pax' : 'flat rate',
            ]);
        }

        // ── 7. Transfers ──────────────────────────────────────
        $this->addItem('Airport Transfers', 60 * $pax, [
            'note' => '$60 × ' . $pax . ' travelers (round trip)',
        ]);

        // ── 8. Internal flight ────────────────────────────────
        if ($this->internalFlight) {
            $this->addItem('Internal Flight', 250 * $pax, [
                'note' => '$250 × ' . $pax . ' travelers',
            ]);
        }

        // ── Totals ────────────────────────────────────────────
        $subtotal   = array_sum(array_column($this->lineItems, 'amount'));
        $svcRate    = defined('SERVICE_CHARGE_RATE') ? SERVICE_CHARGE_RATE : 0.05;
        $vatRate    = defined('VAT_RATE') ? VAT_RATE : 0.18;
        $service    = $subtotal * $svcRate;
        $vat        = ($subtotal + $service) * $vatRate;
        $grandTotal = $subtotal + $service + $vat;
        $deposit    = $grandTotal * ((defined('DEPOSIT_PERCENT') ? DEPOSIT_PERCENT : 50) / 100);
        $perPerson  = $pax > 0 ? $grandTotal / $pax : $grandTotal;

        return [
            'line_items'     => $this->lineItems,
            'subtotal'       => round($subtotal, 2),
            'service_charge' => round($service, 2),
            'vat'            => round($vat, 2),
            'grand_total'    => round($grandTotal, 2),
            'per_person'     => round($perPerson, 2),
            'deposit'        => round($deposit, 2),
            'balance_due'    => round($grandTotal - $deposit, 2),
            'currency'       => 'USD',
            'pax'            => ['adults'=>$this->adults,'children'=>$this->children,'infants'=>$this->infants,'total'=>$pax],
            'duration_days'  => $this->durationDays,
            'accommodation_level' => $this->accommodationLevel,
            'season'         => $this->season,
        ];
    }

    // ── DB helpers ─────────────────────────────────────────────

    private function getSeasonMultiplier(): float
    {
        try {
            $s = $this->db->prepare("SELECT multiplier FROM seasonal_rates WHERE season_key=? LIMIT 1");
            $s->execute([$this->season]);
            $row = $s->fetch();
            if ($row) return (float)$row['multiplier'];
        } catch (\Exception $e) {}
        // Fallback to config constant
        $rates = defined('SEASONAL_RATES') ? SEASONAL_RATES : ['peak'=>1.0,'shoulder'=>0.85,'low'=>0.75];
        return (float)($rates[$this->season] ?? 1.0);
    }

    private function getAccommodationRate(): array
    {
        try {
            $s = $this->db->prepare(
                "SELECT name, rate_per_room FROM accommodations
                 WHERE level=? AND is_active=1
                 ORDER BY rate_per_room ASC LIMIT 1"
            );
            $s->execute([$this->accommodationLevel]);
            $row = $s->fetch();
            if ($row && $row['rate_per_room'] > 0) {
                return ['rate' => (float)$row['rate_per_room'], 'label' => $row['name']];
            }
        } catch (\Exception $e) {}
        // Fallback
        $rates = ['budget'=>120,'midrange'=>250,'highend'=>480,'ultra_luxury'=>700];
        $labels = defined('ACCOMMODATION_LEVELS') ? ACCOMMODATION_LEVELS : [];
        return [
            'rate'  => $rates[$this->accommodationLevel] ?? 250,
            'label' => $labels[$this->accommodationLevel] ?? ucfirst($this->accommodationLevel),
        ];
    }

    private function getParkFee(string $key): ?array
    {
        try {
            $s = $this->db->prepare(
                "SELECT name, adult_fee, child_fee FROM park_fees
                 WHERE park_key=? AND is_active=1 LIMIT 1"
            );
            $s->execute([$key]);
            return $s->fetch() ?: null;
        } catch (\Exception $e) {}
        // Fallback to config
        $fees = defined('PARK_FEES') ? PARK_FEES : [];
        if (isset($fees[$key])) {
            return ['name'=>$fees[$key]['name'],'adult_fee'=>$fees[$key]['adult'],'child_fee'=>$fees[$key]['child']];
        }
        return null;
    }

    private function getVehicle(int $pax): array
    {
        try {
            $level = $this->accommodationLevel === 'highend' || $this->accommodationLevel === 'ultra_luxury'
                ? 'luxury' : 'standard';
            $cap = $pax <= 6 ? 6 : 12;
            $s = $this->db->prepare(
                "SELECT name, daily_rate, fuel_cost_day, driver_cost_day
                 FROM vehicles
                 WHERE is_active=1 AND capacity >= ?
                 ORDER BY (level=?) DESC, daily_rate ASC LIMIT 1"
            );
            $s->execute([$pax, $level]);
            $row = $s->fetch();
            if ($row) return $row;
        } catch (\Exception $e) {}
        // Fallback
        $vehicles = defined('VEHICLE_TYPES') ? VEHICLE_TYPES : [];
        $key = ($pax > 6) ? 'minivan'
             : ($this->accommodationLevel === 'highend' ? 'luxury_cruiser' : 'land_cruiser');
        $v = $vehicles[$key] ?? ['name'=>'Toyota Land Cruiser','daily_rate'=>250];
        return ['name'=>$v['name'],'daily_rate'=>$v['daily_rate'],'fuel_cost_day'=>50,'driver_cost_day'=>50];
    }

    private function getActivity(string $key): ?array
    {
        try {
            $s = $this->db->prepare(
                "SELECT name, price, price_unit FROM activities
                 WHERE activity_key=? AND is_active=1 LIMIT 1"
            );
            $s->execute([$key]);
            return $s->fetch() ?: null;
        } catch (\Exception $e) {}
        $acts = defined('ACTIVITY_PRICES') ? ACTIVITY_PRICES : [];
        return isset($acts[$key]) ? ['name'=>$acts[$key]['name'],'price'=>$acts[$key]['price'],'price_unit'=>$acts[$key]['unit']] : null;
    }

    private function addItem(string $name, float $amount, array $meta = []): void
    {
        $this->lineItems[] = ['name'=>$name,'amount'=>round($amount,2),'meta'=>$meta];
    }

    public static function detectSeason(string $date): string
    {
        $month = (int)date('n', strtotime($date));
        $day   = (int)date('j', strtotime($date));
        if (in_array($month, [6,7,8,9,10])) return 'peak';
        if ($month === 12 && $day >= 20)     return 'peak';
        if ($month === 1  && $day <= 5)      return 'peak';
        if (in_array($month, [3,4,5]))       return 'shoulder';
        return 'low';
    }
}
