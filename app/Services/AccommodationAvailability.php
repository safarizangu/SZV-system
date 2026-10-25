<?php
namespace App\Services;

use App\Models\Database;

/**
 * Room/inventory availability — confirmed MISSING entirely by the
 * enterprise audit. Bookings don't reserve a room in any live-inventory
 * system (SZV books through third-party lodges, not owned rooms), so
 * this doesn't stop a booking from being created; it computes how many
 * rooms are already committed at a given property on a given date, from
 * the itinerary each active booking actually has, so ops can see an
 * over-commitment before it becomes the supplier's problem to catch.
 */
class AccommodationAvailability
{
    /**
     * Rooms already committed at $accommodationName across every active
     * (non-cancelled) booking, for each calendar date in
     * [$rangeStart, $rangeEnd]. A day's calendar date is the booking's
     * travel_date + (itinerary day number - 1) — itinerary days are
     * stored as relative day-offsets, only anchored to a real date once
     * combined with a specific booking.
     *
     * @return array<string,int> date (Y-m-d) => committed room count
     */
    public static function committedRoomsByDate(string $accommodationName, string $rangeStart, string $rangeEnd): array
    {
        $committed = [];
        try {
            $db = Database::getInstance();
            // Only bookings whose travel_date could plausibly overlap the
            // range are worth decoding — a 21-day-max itinerary means a
            // booking starting more than 21 days before rangeStart can't
            // reach into it.
            $stmt = $db->prepare("
                SELECT b.travel_date, i.days
                FROM bookings b
                JOIN itineraries i ON i.id = b.itinerary_id
                WHERE b.payment_status != 'cancelled'
                  AND b.travel_date BETWEEN DATE_SUB(?, INTERVAL 21 DAY) AND ?
            ");
            $stmt->execute([$rangeStart, $rangeEnd]);

            foreach ($stmt->fetchAll() as $row) {
                $days = json_decode($row['days'] ?? '[]', true) ?: [];
                // Calendar date comes from each day's ARRAY POSITION, not
                // its stored 'day' label — the label can start at 0 for an
                // itinerary with a flagged arrival day (see
                // admin/itinerary_edit.php), but the first day-block is
                // always the travel_date itself regardless of what it's
                // labelled, and every day after it is still exactly one
                // calendar day later. Position is the one thing that never
                // changes meaning.
                foreach ($days as $dayIdx => $day) {
                    $accName = trim($day['accommodation_name'] ?? '');
                    if (strcasecmp($accName, $accommodationName) !== 0) continue;

                    $rooms = (int)($day['accommodation_rooms'] ?? 1);
                    if ($rooms <= 0) continue;

                    $date = date('Y-m-d', strtotime($row['travel_date'] . ' +' . $dayIdx . ' days'));
                    if ($date < $rangeStart || $date > $rangeEnd) continue;

                    $committed[$date] = ($committed[$date] ?? 0) + $rooms;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AccommodationAvailability] ' . $e->getMessage());
        }
        return $committed;
    }

    /**
     * Every accommodation that has a real available_rooms figure on file,
     * with its peak (highest single-day) commitment over the given range
     * — the one number that answers "are we at risk of overbooking this
     * property in the next N days."
     *
     * @return array<int,array{id:int,name:string,available_rooms:int,peak_committed:int,peak_date:?string,over_capacity:bool}>
     */
    public static function utilizationSummary(string $rangeStart, string $rangeEnd): array
    {
        $summary = [];
        try {
            $db   = Database::getInstance();
            $accs = $db->query("SELECT id, name, available_rooms FROM accommodations WHERE is_active=1 AND available_rooms IS NOT NULL ORDER BY name")->fetchAll();

            foreach ($accs as $acc) {
                $byDate = self::committedRoomsByDate($acc['name'], $rangeStart, $rangeEnd);
                $peak     = $byDate ? max($byDate) : 0;
                $peakDate = $byDate ? array_search($peak, $byDate, true) : null;
                $summary[] = [
                    'id'              => (int)$acc['id'],
                    'name'            => $acc['name'],
                    'available_rooms' => (int)$acc['available_rooms'],
                    'peak_committed'  => $peak,
                    'peak_date'       => $peakDate,
                    'over_capacity'   => $peak > (int)$acc['available_rooms'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('[AccommodationAvailability] ' . $e->getMessage());
        }
        return $summary;
    }
}
