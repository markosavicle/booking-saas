<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * A booking the customer has asked for but not yet confirmed by SMS code.
 */
final readonly class BookingDraft
{
    public function __construct(
        public int $serviceId,
        public ?int $staffMemberId,
        public CarbonImmutable $start,
        public string $name,
        public string $phone,
        public ?string $email,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'staff_member_id' => $this->staffMemberId,
            'start' => $this->start->utc()->toIso8601String(),
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    /**
     * @param  array<string, int|string|null>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            serviceId: (int) $data['service_id'],
            staffMemberId: $data['staff_member_id'] === null ? null : (int) $data['staff_member_id'],
            start: CarbonImmutable::parse((string) $data['start'])->utc(),
            name: (string) $data['name'],
            phone: (string) $data['phone'],
            email: $data['email'] === null ? null : (string) $data['email'],
        );
    }
}
