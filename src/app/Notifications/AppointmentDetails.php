<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;

/**
 * Presentation values shared by every appointment notification channel.
 */
final readonly class AppointmentDetails
{
    public function __construct(
        public string $businessName,
        public string $serviceName,
        public ?string $staffName,
        public string $when,
    ) {}

    public static function from(Appointment $appointment): self
    {
        $appointment->loadMissing(['tenant', 'service', 'staffMember']);

        return new self(
            businessName: $appointment->tenant->name,
            serviceName: $appointment->service->name,
            staffName: $appointment->staffMember?->name,
            when: $appointment->start_time->format('l, F j, Y \a\t H:i'),
        );
    }
}
