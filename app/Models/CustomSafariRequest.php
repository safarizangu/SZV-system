<?php
namespace App\Models;

class CustomSafariRequest
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $customerId = null;
        if (!empty($data['full_name']) && !empty($data['email'])) {
            try {
                $customerId = (new Customer())->findOrCreateByEmail(
                    $data['email'], $data['full_name'], $data['phone'] ?? null, $data['country'] ?? null
                );
            } catch (\Exception $e) { error_log('[CustomSafariRequest Customer] ' . $e->getMessage()); }
        }

        $stmt = $this->db->prepare("
            INSERT INTO custom_safari_requests
                (customer_id, full_name, email, phone, country, travelers, adults, children, infants,
                 start_date, duration_days, destinations, message, flexible_dates,
                 guide_language, mainland_accommodation, beach_accommodation,
                 meal_plan, room_layout, accommodation_standard, total_budget,
                 experiences, status, created_at)
            VALUES
                (:customer_id, :full_name, :email, :phone, :country, :travelers, :adults, :children, :infants,
                 :start_date, :duration_days, :destinations, :message, :flexible_dates,
                 :guide_language, :mainland_accommodation, :beach_accommodation,
                 :meal_plan, :room_layout, :accommodation_standard, :total_budget,
                 :experiences, 'new', NOW())
        ");
        $stmt->execute([
            'customer_id'             => $customerId,
            'full_name'               => $data['full_name']               ?? '',
            'email'                   => $data['email']                   ?? '',
            'phone'                   => $data['phone']                   ?? '',
            'country'                 => $data['country']                 ?? '',
            'travelers'               => $data['travelers']               ?? '',
            'adults'                  => $data['adults']                  ?? null,
            'children'                => $data['children']                ?? null,
            'infants'                 => $data['infants']                 ?? null,
            'start_date'              => $data['start_date']              ?? null,
            'duration_days'           => $data['duration_days']           ?? null,
            'destinations'            => $data['destinations']            ?? '',
            'message'                 => $data['message']                 ?? '',
            'flexible_dates'          => $data['flexible_dates']          ?? null,
            'guide_language'          => $data['guide_language']          ?? null,
            'mainland_accommodation'  => $data['mainland_accommodation']  ?? null,
            'beach_accommodation'     => $data['beach_accommodation']     ?? null,
            'meal_plan'               => $data['meal_plan']               ?? null,
            'room_layout'             => $data['room_layout']             ?? null,
            'accommodation_standard'  => $data['accommodation_standard']  ?? null,
            'total_budget'            => $data['total_budget']            ?? null,
            'experiences'             => is_array($data['experiences'] ?? null)
                                            ? implode(',', $data['experiences'])
                                            : ($data['experiences'] ?? ''),
        ]);
        return (int)$this->db->lastInsertId();
    }
}
