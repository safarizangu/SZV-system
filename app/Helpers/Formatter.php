<?php
namespace App\Helpers;

class Formatter
{
    public static function currency(float $amount, string $currency = 'USD'): string
    {
        return $currency . ' ' . number_format($amount, 0, '.', ',');
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

    public static function bookingStatusBadge(string $status): string
    {
        $map = [
            'pending'       => ['Pending',        'badge-pending'],
            'deposit_paid'  => ['Deposit Paid',   'badge-deposit_paid'],
            'fully_paid'    => ['Fully Paid',      'badge-fully_paid'],
            'cancelled'     => ['Cancelled',       'badge-cancelled'],
        ];
        [$label, $class] = $map[$status] ?? [$status, 'badge-pending'];
        return "<span class=\"badge $class\">$label</span>";
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
        return 'SZV' . date('Y') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    }
}
