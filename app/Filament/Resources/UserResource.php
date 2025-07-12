<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'User Management';
    protected static ?string $label = 'Data Pengguna';
    protected static ? string $pluralLabel = 'Data Pengguna';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 30;
   

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->columns(2) // Menampilkan section berdampingan jika memungkinkan
                    ->schema([
                        Forms\Components\Section::make('Informasi Dasar') // Memberikan judul section
                            ->description('Informasi utama tentang pengguna') // Menambahkan deskripsi section
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Lengkap') // Label yang lebih deskriptif
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1), // Mengatur lebar kolom dalam group
                                Forms\Components\TextInput::make('email')
                                    ->label('Alamat Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                Forms\Components\Select::make('roles')
                                    ->label('Peran') // Label yang lebih singkat
                                    ->relationship('roles', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->multiple() // Memungkinkan memilih banyak peran
                                    ->columnSpan('full'), // Mengambil lebar penuh
                            ])
                            ->collapsible(), // Membuat section bisa dilipat
                    ]),
                Forms\Components\Group::make()
                    ->columns(2) // Menampilkan section berdampingan jika memungkinkan
                    ->schema([
                        Forms\Components\Section::make('Pengaturan Tambahan') // Judul section lain
                            ->description('Pengaturan opsional untuk pengguna')
                            ->schema([
                                Forms\Components\DateTimePicker::make('email_verified_at')
                                    ->label('Verifikasi Email')
                                    ->native(false)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('password')
                                    ->label('Kata Sandi')
                                    ->password()
                                    ->maxLength(255)
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $context) => $context === 'create')
                                    ->columnSpan(1),
                            ])
                            ->collapsible(),
                    ]),
                Forms\Components\FileUpload::make('image')
                    ->label('Foto Profil') // Label yang lebih jelas
                    ->directory('user-images')
                    ->image() // Menampilkan pratinjau gambar
                    ->columnSpan('full'), // Mengambil lebar penuh
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('email_verified_at')
                //     ->dateTime()
                //     ->sortable(),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
