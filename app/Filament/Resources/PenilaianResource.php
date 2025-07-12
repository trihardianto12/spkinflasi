<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PenilaianResource\Pages;
use App\Models\HasilAkhir;
use App\Models\Penilaian;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\SubKriteria;

class PenilaianResource extends Resource
{
    protected static ?string $model = HasilAkhir::class;
    protected static ?string $navigationLabel = 'Penilaian';
    protected static ?string $label = 'Penilaian';
    protected static ?string $pluralLabel = 'Penilaian';
    protected static ?string $navigationGroup = 'Perhitungan';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filter Data')
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai'),
                        Forms\Components\DatePicker::make('tanggal_akhir')
                            ->label('Tanggal Akhir'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Cek apakah ada filter tanggal aktif
                $hasDateFilter = request()->has('tableFilters.tanggal.tanggal_mulai') ||
                               request()->has('tableFilters.tanggal.tanggal_akhir') ||
                               request()->has('filters.tanggal.tanggal_mulai') ||
                               request()->has('filters.tanggal.tanggal_akhir'); //
                
                if (!$hasDateFilter) {
                    // Jika tidak ada filter tanggal, gunakan data terbaru per kombinasi komoditas-lokasi
                    $latestRecords = DB::table('penilaian as p1')
                        ->join('data_alternatif as da1', 'p1.alternatif_id', '=', 'da1.id') //
                        ->select('p1.id') //
                        ->whereRaw('p1.tanggal = (
                            SELECT MAX(p2.tanggal) 
                            FROM penilaian p2 
                            JOIN data_alternatif da2 ON p2.alternatif_id = da2.id
                            WHERE p2.nama_komoditas = p1.nama_komoditas 
                            AND da2.lokasi_pasar = da1.lokasi_pasar
                        )') //
                        ->pluck('id'); //
                    
                    $query->whereIn('id', $latestRecords); //
                } else {
                    // Jika ada filter tanggal, ambil semua data dalam rentang tanggal
                    // Filter tanggal akan ditangani oleh filter table secara otomatis
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id_penilaian')
                    ->label('No') //
                    ->rowIndex(),
                TextColumn::make('nama_komoditas')
                    ->label('Komoditas') //
                    ->sortable() //
                    ->searchable(), //
                    
                TextColumn::make('dataAlternatif.lokasi_pasar')
                    ->label('Lokasi Pasar') //
                    ->sortable() //
                    ->searchable(), //

                TextColumn::make('tanggal')
                    ->label('Tanggal') //
                    ->date('d/m/Y') //
                    ->sortable(), //

                 TextColumn::make('laju_inflasi')
                    ->label('C1') 
                     ->sortable(),
                    
                  TextColumn::make('jumlah_permintaan')
                   ->label('C2')
                  ->formatStateUsing(fn ($state) => SubKriteria::find($state)?->nilai ?? 'N/A')
                   ->sortable(),

                 TextColumn::make('asal_pemasok')
                ->label('C3')
                   ->formatStateUsing(fn ($state) => SubKriteria::find($state)?->nilai ?? 'N/A')
                ->sortable(),


                TextColumn::make('perubahan_harga')
                   ->label('C4')
                   ->sortable(),


                TextColumn::make('tingkat_pasokan')
                    ->label('C5')
                  ->formatStateUsing(fn ($state) => SubKriteria::find($state)?->nilai ?? 'N/A')
                    ->sortable(),

                
                
                
            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_mulai')
                            ->label('Dari Tanggal'), //
                        Forms\Components\DatePicker::make('tanggal_akhir')
                            ->label('Sampai Tanggal'), //
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['tanggal_mulai'],
                            fn ($query) => $query->whereDate('tanggal', '>=', $data['tanggal_mulai']) //
                        )->when(
                            $data['tanggal_akhir'],
                            fn ($query) => $query->whereDate('tanggal', '<=', $data['tanggal_akhir']) //
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['tanggal_mulai'] && ! $data['tanggal_akhir']) {
                            return null;
                        }
                        
                        $indication = 'Tanggal: '; //
                        if ($data['tanggal_mulai']) {
                            $indication .= 'dari ' . \Carbon\Carbon::parse($data['tanggal_mulai'])->format('d/m/Y'); //
                        }
                        if ($data['tanggal_akhir']) {
                            $indication .= ' sampai ' . \Carbon\Carbon::parse($data['tanggal_akhir'])->format('d/m/Y'); //
                        }
                        
                        return $indication;
                    }),
                    
                Tables\Filters\SelectFilter::make('nama_komoditas')
                    ->label('Komoditas') //
                    ->options(fn () => Penilaian::distinct('nama_komoditas')
                        ->pluck('nama_komoditas', 'nama_komoditas')), //
                        
                Tables\Filters\SelectFilter::make('lokasi_pasar')
                    ->label('Lokasi Pasar') //
                    ->options(fn () => \App\Models\DataAlternatif::distinct('lokasi_pasar')
                        ->pluck('lokasi_pasar', 'lokasi_pasar')) //
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value'])) {
                            $query->whereHas('dataAlternatif', function ($q) use ($data) {
                                $q->where('lokasi_pasar', $data['value']); //
                            });
                        }
                    }),
            ])
            ->actions([
                // Tidak ada aksi per baris untuk resource ini
            ])
            ->headerActions([
               
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->action(function ($action, $records) {
                        foreach ($records as $record) {
                            $dataAlternatif = $record->dataAlternatif; //
                            if ($dataAlternatif) {
                                $inputData = $dataAlternatif->inputData; //
                                if ($inputData) $inputData->delete(); //
                                else $dataAlternatif->delete(); //
                            } else {
                                $record->delete(); //
                            }
                        }
                        \Filament\Notifications\Notification::make()->title('Berhasil dihapus')->body('Semua record yang dipilih dan data turunan telah dihapus.')->success()->send(); //
                    })
                    ->requiresConfirmation(), //
            ])
           
           ;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPenilaians::route('/'),
        ];
    }

    
}