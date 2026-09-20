<?php
namespace App\Services;

/**
 * Schedules the 5 standard reminder emails for a new booking.
 * Was previously duplicated verbatim in api/book.php and
 * api/convert_quotation.php — extracted so both booking paths
 * schedule reminders identically.
 */
class BookingReminderScheduler
{
    public static function schedule(\PDO $pdo, string $ref, string $travelDate): void
    {
        try {
            $travel   = new \DateTime($travelDate);
            $now      = new \DateTime();
            $daysAway = (int)$now->diff($travel)->days;
            $reminders = [];

            // Deposit due — 48 hours from now
            $depositDue = (clone $now)->modify('+48 hours');
            $reminders[] = [
                'type'    => 'deposit_due',
                'send_at' => $depositDue->format('Y-m-d H:i:s'),
                'subject' => "Deposit Due — Booking $ref | Safari Zangu Ventures",
            ];

            // Balance due — 45 days before travel (only if far enough away)
            if ($daysAway > 50) {
                $balanceDue = (clone $travel)->modify('-45 days');
                $reminders[] = [
                    'type'    => 'balance_due',
                    'send_at' => $balanceDue->format('Y-m-d 09:00:00'),
                    'subject' => "Balance Due — Booking $ref | Safari Zangu Ventures",
                ];
            }

            // Departure reminder — 7 days before travel
            if ($daysAway > 10) {
                $departure = (clone $travel)->modify('-7 days');
                $reminders[] = [
                    'type'    => 'departure',
                    'send_at' => $departure->format('Y-m-d 09:00:00'),
                    'subject' => "Your Safari Departs in 7 Days — $ref",
                ];
            }

            // Post-trip feedback — 3 days after travel
            $postTrip = (clone $travel)->modify('+3 days');
            $reminders[] = [
                'type'    => 'post_trip',
                'send_at' => $postTrip->format('Y-m-d 10:00:00'),
                'subject' => "How Was Your Safari? — $ref",
            ];

            // Win-back — 6 months after travel. The cron that sends this
            // (api/cron/process_reminders.php) checks at send time whether
            // this customer has booked again since, and skips it without
            // sending if they have — scheduling it now is safe regardless
            // of what happens between now and then.
            $winBack = (clone $travel)->modify('+180 days');
            $reminders[] = [
                'type'    => 'win_back',
                'send_at' => $winBack->format('Y-m-d 10:00:00'),
                'subject' => "We'd Love to Welcome You Back — Safari Zangu Ventures",
            ];

            $stmt = $pdo->prepare("
                INSERT INTO booking_reminders (booking_ref, type, send_at, subject)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($reminders as $r) {
                $stmt->execute([$ref, $r['type'], $r['send_at'], $r['subject']]);
            }
        } catch (\Exception $e) {
            error_log('[SZV Reminders] Failed to schedule for ' . $ref . ': ' . $e->getMessage());
            // Non-fatal — booking still created
        }
    }
}
