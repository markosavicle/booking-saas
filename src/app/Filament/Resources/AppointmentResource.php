<?php

namespace App\Filament\Resources;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Filament\Forms\TenantSelect;
use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TenantSelect::make()
                    // Services and staff belong to one shop, so a different shop starts from empty picks.
                    ->afterStateUpdated(function (Forms\Set $set): void {
                        $set('service_id', null);
                        $set('staff_member_id', null);
                    }),
                Forms\Components\Select::make('service_id')
                    ->relationship('service', 'name', fn (Builder $query, Forms\Get $get): Builder => $query
                        ->where('services.tenant_id', $get('tenant_id')))
                    ->rule(static::belongsToShop(Service::class))
                    ->required(),
                Forms\Components\Select::make('staff_member_id')
                    ->relationship('staffMember', 'name', fn (Builder $query, Forms\Get $get): Builder => $query
                        ->where('staff_members.tenant_id', $get('tenant_id')))
                    ->rule(static::belongsToShop(StaffMember::class)),
                // Users are global, so only customer accounts are offered, never another shop's admins.
                Forms\Components\Select::make('user_id')
                    ->label('Customer')
                    ->relationship('user', 'name', fn (Builder $query): Builder => $query->where('role', UserRole::Customer))
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! User::whereKey($value)->where('role', UserRole::Customer)->exists()) {
                            $fail('Pick a customer account.');
                        }
                    })
                    ->searchable()
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->timezone(fn (?Appointment $record): string => static::shopTimezone($record))
                    ->required(),
                Forms\Components\DateTimePicker::make('end_time')
                    ->timezone(fn (?Appointment $record): string => static::shopTimezone($record))
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(AppointmentStatus::class)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('staffMember.name')
                    ->label('Staff')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->timezone(fn (Appointment $record): string => static::shopTimezone($record))
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->timezone(fn (Appointment $record): string => static::shopTimezone($record))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }

    /**
     * Option lists can be bypassed from the browser; re-check the picked id belongs to the form's shop.
     *
     * @param  class-string<Service|StaffMember>  $model
     */
    private static function belongsToShop(string $model): Closure
    {
        return fn (Forms\Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($model, $get): void {
            if (filled($value) && ! $model::whereKey($value)->where('tenant_id', $get('tenant_id'))->exists()) {
                $fail("Pick only this shop's options.");
            }
        };
    }

    /**
     * Appointments are stored in UTC but edited and shown in the shop's wall-clock time.
     */
    private static function shopTimezone(?Appointment $record): string
    {
        return $record?->tenant?->timezone
            ?? auth()->user()?->tenant?->timezone
            ?? config('app.timezone');
    }
}
