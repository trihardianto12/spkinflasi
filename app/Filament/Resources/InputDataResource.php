<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InputDataResource\Pages;
use App\Models\InputData;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Hidden;
use Humaidem\FilamentMapPicker\Fields\OSMMap;

class InputDataResource extends Resource
{
    protected static ?string $model = InputData::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Data Harga Komoditas';
      protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Data Harga';
    protected static ?string $label = 'Data Komoditas';
    protected static ?string $pluralLabel = 'Data Komoditas';
    protected static ?string $navigationGroup = 'Manajemen Data';
  

    public static function getNavigationBadge(): ?string
    {
        if (!Auth::user()->hasRole('super_admin')) {
            return static::getModel()::where('status', 'Pending')
                ->where('user_id', Auth::id())
                ->count();
        }
        return static::getModel()::where('status', 'Pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        if (!Auth::user()->hasRole('super_admin')) {
            return 'warning';
        }
        return static::getModel()::where('status', 'Pending')->count() > 5 ? 'danger' : 'warning';
    }

    public static function form(Form $form): Form
    {
        $schema = [
            Section::make('Informasi Komoditas')
                ->columns(2)
                ->schema([
                    Hidden::make('user_id')
                        ->default(Auth::id())
                        ->required(),
                        
                    TextInput::make('nama_komoditas')
                        ->label('Nama Komoditas')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    Select::make('jenis_komoditas')
                        ->label('Jenis Komoditas')
                        ->options([
                            'Sembako' => 'Sembako',
                            'Sayuran' => 'Sayuran',
                            'Daging' => 'Daging',
                            'Buah' => 'Buah',
                            'Bahan Pokok' => 'Bahan Pokok',
                        ])
                        ->required()
                        ->columnSpan(1),

                    TextInput::make('lokasi_pasar')
                        ->label('Lokasi Pasar')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    TextInput::make('satuan')
                        ->label('Satuan (kg/liter/pcs)')
                        ->required()
                        ->maxLength(50)
                        ->columnSpan(1),

                    Select::make('jumlah_permintaan')
                        ->label('Jumlah Permintaan')
                        ->options(function () {
                            return \App\Models\SubKriteria::where('id_kriteria', 13)
                                ->pluck('deskripsi', 'id_sub_kriteria')
                                ->toArray();
                        })
                        ->required()
                        ->columnSpan(1),

                    Select::make('tingkat_pasokan')
                        ->label('Tingkat Pasokan')
                        ->options(function () {
                            return \App\Models\SubKriteria::where('id_kriteria', 18)
                                ->pluck('deskripsi', 'id_sub_kriteria')
                                ->toArray();
                        })
                        ->required()
                        ->columnSpan(1),

                    Select::make('asal_pemasok')
                        ->label('Asal Pemasok')
                        ->options(function () {
                            return \App\Models\SubKriteria::where('id_kriteria', 14)
                                ->pluck('deskripsi', 'id_sub_kriteria')
                                ->toArray();
                        })
                        ->required()
                        ->columnSpan(1),
                ]),

            Section::make('Detail Harga')
                ->columns(2)
                ->schema([
                    TextInput::make('harga')
                        ->label('Harga (Rp)')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->columnSpan(1),

                    DatePicker::make('tanggal')
                        ->label('Tanggal Pencatatan')
                        ->required()
                        ->displayFormat('d F Y')
                        ->native(false)
                        ->columnSpan(1),
                ]),

            Section::make('Koordinat Lokasi')
                ->columns(2)
                ->schema([
                    OSMMap::make('location')
                                    ->label('Location')
                                    ->showMarker()
                                    ->draggable()
                                    ->extraControl([
                                        'zoomDelta' => 1,
                                        'zoomSnap' => 0.25,
                                        'wheelPxPerZoomLevel' => 60
                                    ])
                                    // tiles url (refer to https://www.spatialbias.com/2018/02/qgis-3.0-xyz-tile-layers/)
                                    ->tilesUrl('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}')
                                    ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set, $record) {
                                        if($record){
                                            $latitude = $record->latitude;
                                            $longitude = $record->longitude;
    
                                            if ($latitude && $longitude) {
                                                $set('location', ['lat' => $latitude, 'lng' => $longitude]);
                                            }
                                        }
                                    })
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                        $set('latitude', $state['lat']);
                                        $set('longitude', $state['lng']);
                                    }),
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('latitude')
                                            ->readOnly()
                                            ->numeric(),
                                        Forms\Components\TextInput::make('longitude')
                                            ->readOnly()
                                            ->numeric(),
                                ])->columns(2)
                            ])
                    
                    
                
        ];

        if (Auth::user()->hasRole('super_admin')) {
            $schema[] = Section::make('Approval')
                ->schema([
                    Select::make('status')
                        ->options([
                            'Pending' => 'Pending',
                            'Approved' => 'Approved',
                            'Rejected' => 'Rejected',
                        ])
                        ->required(),
                    // Forms\Components\Textarea::make('note')
                    //     ->label('Catatan')
                    //     ->columnSpanFull(),
                ]);
        }

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('no')
                ->label('No')
                ->rowIndex(),

            TextColumn::make('nama_komoditas')
                ->label('Komoditas')
                ->searchable()
                ->sortable(),

            TextColumn::make('jenis_komoditas')
                ->label('Jenis')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Sembako' => 'primary',
                    'Sayuran' => 'success',
                    'Daging' => 'danger',
                    'Buah' => 'warning',
                    'Bahan Pokok' => 'info',
                    default => 'gray',
                })
                ->sortable(),

            TextColumn::make('lokasi_pasar')
                ->label('Lokasi Pasar')
                ->searchable()
                ->sortable(),

            TextColumn::make('harga')
                ->label('Harga')
                ->money('IDR')
                ->sortable(),

            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Pending' => 'warning',
                    'Approved' => 'success',
                    'Rejected' => 'danger',
                }),

            TextColumn::make('tanggal')
                ->label('Tanggal')
                ->date('d M Y')
                ->sortable(),
        ])
        ->filters([
              
            SelectFilter::make('status')
                ->label('Status')
                ->options([
                    'Pending' => 'Pending',
                    'Approved' => 'Approved',
                    'Rejected' => 'Rejected',
                ])
                ->visible(fn (): bool => auth()->user()->hasRole('super_admin')),
            
            // Filter tanggal
            Tables\Filters\Filter::make('tanggal')
                ->form([
                    DatePicker::make('dari_tanggal')
                        ->label('Dari Tanggal')
                        ->native(false),
                    DatePicker::make('sampai_tanggal')
                        ->label('Sampai Tanggal')
                        ->native(false),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['dari_tanggal'],
                            fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                        )
                        ->when(
                            $data['sampai_tanggal'],
                            fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                        );
                })
        ])
        ->actions([
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn (InputData $record): bool => 
                        !auth()->user()->hasRole('super_admin') && $record->status === 'Approved'),
                DeleteAction::make(),
            ]),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ])
        ->defaultSort('tanggal', 'desc')
        ->striped();
}

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInputData::route('/'),
            'create' => Pages\CreateInputData::route('/create'),
            'edit' => Pages\EditInputData::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }
}