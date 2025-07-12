<?php

namespace App\Filament\Resources;

use App\Models\Kriteria;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use App\Filament\Resources\KriteriaResource\Pages;

class KriteriaResource extends Resource
{
    protected static ?string $model = Kriteria::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Perhitungan';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Data Kriteria';
    protected static ?string $label = 'Data Kriteria';
    protected static ? string $pluralLabel = 'Data Kriteria';
    


public static function getNavigationBadge(): ?string
{
    return static::getModel()::count();
}

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('kode_kriteria')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Masukkan kode kriteria'),
                Forms\Components\TextInput::make('keterangan')
                    ->label('Nama Kriteria')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Masukkan nama kriteria'),
                Forms\Components\TextInput::make('bobot')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Masukkan bobot kriteria'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id_kriteria')
                    ->label('No')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('kode_kriteria')
                    ->label('Kode Kriteria')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Nama Kriteria')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bobot')
                    ->label('Bobot')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKriterias::route('/'),
            'create' => Pages\CreateKriteria::route('/create'),
            'edit' => Pages\EditKriteria::route('/{record}/edit'),
        ];
    }
}