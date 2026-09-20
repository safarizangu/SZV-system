<?php
namespace App\Helpers;

class Formatter
{
    public static function currency(float $amount, string $currency = 'USD'): string
    {
        return $currency . ' ' . number_format($amount, 0, '.', ',');
    }

    /** "~£X" — an approximate GBP figure for display next to a USD price. */
    public static function gbpApprox(float $usdAmount): string
    {
        return self::approxIn($usdAmount, 'GBP') ?? ('~£' . number_format($usdAmount * (defined('GBP_RATE') ? GBP_RATE : 0.75), 0, '.', ','));
    }

    /**
     * "~<symbol>X" in any currency_rates row, DB-first (admin-managed,
     * see admin/currency_rates.php). Returns null if the currency isn't
     * on file or isn't active — display-only, never touches a real
     * charge, which always stays in USD.
     */
    public static function approxIn(float $usdAmount, string $currencyCode): ?string
    {
        static $cache = [];
        if (!array_key_exists($currencyCode, $cache)) {
            $cache[$currencyCode] = null;
            try {
                $db   = \App\Models\Database::getInstance();
                $stmt = $db->prepare("SELECT symbol, rate_to_usd FROM currency_rates WHERE code=? AND is_active=1 LIMIT 1");
                $stmt->execute([$currencyCode]);
                $cache[$currencyCode] = $stmt->fetch() ?: null;
            } catch (\Throwable $e) {}
        }
        $row = $cache[$currencyCode];
        if (!$row) return null;
        return '~' . $row['symbol'] . number_format($usdAmount * (float)$row['rate_to_usd'], 0, '.', ',');
    }

    /** All active currencies, USD first — for a currency-selector UI. */
    public static function activeCurrencies(): array
    {
        try {
            $db = \App\Models\Database::getInstance();
            return $db->query("SELECT code, name, symbol, rate_to_usd FROM currency_rates WHERE is_active=1 ORDER BY code='USD' DESC, code")->fetchAll();
        } catch (\Throwable $e) { return []; }
    }

    /**
     * "~£1,238 · ~€1,518" — every active non-USD currency joined, for the
     * small approximate-price hint next to a USD package price. Grows
     * automatically as an admin activates more currencies in
     * admin/currency_rates.php, with no further code change needed here.
     */
    public static function approxAll(float $usdAmount): string
    {
        $parts = [];
        foreach (self::activeCurrencies() as $c) {
            if ($c['code'] === 'USD') continue;
            $parts[] = '~' . $c['symbol'] . number_format($usdAmount * (float)$c['rate_to_usd'], 0, '.', ',');
        }
        return implode(' &middot; ', $parts);
    }

    public static function date(string $date, string $format = 'd F Y'): string
    {
        $ts = strtotime($date);
        return $ts ? date($format, $ts) : $date;
    }

    public static function accommodationLabel(string $level): string
    {
        return ACCOMMODATION_LEVELS[$level] ?? ucfirst($level);
    }

    /**
     * Travel-execution status computed from travel_date + a known trip
     * duration — no separate manual status field to keep in sync.
     * $durationDays is nullable (package/itinerary link can be missing on
     * older bookings); a null duration falls back to a single-day trip,
     * which only affects the Upcoming/In Progress boundary, never
     * Completed vs not.
     */
    public static function tripStatus(?string $travelDate, ?int $durationDays = null): array
    {
        if (!$travelDate) {
            return ['label' => 'Unscheduled', 'color' => '#6b7280', 'bg' => '#f3f4f6'];
        }
        $today   = date('Y-m-d');
        $start   = date('Y-m-d', strtotime($travelDate));
        $nights  = max(1, $durationDays ?? 1) - 1;
        $end     = date('Y-m-d', strtotime($travelDate . ' +' . $nights . ' days'));

        if ($today < $start) return ['label' => 'Upcoming', 'color' => '#1e40af', 'bg' => '#eff6ff'];
        if ($today > $end)   return ['label' => 'Completed', 'color' => '#374151', 'bg' => '#f3f4f6'];
        return ['label' => 'In Progress', 'color' => '#166534', 'bg' => '#f0fdf4'];
    }

    public static function sanitize(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public static function e(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    public static function generateRef(): string
    {
        return 'SZV' . strtoupper(bin2hex(random_bytes(5))); // cryptographically random
    }
}
