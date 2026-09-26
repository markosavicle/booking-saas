<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\AppointmentStatus;
use App\Filament\Forms\TenantSelect;
use App\Filament\Resources\StaffMemberResource\Pages;
use App\Models\Service;
use App\Models\StaffMember;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffMemberResource extends Resource
{
    protected static ?string $model = StaffMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TenantSelect::make()
                    // Services belong to one shop, so a different shop starts from an empty list.
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('services', [])),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('services')
                    ->helperText('The services this barber performs. Only these can be booked with them.')
                    ->relationship('services', 'name', fn (Builder $query, Forms\Get $get): Builder => $query
                        ->where('services.tenant_id', $get('tenant_id'))
                        ->orderBy('name'))
                    ->multiple()
                    ->preload()
                    // Option lists can be bypassed from the browser; re-check every id server-side.
                    ->rule(fn (Forms\Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $ids = array_filter((array) $value);

                        if ($ids !== [] && Service::whereKey($ids)->where('tenant_id', $get('tenant_id'))->count() !== count($ids)) {
                            $fail("Pick only this shop's services.");
                        }
                    }),
                Forms\Components\Toggle::make('is_active')
                    ->label('Taking bookings')
                    ->helperText('Switch off instead of deleting to keep a barber\'s booking history.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['tenant', 'services'])
                ->withExists(['appointments as has_upcoming_appointments' => fn (Builder $query) => $query
                    ->whereIn('status', AppointmentStatus::blocking())
                    ->where('start_time', '>', now())]))
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Shop')
                    ->sortable()
                    ->hidden(fn (): bool => TenantSelect::shopAdmin() !== null),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('services.name')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Shop')
                    ->relationship('tenant', 'name')
                    ->hidden(fn (): bool => TenantSelect::shopAdmin() !== null),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Deleting would silently unassign their upcoming customers; deactivate those instead.
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (StaffMember $record): bool => $record->hasUpcomingAppointments()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffMembers::route('/'),
            'create' => Pages\CreateStaffMember::route('/create'),
            'edit' => Pages\EditStaffMember::route('/{record}/edit'),
        ];
    }
}
