<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use DateTimeZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Shops';

    protected static ?string $modelLabel = 'shop';

    /** @var array<string, string> */
    private const array CURRENCIES = [
        'EUR' => 'Euro (€)',
        'RSD' => 'Serbian dinar (RSD)',
        'USD' => 'US dollar ($)',
        'GBP' => 'British pound (£)',
        'CHF' => 'Swiss franc (CHF)',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shop')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->helperText('Public booking URL. Generated from the name when left empty.')
                            ->alphaDash()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            // Changing it breaks every link and QR code a shop has printed.
                            ->disabled(fn (): bool => ! static::currentUser()?->isSuperAdmin()),
                        Forms\Components\Select::make('timezone')
                            // Static options: a closure makes Filament re-fetch the list over Livewire on every open.
                            ->options(self::timezoneOptions())
                            ->searchable()
                            ->optionsLimit(count(DateTimeZone::listIdentifiers()))
                            ->required()
                            ->default('Europe/Belgrade'),
                        Forms\Components\Select::make('currency')
                            ->options(self::CURRENCIES)
                            ->required()
                            ->default('EUR'),
                    ]),
                Forms\Components\Section::make('Landing page')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('tagline')
                            ->maxLength(160)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('about_text')
                            ->label('About')
                            ->helperText('Plain text. Leave a blank line between paragraphs.')
                            ->rows(5)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('address')
                            ->helperText('One line per row, e.g. street on the first line, city on the second.')
                            ->rows(2)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(32),
                        Forms\Components\TextInput::make('social_instagram')
                            ->label('Instagram URL')
                            ->url()
                            ->startsWith(['https://'])
                            ->maxLength(255),
                        Forms\Components\TextInput::make('social_facebook')
                            ->label('Facebook URL')
                            ->url()
                            ->startsWith(['https://'])
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->helperText('Landscape photo, at least 1600px wide. A stock photo is used when empty.')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory(Tenant::HERO_DIRECTORY)
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('timezone'),
                Tables\Columns\TextColumn::make('currency'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    /**
     * Tenant has no tenant_id (it IS the tenant), so TenantScope doesn't apply; scope admins here.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = static::currentUser();

        return parent::getEloquentQuery()
            ->when($user?->isTenantAdmin(), fn (Builder $query) => $query->whereKey($user->tenant_id));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function timezoneOptions(): array
    {
        $identifiers = DateTimeZone::listIdentifiers();

        return array_combine($identifiers, $identifiers);
    }

    private static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
