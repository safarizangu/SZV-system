<?php
namespace App\Services;

use App\Models\Database;

/**
 * ARCH-3: the VAT/service-charge breakdown for a booking — previously
 * implemented independently in views/confirmation.php and
 * api/check_booking.php, which had drifted apart: confirmation.php had
 * since been fixed to prefer the booking's real linked quotation line
 * items (or the package's own cost_breakdown) over a flat percentage
 * guess, while check_booking.php still always used the old percentage
 * guess — so the same booking could show two different breakdowns
 * depending which page you checked it from. Both now call this.
 */
class BookingCostBreakdown
{
    /** @return array{raw_total:float,raw_deposit:float,vat_rate:float,subtotal:float,vat_amount:float,service_charge:float,base_amount:float,balance_due:float,travelers:int,adults:int,children:int,per_person:float} */
    public static function compute(array $booking): array
    {
        $rawTotal   = (float)($booking['total_amount']   ?? 0);
        $rawDeposit = (float)($booking['deposit_amount'] ?? 0);
        $vatRate    = defined('VAT_RATE') ? (float)VAT_RATE : 0.18;
        $subtotal   = round($rawTotal / (1 + $vatRate), 2);
        $vatAmount  = round($rawTotal - $subtotal, 2);
        $balanceDue = round($rawTotal - $rawDeposit, 2);
        $travelers  = (int)($booking['travelers'] ?? 0);
        $adults     = (int)($booking['adults']   ?? $travelers);
        $children   = (int)($booking['children'] ?? 0);
        $perPerson  = ($travelers > 0 && $rawTotal > 0) ? round($rawTotal / $travelers, 2) : 0.0;

        // Service Charge — a real dollar amount everywhere else in the app
        // (admin/package_edit.php's Adults/Children rates, carried through
        // to the actual quotation line items), never a percentage. Preferred
        // source is the booking's own linked quotation's real line items
        // (exactly what was priced); falls back to recomputing from the
        // package's own rates when there's no linked quotation; only
        // reaches for a flat percentage guess if neither is available
        // (very old data with no package link).
        $svcAdultAmount = 0.0;
        $svcChildAmount = 0.0;

        if (!empty($booking['quotation_id'])) {
            try {
                $qs = Database::getInstance()->prepare("SELECT line_items FROM quotations WHERE id=? LIMIT 1");
                $qs->execute([(int)$booking['quotation_id']]);
                $qLineItems = json_decode($qs->fetchColumn() ?: '[]', true) ?: [];
                foreach ($qLineItems as $li) {
                    if (($li['category'] ?? '') !== 'service_fee') continue;
                    $desc = $li['description'] ?? '';
                    $amt  = (float)($li['amount'] ?? 0);
                    if (stripos($desc, 'child') !== false) $svcChildAmount += $amt;
                    else $svcAdultAmount += $amt; // covers "Adults" and any legacy combined line
                }
            } catch (\Exception $e) {}
        }

        if ($svcAdultAmount <= 0 && $svcChildAmount <= 0 && !empty($booking['package_id'])) {
            try {
                $ps = Database::getInstance()->prepare("SELECT cost_breakdown FROM packages WHERE id=? LIMIT 1");
                $ps->execute([(int)$booking['package_id']]);
                $pbd = json_decode($ps->fetchColumn() ?: '{}', true) ?: [];
                $svcAdultAmount = (float)($pbd['service_charge_adult'] ?? $pbd['service_charge_pax'] ?? 0) * $adults;
                $svcChildAmount = (float)($pbd['service_charge_child'] ?? $pbd['service_charge_pax'] ?? 0) * $children;
            } catch (\Exception $e) {}
        }

        if ($svcAdultAmount <= 0 && $svcChildAmount <= 0) {
            $svcRate        = defined('SERVICE_CHARGE_RATE') ? (float)SERVICE_CHARGE_RATE : 0.05;
            $svcAdultAmount = round($subtotal - round($subtotal / (1 + $svcRate), 2), 2);
        }

        $svcAmount  = round($svcAdultAmount + $svcChildAmount, 2);
        $baseAmount = round($subtotal - $svcAmount, 2);

        return [
            'raw_total'       => $rawTotal,
            'raw_deposit'     => $rawDeposit,
            'vat_rate'        => $vatRate,
            'subtotal'        => $subtotal,
            'vat_amount'      => $vatAmount,
            'service_charge'  => $svcAmount,
            'base_amount'     => $baseAmount,
            'balance_due'     => $balanceDue,
            'travelers'       => $travelers,
            'adults'          => $adults,
            'children'        => $children,
            'per_person'      => $perPerson,
        ];
    }
}
