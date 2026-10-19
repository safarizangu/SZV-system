<?php
namespace App\Validators;

class BookingValidator
{
    private array $errors = [];

    public function validate(array $data): bool
    {
        $this->errors = [];

        if (empty(trim($data['full_name'] ?? ''))) {
            $this->errors['full_name'] = 'Full name is required.';
        }

        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'A valid email address is required.';
        }

        if (empty(trim($data['phone'] ?? ''))) {
            $this->errors['phone'] = 'Phone number is required.';
        }

        // Form sends adults + children separately; both sum to travelers
        $adults   = (int)($data['adults']   ?? $data['travelers'] ?? 0);
        $children = (int)($data['children'] ?? 0);
        $travelers = $adults + $children;
        if ($adults < 1 || $adults > 50) {
            $this->errors['adults'] = 'Please select at least 1 adult (max 50).';
        }
        if ($travelers < 1 || $travelers > MAX_PARTY_SIZE) {
            $this->errors['travelers'] = 'Total travelers must be between 1 and ' . MAX_PARTY_SIZE . '.';
        }

        if (empty($data['travel_date'])) {
            $this->errors['travel_date'] = 'Travel date is required.';
        } elseif (strtotime($data['travel_date']) < strtotime('+1 day')) {
            $this->errors['travel_date'] = 'Travel date must be at least tomorrow.';
        }

        if (empty($data['package_id'])) {
            $this->errors['package_id'] = 'Please select a package.';
        }

        return empty($this->errors);
    }

    public function errors(): array { return $this->errors; }
    public function firstError(): string { return reset($this->errors) ?: ''; }
}
