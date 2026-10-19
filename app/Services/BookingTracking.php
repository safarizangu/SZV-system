<?php
namespace App\Services;

/**
 * One place for the Track Booking password: generating it at
 * booking-creation time (direct public booking, agent direct booking,
 * quotation→booking conversion — see issue()'s callers), and verifying it
 * later at views/track.php. Hashed at rest like every other credential
 * in this app; the plaintext only ever exists for the one request that
 * creates it, to be emailed immediately and then discarded.
 */
class BookingTracking
{
    // Unambiguous on screen and when read aloud over WhatsApp — no 0/O, 1/I/l.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LENGTH   = 8;

    /** Generates a fresh password, hashes+stores it against the booking, returns the plaintext to email. */
    public static function issue(\PDO $db, string $bookingRef): string
    {
        $password = self::generatePassword();
        $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $db->prepare("UPDATE bookings SET tracking_password_hash=? WHERE booking_ref=?")
           ->execute([$hash, $bookingRef]);
        return $password;
    }

    private static function generatePassword(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    /** Returns the full booking row on a correct ref+password match, null otherwise. */
    public static function verify(\PDO $db, string $bookingRef, string $password): ?array
    {
        if ($bookingRef === '' || $password === '') return null;
        $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_ref = ? LIMIT 1");
        $stmt->execute([$bookingRef]);
        $booking = $stmt->fetch();
        if (!$booking || empty($booking['tracking_password_hash'])) return null;
        if (!password_verify($password, $booking['tracking_password_hash'])) return null;
        return $booking;
    }
}
