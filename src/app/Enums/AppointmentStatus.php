<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Canceled = 'canceled';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Statuses that occupy a slot in the calendar.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
