<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DataAlternatifResource\Pages;
use App\Models\DataAlternatif;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\CreateAction;
use App\Exports\DataAlternatifExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Enums\FiltersLayout;
use GuzzleHttp\Promise\Create;
use Barryvdh\DomPDF\Facade as PDF;


class DataAlternatifResource extends Resource
{
    protected static ?string $model = DataAlternatif::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Data Alternatif';
    protected static ?string $navigationGroup = 'Perhitungan';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Data Alternatif';
    protected static ? string $pluralLabel = 'Data Alternatif';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            // Section for Information
            Section::make('Informasi Komoditas')
                ->columns(2)
                ->schema([
                    TextInput::make('nama_komoditas')
                        ->label('Nama Komoditas')
                        ->disabled()
                        ->columnSpan(1),

                    TextInput::make('lokasi_pasar')
                        ->label('Lokasi Pasar')
                        ->prefix('')
                        ->disabled()
                        ->columnSpan(1),

                    TextInput::make('harga')
                        ->label('Harga')
                        ->prefix('Rp')
                        ->disabled()
                        ->columnSpan(1),

                    TextInput::make('harga_sebelumnya')
                        ->label('Harga Sebelumnya')
                        ->prefix('Rp')
                        ->disabled()
                        ->columnSpan(1),

                    

                    Select::make('asal_pemasok')
                        ->label('Asal Pemasok')
                        ->options(function () {
                            // Ambil data dari tabel SubKriteria yang memiliki id_kriteria = 14
                            return \App\Models\SubKriteria::where('id_kriteria', 14)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    // Gabungkan deskripsi dan nilai untuk digunakan sebagai label
                                    return [$item->id_sub_kriteria => $item->deskripsi ];
                                })
                                ->toArray();
                        })
                        ->required()
                        ->disabled()
                        ->columnSpan(1),
                        
