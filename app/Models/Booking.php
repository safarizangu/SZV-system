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

    public function create(array $data): string
    {
        $ref  = $this->generateRef();
        $stmt = $this->db->prepare("
            INSERT INTO bookings
                (booking_ref, full_name, email, phone, travelers,
                 package_id, package_name, accommodation_level,
                 travel_date, special_request,
                 total_amount, deposit_amount,
                 payment_status, payment_method, created_at)
            VALUES
                (:ref, :name, :email, :phone, :travelers,
                 :pkg_id, :pkg_name, :acc_level,
                 :travel_date, :special_request,
                 :total, :deposit,
                 'pending', :method, NOW())
        ");
        $stmt->execute([
            'ref'            => $ref,
            'name'           => $data['full_name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'],
            'travelers'      => (int)$data['travelers'],
            'pkg_id'         => $data['package_id'] ?? null,
            'pkg_name'       => $data['package_name'] ?? '',
            'acc_level'      => $data['accommodation_level'] ?? 'midrange',
            'travel_date'    => $data['travel_date'],
            'special_request'=> $data['special_request'] ?? '',
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

    public function all(int $limit = 50, int $offset = 0): array
    {
        return $this->db->query(
            "SELECT * FROM bookings ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
        )->fetchAll();
    }

    public function count(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    }

    public function totalRevenue(): float
    {
        return (float)$this->db->query(
            "SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status != 'cancelled'"
        )->fetchColumn();
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
