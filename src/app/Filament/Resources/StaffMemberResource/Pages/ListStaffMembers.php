<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Resources\StaffMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaffMembers extends ListRecords
{
    protected static string $resource = StaffMemberResource::class;

    // CreateAction checks the resource policy, so it only appears for roles allowed to create.
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
