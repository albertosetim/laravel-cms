<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Support\Locales;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('System');
    }

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }

    public static function getModelLabel(): string
    {
        return __('user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users');
    }

    /** Gestão de utilizadores: admins e developers. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'developer']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label(__('Name'))->required(),
                TextInput::make('email')->label(__('Email'))->email()->required()->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    // hashing explícito; só grava quando preenchida (edit mantém a atual).
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create'),
            ]),
            Section::make(__('Access'))->columns(2)->schema([
                Select::make('roles')
                    ->label(__('Roles'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->helperText(__('Roles define what the user can do.')),
                Select::make('groups')
                    ->label(__('Groups'))
                    ->relationship('groups', 'name')
                    ->multiple()
                    ->preload(),
                Select::make('locale')
                    ->label(__('Panel language'))
                    ->options(Locales::options())
                    ->placeholder(__('Site default').' ('.Locales::default().')')
                    ->native(false)
                    ->helperText(__('The language this user sees the panel in.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('Email'))->searchable()->sortable(),
                TextColumn::make('roles.name')->label(__('Roles'))->badge(),
                TextColumn::make('groups.name')->label(__('Groups'))->badge()->color('gray'),
                TextColumn::make('created_at')->label(__('Created'))->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