                        Select::make('asal_pemasok')
                        ->label('Nilai Asal Pemasok')
                        ->options(function () {
                            // Ambil data dari tabel SubKriteria yang memiliki id_kriteria = 14
                            return \App\Models\SubKriteria::where('id_kriteria', 14)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    // Gabungkan deskripsi dan nilai untuk digunakan sebagai label
                                    return [$item->id_sub_kriteria => $item->nilai];
                                })
                                ->toArray();
                        })
                        ->required()
                        ->disabled()
                        ->columnSpan(1),
                    
                    Select::make('tingkat_pasokan')
                        ->label('Tingkat Pasokan')
                        ->options(function () {
                            // Ambil data dari tabel SubKriteria yang memiliki id_kriteria = 14
                            return \App\Models\SubKriteria::where('id_kriteria', 18)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    // Gabungkan deskripsi dan nilai untuk digunakan sebagai label
                                    return [$item->id_sub_kriteria => $item->deskripsi ];
                                })
                                ->toArray();
                        })
                        ->required()
                        ->disabled()
                        ->columnSpan(1),
                        
                        Select::make('tingkat_pasokan')
                        ->label('Nilai Tingkat Pasokan')
                        ->options(function () {
                            // Ambil data dari tabel SubKriteria yang memiliki id_kriteria = 14
                            return \App\Models\SubKriteria::where('id_kriteria', 18)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    // Gabungkan deskripsi dan nilai untuk digunakan sebagai label
                                    return [$item->id_sub_kriteria => $item->nilai];
                                })
                                ->toArray();
                        })
                        ->required()
                        ->disabled()
                        ->columnSpan(1),


                    Select::make('jumlah_permintaan')
                        ->label('Jumlah Permintaan')
                        ->options(function () {
                            // Ambil data dari tabel SubKriteria yang memiliki id_kriteria = 14
                            return \App\Models\SubKriteria::where('id_kriteria', 13)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    // Gabungkan deskripsi dan nilai untuk digunakan sebagai label
                                    return [$item->id_sub_kriteria => $item->deskripsi ];
                                })
                                ->toArray();
                        })
                        ->required()
                        ->disabled()
                        ->columnSpan(1),
                        
                        Select::make('jumlah_permintaan')
                        ->label('Nilai Jumlah Permintaan')
                        ->options(function () {
                            // Ambil data dari tabel SubKriteria yang memiliki id_kriteria = 14
                            return \App\Models\SubKriteria::where('id_kriteria', 13)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    // Gabungkan deskripsi dan nilai untuk digunakan sebagai label
                                    return [$item->id_sub_kriteria => $item->nilai];
                                })
                                ->toArray();
                        })
                        ->required()
                        ->disabled()
                        ->columnSpan(1),
                       
                        
                ]),

            // Section for Price Differences and Ratings
            Section::make('Detail Harga dan Penilaian')
                ->columns(2)
                ->schema([
                    TextInput::make('selisih_harga')
                        ->label('Selisih Harga')
                        ->prefix('Rp')
                        ->disabled()
                        ->default(fn ($record) => $record?->selisih_harga)
                        ->columnSpan(1),

                    TextInput::make('kriteria')
                        ->label('Penilaian')
                        ->prefix('')
                        ->disabled()
                        ->columnSpan(1),

                 

                    TextInput::make('perbedaan_harga_tertata')
                        ->label('Laju Inflasi')
                        ->prefix('%')
                        ->disabled()
                        ->default(fn ($record) => $record?->selisih_harga)
                        ->columnSpan(1),

                    TextInput::make('sub_kriteria')
                        ->label('Penilaian')
                        ->prefix('')
                        ->disabled()
                        ->columnSpan(1),
                ]),

            // Section for Date Picker
            Section::make('Tanggal Pencatatan')
                ->columns(1)
                ->schema([
                    DatePicker::make('tanggal')
                        ->label('Tanggal')
                        ->disabled()
                        ->displayFormat('d F Y')
                        ->native(false)
                        ->columnSpan(1),
                ]),
        ]);
}

    

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('No')
                    ->rowIndex(),
                
                TextColumn::make('nama_komoditas')
                    ->label('Nama Komoditas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lokasi_pasar')
                    ->label('Lokasi Pasar')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('harga')
                    ->label('Harga Dasar')
                    ->money('IDR')
                    ->sortable(),
                
                    TextColumn::make('harga')
                    ->label('Harga Sekarang')
                    ->money('IDR')
                    ->sortable()
                    ->color('primary'),

                    TextColumn::make('harga_sebelumnya')
                    ->label('Harga Sebelumnya')
                    ->state(function ($record) {
                        return $record->harga_sebelumnya;
                    })
                    ->money('IDR')
                    ->sortable()
                    ->color('secondary'),
                

              
                    TextColumn::make('selisih_harga')
                    ->label('Perubahan Harga')
                    ->money('IDR')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),

                    TextColumn::make('perbedaan_harga_tertata')
                    ->label('Laju Inflasi')
                    ->suffix('%')
                    ->alignCenter()
                   
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger') 
                    ->prefix(fn ($state) => $state >= 0 ? '' : '')
                  
                
            ])
            ->filters([
                SelectFilter::make('perubahan_harga')
                    ->label('Perubahan Harga')
                    ->options([
                        'naik' => 'Harga Naik',
                        'turun' => 'Harga Turun',
                        'tetap' => 'Harga Tetap'
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] == 'naik') {
                            return $query->whereRaw('harga_sekarang > harga_sebelumnya');
                        }
                        if ($data['value'] == 'turun') {
                            return $query->whereRaw('harga_sekarang < harga_sebelumnya');
                        }
                        if ($data['value'] == 'tetap') {
                            return $query->whereRaw('harga_sekarang = harga_sebelumnya');
                        }
                    })
            ])
            ->actions([
                   ActionGroup::make([
                    ViewAction::make(),
                   
                    // EditAction::make(),
                    // DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('tanggal', 'desc');
            // ->deferLoading();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDataAlternatif::route('/'),
            
         
        ];
    }
}