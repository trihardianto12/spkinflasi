<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubKriteriaResource\Pages;
use App\Filament\Resources\SubKriteriaResource\RelationManagers;
use App\Models\SubKriteria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\Section;

class SubKriteriaResource extends Resource
{
    protected static ?string $model = SubKriteria::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Perhitungan';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Data Sub Kriteria';
    protected static ?string $label = 'Data Sub Kriteria';
    protected static ? string $pluralLabel = 'Data Sub Kriteria';

   

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Form Sub Kriteria')
                    ->schema([
                        Forms\Components\Select::make('id_kriteria')
                            ->label('Kriteria')
                            ->relationship('kriteria', 'keterangan')
                            ->required(),
    
                        Forms\Components\TextInput::make('deskripsi')
                            ->label('Sub Kriteria')
                            ->required(),
    
                        Forms\Components\TextInput::make('nilai')
                            ->label('Nilai')
                            ->numeric()
                            ->required(),
                    ])
            ]);
    }
    

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
        
        ->defaultGroup('kriteria.keterangan')
            ->columns([
                
                // Tables\Columns\TextColumn::make('id_sub_kriteria')
                //     ->label('No')
                //     ->rowIndex(),
                // Tables\Columns\TextColumn::make('kriteria.keterangan')
                //     ->label('Kriteria')
                //     ->searchable()
                //     ->sortable(),
                Tables\Columns\TextColumn::make('deskripsi')
                    ->label('Sub Kriteria')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nilai')
                    ->label('Nilai')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                    ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
          
            ;
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
            'index' => Pages\ListSubKriterias::route('/'),
            'create' => Pages\CreateSubKriteria::route('/create'),
            'edit' => Pages\EditSubKriteria::route('/{record}/edit'),
        ];
    }
}
