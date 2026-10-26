<?php
namespace App\Models;

/**
 * Booking model
 */
class Booking
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * ARCH-8: brought back in sync with api/book.php's real INSERT, which
     * this had drifted from — customer_id, country, adults, and children
     * were all columns the production booking flow actually writes that
     * this method silently dropped. Nothing in production currently calls
     * this method (confirmed by grep; api/book.php builds its own INSERT
     * inline rather than going through the model), only the test suite —
     * but a model method that's wrong is worse than one that's merely
     * unused, since the next real caller would trust it. Also now
     * resolves the customer record itself (Customer::findOrCreateByEmail,
     * the same call api/book.php makes before its own insert), so this is
     * a genuinely complete, correct way to create a booking on its own,
     * not a thin wrapper requiring the caller to already know about the
     * Customer model.
     */
    public function create(array $data): string
    {
        $ref = $this->generateRef();

        $customerId = null;
        try {
            $customerId = (new Customer())->findOrCreateByEmail(
                $data['email'], $data['full_name'], $data['phone'] ?? null, $data['country'] ?? null
            );
        } catch (\Exception $e) {
            error_log('[Booking::create] Customer] ' . $e->getMessage());
        }

        $stmt = $this->db->prepare("
            INSERT INTO bookings
                (booking_ref, customer_id, full_name, email, phone, country, travelers, adults, children,
                 rooms, airport_pickup,
                 package_id, package_name, accommodation_level,
                 travel_date, special_request, trip_extension,
                 total_amount, deposit_amount,
                 payment_status, payment_method, created_at)
            VALUES
                (:ref, :customer_id, :name, :email, :phone, :country, :travelers, :adults, :children,
                 :rooms, :airport_pickup,
                 :pkg_id, :pkg_name, :acc_level,
                 :travel_date, :special_request, :trip_extension,
                 :total, :deposit,
                 'pending', :method, NOW())
        ");
        $stmt->execute([
            'ref'            => $ref,
            'customer_id'    => $customerId,
            'name'           => $data['full_name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'],
            'country'        => $data['country'] ?? null,
            'travelers'      => (int)$data['travelers'],
            'adults'         => (int)($data['adults'] ?? $data['travelers']),
            'children'       => (int)($data['children'] ?? 0),
            'rooms'          => isset($data['rooms']) ? (int)$data['rooms'] : null,
            'airport_pickup' => !empty($data['airport_pickup']) ? 1 : 0,
            'pkg_id'         => $data['package_id'] ?? null,
            'pkg_name'       => $data['package_name'] ?? '',
            'acc_level'      => $data['accommodation_level'] ?? 'midrange',
            'travel_date'    => $data['travel_date'],
            'special_request'=> $data['special_request'] ?? '',
            'trip_extension' => $data['trip_extension'] ?? null,
            'total'          => $data['total_amount'],
            'deposit'        => $data['deposit_amount'],
            'method'         => $data['payment_method'] ?? null,
        ]);
        return $ref;
    }

    public function findByRefAndDate(string $ref, string $date): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM bookings WHERE booking_ref = ? AND travel_date = ? LIMIT 1"
        );
        $stmt->execute([strtoupper($ref), $date]);
        return $stmt->fetch() ?: null;
    }

    public function count(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    }

    public function updatePaymentStatus(string $ref, string $status, ?string $paypalId = null): void
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings SET payment_status = ?, paypal_order_id = ?, updated_at = NOW()
             WHERE booking_ref = ?"
        );
        $stmt->execute([$status, $paypalId, $ref]);
    }

    private function generateRef(): string
    {
        return \App\Helpers\Formatter::generateRef();
    }
}
